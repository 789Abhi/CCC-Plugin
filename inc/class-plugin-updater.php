<?php
defined('ABSPATH') || exit;

class Custom_Craft_Component_Updater {
    private $remote_manifest_url = 'https://raw.githubusercontent.com/789Abhi/CCC-Plugin/Master/manifest.json';
    private $plugin_slug = 'custom-craft-component';
    private $version_option = 'ccc_plugin_build_version';
    private $last_check_option = 'ccc_plugin_last_update_check';
    private $plugin_file = 'custom-craft-component/custom-craft-component.php';

    public function __construct() {
        // Enable WordPress debug logging
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }
        if (!defined('WP_DEBUG_LOG')) {
            define('WP_DEBUG_LOG', true);
        }
        
        // Store current version on initialization
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $this->plugin_file);
        update_option($this->version_option, $plugin_data['Version']);
        
        // Log initialization
        error_log("CCC Plugin Updater initialized. Plugin version: {$plugin_data['Version']}");
        
        // Set up hooks
        add_action('admin_init', [$this, 'schedule_update_check']);
        add_action('ccc_plugin_update_check', [$this, 'check_for_updates']);
        add_action('admin_notices', [$this, 'update_notice']);
        add_action('wp_ajax_ccc_plugin_manual_update', [$this, 'manual_update_handler']);
        
        // Run immediate check
        add_action('admin_init', [$this, 'run_initial_check'], 20);
        
        // Ensure the scheduled event is set up
        if (!wp_next_scheduled('ccc_plugin_update_check')) {
            wp_schedule_event(time(), 'hourly', 'ccc_plugin_update_check');
            error_log("CCC Plugin: Scheduled hourly update check");
        }
    }
    
    public function get_manifest_url() {
        return $this->remote_manifest_url;
    }
    
    public function run_initial_check() {
        static $checked = false;
        if (!$checked) {
            $checked = true;
            $this->check_for_updates();
        }
    }

    public function schedule_update_check() {
        // Ensure the scheduled event exists
        if (!wp_next_scheduled('ccc_plugin_update_check')) {
            wp_schedule_event(time(), 'hourly', 'ccc_plugin_update_check');
            error_log("CCC Plugin: Scheduled update check from admin_init");
        }
    }

    public function check_for_updates() {
        // Log update check start
        error_log('CCC Plugin: Running update check...');
        
        // Get current version
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $this->plugin_file);
        $current_version = $plugin_data['Version'];
        error_log("CCC Plugin: Current version from plugin file: {$current_version}");
        
        // Store current version
        update_option($this->version_option, $current_version);
        
        // Get remote data
        $remote_data = $this->get_remote_manifest();
        
        if (!$remote_data) {
            error_log('CCC Plugin: Failed to get remote manifest data');
            return;
        }
        
        error_log("CCC Plugin: Remote version: {$remote_data['version']}");
        
        // Compare versions
        $update_needed = version_compare($current_version, $remote_data['version'], '<');
        error_log("CCC Plugin: Update needed: " . ($update_needed ? 'Yes' : 'No'));
        
        if ($update_needed) {
            // Update available
            update_option('ccc_plugin_update_available', true);
            update_option('ccc_plugin_new_version', $remote_data['version']);
            update_option('ccc_plugin_download_url', $remote_data['download_url']);
            error_log("CCC Plugin: Update available! New version: {$remote_data['version']}");
        } else {
            // No update available
            delete_option('ccc_plugin_update_available');
            delete_option('ccc_plugin_new_version');
            delete_option('ccc_plugin_download_url');
            error_log("CCC Plugin: No update available. Current version is up to date.");
        }
        
        // Update last check time
        update_option($this->last_check_option, time());
    }

    public function get_remote_manifest() {
        error_log("CCC Plugin: Fetching manifest from {$this->remote_manifest_url}");
        
        $response = wp_remote_get($this->remote_manifest_url, [
            'timeout' => 30,
            'sslverify' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache'
            ],
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
        ]);
        
        if (is_wp_error($response)) {
            error_log('CCC Plugin: Manifest request failed: ' . $response->get_error_message());
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            error_log("CCC Plugin: Manifest request returned HTTP {$code}");
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        error_log("CCC Plugin: Raw response body: " . substr($body, 0, 100) . "...");
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('CCC Plugin: JSON parse error: ' . json_last_error_msg());
            return false;
        }
        
        if (!isset($data['version']) || !isset($data['download_url'])) {
            error_log('CCC Plugin: Invalid manifest format. Missing required fields.');
            return false;
        }
        
        error_log("CCC Plugin: Manifest parsed successfully. Version: {$data['version']}");
        return $data;
    }

    public function update_notice() {
        // Check if update is available
        if (get_option('ccc_plugin_update_available')) {
            $new_version = get_option('ccc_plugin_new_version');
            $current_version = get_option($this->version_option);
            
            error_log("CCC Plugin: Showing update notice. Current: {$current_version}, New: {$new_version}");
            
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong>Custom Craft Component Update Available:</strong> 
                    Version <?php echo esc_html($new_version); ?> is now available. 
                    You're currently using version <?php echo esc_html($current_version); ?>.
                    <button id="ccc-update-btn" class="button button-primary">Update Now</button>
                </p>
            </div>
            <script>
            jQuery(document).ready(function($) {
                $('#ccc-update-btn').on('click', function(e) {
                    e.preventDefault();
                    
                    var $button = $(this);
                    $button.text('Updating...').prop('disabled', true);
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'ccc_plugin_manual_update',
                            nonce: '<?php echo wp_create_nonce('ccc_update_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $button.text('Update Successful!');
                                setTimeout(function() {
                                    location.reload();
                                }, 1000);
                            } else {
                                alert('Update failed: ' + response.data);
                                $button.text('Update Failed').prop('disabled', false);
                            }
                        },
                        error: function() {
                            alert('Update request failed. Please try again.');
                            $button.text('Update Now').prop('disabled', false);
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
        
        error_log('CCC Plugin: Manual update triggered');

        // Ensure user has proper capabilities
        if (!current_user_can('update_plugins')) {
            error_log('CCC Plugin: Update failed - insufficient permissions');
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Perform update
        $update_result = $this->perform_plugin_update();

        if ($update_result) {
            // Clear update flags
            delete_option('ccc_plugin_update_available');
            delete_option('ccc_plugin_new_version');
            error_log('CCC Plugin: Update successful');
            wp_send_json_success('Plugin updated successfully');
        } else {
            error_log('CCC Plugin: Update failed - see previous logs');
            wp_send_json_error('Update failed. Check server logs for details.');
        }
    }

    private function perform_plugin_update() {
        error_log('CCC Plugin: Starting plugin update process');
        
        // Ensure WP_Filesystem is available
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');

        // Get the download URL from the stored option
        $download_url = get_option('ccc_plugin_download_url');
        if (!$download_url) {
            error_log('CCC Plugin: Update Failed - No download URL available');
            return false;
        }
        
        error_log("CCC Plugin: Downloading from {$download_url}");

        // Create upgrader
        $skin = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);
        
        // Set upgrader to overwrite files
        add_filter('upgrader_package_options', function($options) {
            $options['clear_destination'] = true;
            return $options;
        });
        
        // Download and install the update
        $result = $upgrader->install($download_url, ['overwrite_package' => true]);
        
        if (is_wp_error($result)) {
            error_log('CCC Plugin: Upgrader error: ' . $result->get_error_message());
            return false;
        }
        
        if (!$result) {
            error_log('CCC Plugin: Update failed');
            error_log('Skin errors: ' . json_encode($skin->get_errors()));
            return false;
        }

        // If installation was successful, update the version in the database
        $new_version = get_option('ccc_plugin_new_version');
        if ($new_version) {
            update_option($this->version_option, $new_version);
            error_log("CCC Plugin: Version updated to {$new_version} in database");
            
            // Make sure the plugin is activated
            $plugin_file = plugin_basename(WP_PLUGIN_DIR . '/' . $this->plugin_file);
            if (!is_plugin_active($plugin_file)) {
                error_log("CCC Plugin: Reactivating plugin");
                activate_plugin($plugin_file);
            }
        }

        return true;
    }
}