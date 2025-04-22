<?php
defined('ABSPATH') || exit;

class Custom_Craft_Component_Updater {
    private $remote_manifest_url = 'https://raw.githubusercontent.com/789Abhi/CCC-Plugin/Master/build/manifest.json';
    private $plugin_slug = 'custom-craft-component';
    private $version_option = 'ccc_plugin_build_version';
    private $last_check_option = 'ccc_plugin_last_update_check';

    public function __construct() {
        add_action('admin_init', [$this, 'schedule_update_check']);
        add_action('ccc_plugin_update_check', [$this, 'check_for_updates']);
        add_action('admin_notices', [$this, 'update_notice']);
        add_action('wp_ajax_ccc_plugin_manual_update', [$this, 'manual_update_handler']);
        
        // Ensure the scheduled event is set up
        if (!wp_next_scheduled('ccc_plugin_update_check')) {
            wp_schedule_event(time(), 'hourly', 'ccc_plugin_update_check');
        }
    }

    public function schedule_update_check() {
        // Ensure the scheduled event exists
        if (!wp_next_scheduled('ccc_plugin_update_check')) {
            wp_schedule_event(time(), 'hourly', 'ccc_plugin_update_check');
        }
    }

    public function check_for_updates() {
        $current_version = get_option($this->version_option, '1.0.0');
        $remote_data = $this->get_remote_manifest();

        if (!$remote_data) {
            return;
        }

        // Compare versions
        if (version_compare($current_version, $remote_data['version'], '<')) {
            // Update available
            update_option('ccc_plugin_update_available', true);
            update_option('ccc_plugin_new_version', $remote_data['version']);
        } else {
            // No update available
            delete_option('ccc_plugin_update_available');
            delete_option('ccc_plugin_new_version');
        }

        // Update last check time
        update_option($this->last_check_option, time());
    }

    public function get_remote_manifest() {
        $response = wp_remote_get($this->remote_manifest_url, [
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            error_log('CCC Plugin Update Check Failed: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    public function update_notice() {
        // Check if update is available
        if (get_option('ccc_plugin_update_available')) {
            $new_version = get_option('ccc_plugin_new_version');
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    A new version (<?php echo esc_html($new_version); ?>) of Custom Craft Component is available. 
                    <button id="ccc-update-btn" class="button button-primary">Update Now</button>
                </p>
            </div>
            <script>
            jQuery(document).ready(function($) {
                $('#ccc-update-btn').on('click', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'ccc_plugin_manual_update',
                            nonce: '<?php echo wp_create_nonce('ccc_update_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Update failed: ' + response.data);
                            }
                        }
                    });
                });
            });
            </script>
            <?php
        }
    }

    public function manual_update_handler() {
        // Verify nonce
        check_ajax_referer('ccc_update_nonce', 'nonce');

        // Ensure user has proper capabilities
        if (!current_user_can('update_plugins')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Perform update
        $update_result = $this->perform_plugin_update();

        if ($update_result) {
            // Clear update flags
            delete_option('ccc_plugin_update_available');
            delete_option('ccc_plugin_new_version');
            wp_send_json_success('Plugin updated successfully');
        } else {
            wp_send_json_error('Update failed');
        }
    }

    private function perform_plugin_update() {
        // Ensure WP_Filesystem is available
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        WP_Filesystem();
        global $wp_filesystem;

        // Download update
        $remote_zip = 'https://github.com/789Abhi/CCC-Plugin/archive/Master.zip';
        $temp_file = download_url($remote_zip);

        if (is_wp_error($temp_file)) {
            error_log('CCC Plugin Download Failed: ' . $temp_file->get_error_message());
            return false;
        }

        // Prepare plugin directory
        $plugin_dir = plugin_dir_path(__FILE__) . '../';

        // Unzip to plugin directory
        $unzip_result = unzip_file($temp_file, $plugin_dir);

        // Clean up temp file
        unlink($temp_file);

        if ($unzip_result) {
            // Update version in database
            $remote_data = $this->get_remote_manifest();
            if ($remote_data) {
                update_option($this->version_option, $remote_data['version']);
            }
            return true;
        }

        error_log('CCC Plugin Unzip Failed');
        return false;
    }
}
