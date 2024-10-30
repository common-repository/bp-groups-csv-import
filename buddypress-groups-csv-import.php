<?php
/**
 * Plugin Name: BuddyPress Groups CSV Import
 * Plugin URI: http://wordpress.org/plugins/bp-groups-csv-import/
 * GitHub: https://github.com/erviveksharma/Buddypress-Groups-Import
 * Description: Import BuddyPress groups from CSV file.
 * Version: 1.0.5
 * Requires at least: WP 3.5.1, BuddyPress 1.6.5
 * Tested up to: WP 6.1, BuddyPress 10.6.0
 * Text Domain: buddypress-groups-csv-import
 * Author: Vivek Sharma
 * Author URI: http://ProvisTechnologies.com
 * License: GNU General Public License 3.0 or newer (GPL) http://www.gnu.org/licenses/gpl.html
 */

# check required wordpress plugins
register_activation_hook( __FILE__, 'pt_buddypress_groups_csv_import_install' );
function pt_buddypress_groups_csv_import_install() {
    global $bp;

    # Check whether BP is active and whether Groups component is loaded, and throw error if not
    if(!(function_exists('BuddyPress') || is_a($bp,'BuddyPress')) || !bp_is_active('groups')) {
       echo 'BuddyPress is not installed or the Groups component is not activated. Cannot continue.';
        exit;
    }
}

# register admin menu
add_action('admin_menu', 'pt_buddypress_groups_csv_import_admin_menu_register');
function pt_buddypress_groups_csv_import_admin_menu_register() {
    add_submenu_page(
        'tools.php',
        'BP Groups CSV Import',
        'BP Groups CSV Import',
        'publish_pages',
        'bp-groups-csv-import',
        'pt_buddypress_groups_csv_import_page_display'
    );
}

# display admin page
function pt_buddypress_groups_csv_import_page_display() {
    
    # check user capability
    if ( !current_user_can( 'publish_pages' ) )  {
        wp_die('You do not have sufficient permissions to access this page.');
    }

    # pre-setup
    $notice = '';
    $errors = '';

    # start import
    if (!empty($_POST)) {
        global $bp, $wpdb;

        pt_buddypress_groups_csv_import_install();

        # pre-controls
        if ($_FILES['csv_file']['size'] == 0) {
            echo "It is a blank file";
            exit;
        }
        if ($_FILES['csv_file']['error'] != 0) {
            echo "Upload error";
            exit;
        }
        $info = pathinfo($_FILES['csv_file']['name']);

        if($info['extension'] != 'csv'){
            echo "Only csv file can be uploaded";
            exit;
        }

        # extract post values
        extract( $_POST, EXTR_OVERWRITE );

        # load CSV file
        if (($handle = fopen($_FILES['csv_file']['tmp_name'], "r")) !== FALSE) {
            # read 1000 lines per run
            $group_count = 0;
            $existing_group_count = 0;
            $i=0;
            while (($data = fgetcsv($handle)) !== FALSE) {
                if ($data[0]== '' || $data[1]=='' || $data[2]=='' || $data[3]=='') {
                    continue;
                }
                # get details from csv file
                $csv_group_name = trim($data[0]);
                $csv_group_description = trim($data[1]);
                $csv_group_status = trim($data[2]);
                $csv_group_invite_status = trim($data[3]);

                if(sizeof($data) != 4) {
                    echo "CSV file is not according to sample CSV file";
                    exit;
                }

                $group_slug = sanitize_title_with_dashes(esc_attr($csv_group_name));

                $group_search_args = array(
                    'slug' => $group_slug
                );

                $existing_group = groups_get_groups($group_search_args);
                //  var_dump($existing_group);

                # create group
                $args = array (
                    'name'          => $csv_group_name,
                    'description'   => $csv_group_description,
                    'slug'          => groups_check_slug($group_slug),
                    'status'        => $csv_group_status,
                );

                if($existing_group['total'] > 0) {
                    $existing_group_count++;
                    continue;
                }

                else {
                    $new_group_id = groups_create_group ($args);
                }

                # group created successfully
                if (!empty($new_group_id)) {
                    $u = '<a href="'.bp_loggedin_user_domain('/').'" title="'.bp_get_loggedin_user_username().'">'. bp_get_loggedin_user_username().'</a>';
                    $g = '<a href="'.site_url().'/groups/'.$group_slug.'/">'.$csv_group_name.'</a>';

                    # add BP activity
                    bp_activity_add (array(
                      'action'            => sprintf ( '%s created the group %s', $u, $g),
                      'component'         => 'groups',
                      'type'              => 'created_group',
                      'primary_link'      => bp_loggedin_user_domain('/'),
                      'user_id'           => bp_loggedin_user_id(),
                      'item_id'           => $new_group_id
                    ));

                    # set invite status
                    groups_update_groupmeta( $new_group_id, 'invite_status', $csv_group_invite_status );

                    $group_count++;
                }
                else {
                    echo sprintf( 'Cannot create group %s, probably a temporary mysql error', $csv_group_name);
                    exit;
                }// else

            } // while
            fclose($handle);
        } // if
        else {
            echo 'Cannot open uploaded CSV file, contact your hosting support.';
            exit;
        }

        if ($existing_group_count != 0  ) {
            $error = '<div class="error settings-error" id="setting-error"><p><strong>' .sprintf ( 'Total %d groups are already found with the same name.', $existing_group_count ) .'</strong></p></div>';
        }
        if ($group_count > 0 ) {
            $notice = '<div class="updated settings-error" id="setting-error"><p><strong>'.sprintf ( 'Total %d groups are imported.', $group_count ).'</strong></p></div>';
        }        
    } // if

    # display admin page content
    echo '<div class="wrap">';
        echo '<h2>'. 'BuddyPress Groups CSV Import' .'</h2>';
        echo $error;
        echo $notice;

        echo '<p>'.'This plugin imports BuddyPress groups with their settings from a CSV file.';
        echo '<p>'.'Prepare CSV file, and then click import. That is all, enjoy'.'</p>';
        echo '<p><strong>'. 'Notes :'.'</strong><br>';
        echo '* CSV file structure must match with the sample.'.'<br>';
        echo '* If you get "Request timeout" or similar timeout message while trying to import large CSV file contact your hosting support or split your files into two or more part.'.'<br>';
        echo '</p>';

        echo '<form name="form" action="tools.php?page=bp-groups-csv-import" method="post" enctype="multipart/form-data">';
        echo '<h3>'. 'Import Groups'.'</h3>';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="csv_file">'. 'Choose CSV File :'.' </label></th>';
        echo '<td><input type="file" id="csv_file" name="csv_file" size="25"></td></tr>';
        echo '</tbody></table>';
        echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="'. 'Start Import'.'"></p>';
        wp_nonce_field( 'bp-groups-csv-import' );
        echo '</form><p></p>';

        echo '<p><h3>'. 'CSV Sample'.'</h3><code>';
        echo '[Group Name],[Group Description],[Group Status],[Group Invite Status]'.'<br>';
        echo 'Test group, Group description, private, mods';
        echo '</code></p>';
        echo 'For downloading sample.csv file, click '; ?>
        <a href="<?php echo plugins_url( 'sample.csv', __FILE__  ); ?> "> here </a>
    <?php echo '</div>';
}

// Add link to settings page
function pt_buddypress_groups_csv_settings_link($links, $file) {
    if ( $file == plugin_basename(dirname(__FILE__) . '/buddypress-groups-csv-import.php') )
    {
        $links[] = '<a href="tools.php?page=bp-groups-csv-import">' . 'Settings' . '</a>';
    }
    return $links;
}
add_filter('plugin_action_links', 'pt_buddypress_groups_csv_settings_link', 10, 2);
?>
