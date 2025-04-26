<?php
defined('ABSPATH') || exit;

class Custom_Craft_Component_Updater {
    private $remote_manifest_url = 'https://raw.githubusercontent.com/789Abhi/CCC-Plugin/Master/manifest.json';
        private $plugin_slug = 'custom-craft-component';
        private $version_option = 'ccc_plugin_build_version';
        private $last_check_option = 'ccc_plugin_last_update_check';
        private $plugin_file = 'custom-craft-component.php'; // Adjust if needed

    public function __construct() {
         // Store current version on plugin init
         $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $this->plugin_file);
         update_option($this->version_option, $plugin_data['Version']);

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
        // Add detailed logging
        error_log('CCC Plugin Update Check Started');
    
        $current_version = get_option($this->version_option, '1.1.2');
        error_log("Current Version: {$current_version}");
    
        $remote_data = $this->get_remote_manifest();
    
        if (!$remote_data) {
            error_log('CRITICAL: Failed to retrieve remote manifest');
            return;
        }
    
        error_log("Remote Version: {$remote_data['version']}");
    
        // More verbose version comparison
        $update_available = version_compare($current_version, $remote_data['version'], '<');
        error_log("Update Available: " . ($update_available ? 'Yes' : 'No'));
    
        if ($update_available) {
            update_option('ccc_plugin_update_available', true);
            update_option('ccc_plugin_new_version', $remote_data['version']);
            update_option('ccc_plugin_download_url', $remote_data['download_url']);
            
            error_log("Update Details: 
                Current Version: {$current_version}
                New Version: {$remote_data['version']}
                Download URL: {$remote_data['download_url']}
            ");
        }
    }
    

    public function get_remote_manifest() {
        try {
            $response = wp_remote_get($this->remote_manifest_url, [
                'timeout' => 30,
                'sslverify' => false,
                'headers' => [
                    'Accept' => 'application/json',
                    'Cache-Control' => 'no-cache'  // Prevent caching
                ],
            ]);
    
            if (is_wp_error($response)) {
                throw new Exception('Remote request failed: ' . $response->get_error_message());
            }
    
            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                throw new Exception("HTTP {$code} returned");
            }
    
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
    
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON parsing failed: ' . json_last_error_msg());
            }
    
            // Validate manifest structure
            $required_keys = ['version', 'download_url'];
            foreach ($required_keys as $key) {
                if (!isset($data[$key])) {
                    throw new Exception("Missing required key: {$key}");
                }
            }
    
            return $data;
        } catch (Exception $e) {
            error_log('CCC Plugin Manifest Error: ' . $e->getMessage());
            return false;
        }
    }
    

    public function update_notice() {
        if (get_option('ccc_plugin_update_available')) {
            $new_version = get_option('ccc_plugin_new_version');
            $download_url = get_option('ccc_plugin_download_url');
    
            // Log update availability
            error_log("Update Notice Triggered. New Version: {$new_version}");
    
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    🚀 Custom Craft Component Update Available: 
                    Version <?php echo esc_html($new_version); ?>
                    <a href="<?php echo esc_url($download_url); ?>" target="_blank">
                        Download
                    </a>
                    <button id="ccc-update-btn" class="button button-primary">
                        Update Automatically
                    </button>
                </p>
            </div>
            <script>
            jQuery(document).ready(function($) {
                $('#ccc-update-btn').on('click', function(e) {
                    e.preventDefault();
                    if(confirm('Are you sure you want to update the plugin?')) {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'ccc_plugin_manual_update',
                                nonce: '<?php echo wp_create_nonce('ccc_update_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert('Plugin updated successfully!');
                                    location.reload();
                                } else {
                                    alert('Update failed: ' + response.data);
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Update Error:', error);
                                alert('An unexpected error occurred');
                            }
                        });
                    }
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
        require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');

        // Get the download URL from the stored option
        $download_url = get_option('ccc_plugin_download_url');
        if (!$download_url) {
            error_log('CCC Plugin Update Failed: No download URL available');
            return false;
        }

        // Download the update
        $skin = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);
        $result = $upgrader->install($download_url, ['overwrite_package' => true]);

        if (is_wp_error($result)) {
            error_log('CCC Plugin Update Error: ' . $result->get_error_message());
            return false;
        }

        // If installation was successful, update the version in the database
        if ($result) {
            $remote_data = $this->get_remote_manifest();
            if ($remote_data) {
                update_option($this->version_option, $remote_data['version']);
                
                // Reactivate the plugin if it was active
                if (is_plugin_active($this->plugin_file)) {
                    activate_plugin($this->plugin_file);
                }
            }
        }

        return $result;
    }
}
