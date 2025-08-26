<?php

namespace CCC\Admin;

defined('ABSPATH') || exit;

class MasterPasswordSettings {
    
    private $option_group = 'ccc_master_password_settings';
    private $option_name = 'ccc_master_password';
    
    public function __construct() {
        add_action('admin_menu', [$this, 'addSettingsPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('wp_ajax_ccc_create_initial_installation', [$this, 'createInitialInstallation']);
        add_action('wp_ajax_ccc_update_tracking_setting', [$this, 'updateTrackingSetting']);
        add_action('wp_ajax_ccc_update_license_server_url', [$this, 'updateLicenseServerUrl']);
        add_action('wp_ajax_ccc_update_central_reporting', [$this, 'updateCentralReporting']);
        add_action('wp_ajax_ccc_test_central_connection', [$this, 'testCentralConnection']);
    }
    
    /**
     * Add settings page to admin menu
     */
    public function addSettingsPage() {
        // Only show to users with manage_options capability (admin/super_admin)
        if (current_user_can('manage_options')) {
            add_submenu_page(
                'custom-craft-settings',
                'Master Password Settings',
                'Master Password',
                'manage_options',
                'custom-craft-settings-master-password',
                [$this, 'renderSettingsPage']
            );
        }
    }
    
    /**
     * Register settings
     */
    public function registerSettings() {
        register_setting(
            $this->option_group,
            $this->option_name,
            [$this, 'sanitizePassword']
        );
        
        add_settings_section(
            'ccc_master_password_section',
            'Master Password Configuration',
            [$this, 'renderSectionDescription'],
            'custom-craft-settings-master-password'
        );
        
        add_settings_field(
            'ccc_master_password_field',
            'Master Password',
            [$this, 'renderPasswordField'],
            'custom-craft-settings-master-password',
            'ccc_master_password_section'
        );
    }
    
    /**
     * Render the settings page
     */
    public function renderSettingsPage() {
        // Double-check user permissions
        if (!current_user_can('manage_options')) {
            wp_die('Access denied. You do not have permission to view this page.');
        }
        ?>
        <div class="wrap">
            <h1>🔐 Master Password Settings</h1>
            
            <div class="notice notice-info">
                <p><strong>ℹ️ Information:</strong> The master password is used to access the admin panel for user management and system administration.</p>
            </div>
            
            <div class="card" style="max-width: 600px;">
                <h2>Change Master Password</h2>
                
                <form method="post" action="options.php">
                    <?php
                    settings_fields($this->option_group);
                    do_settings_sections('custom-craft-settings-master-password');
                    submit_button('Update Master Password');
                    ?>
                </form>
            </div>
            
            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h3>📋 How to Use:</h3>
                <ol>
                    <li><strong>Set/Change Master Password:</strong> Enter a new master password above and save</li>
                    <li><strong>Access Admin Panel:</strong> Go to <code>Custom Craft Settings → Admin Login</code></li>
                    <li><strong>Enter Master Password:</strong> Use the password you set here to access admin functions</li>
                    <li><strong>Manage Users:</strong> Promote users to admin/super_admin roles</li>
                    <li><strong>Create Admin Users:</strong> Create new admin accounts directly</li>
                </ol>
                
                <h3>🔒 Security Features:</h3>
                <ul>
                    <li>✅ Master password is encrypted using WordPress password hashing</li>
                    <li>✅ Admin sessions expire after 24 hours</li>
                    <li>✅ Nonce verification for all admin actions</li>
                    <li>✅ Role-based access control</li>
                    <li>✅ Secure token-based authentication</li>
                </ul>
            </div>
            
            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h3>📊 Installation Management</h3>
                
                <div class="form-table">
                    <div class="form-field">
                        <label for="admin_email">Admin Email for Installation:</label>
                        <input type="email" 
                               id="admin_email" 
                               name="admin_email" 
                               class="regular-text" 
                               placeholder="your-email@domain.com"
                               value="<?php echo esc_attr(get_option('admin_email')); ?>">
                        <p class="description">This email will be used for the initial installation record.</p>
                    </div>
                    
                    <div class="form-field">
                        <button type="button" 
                                id="create-installation" 
                                class="button button-secondary">
                            Create Initial Installation Record
                        </button>
                        <p class="description">Create the initial installation record for this domain. Only needed once.</p>
                    </div>
                    
                    <div class="form-field">
                        <label>
                            <input type="checkbox" 
                                   id="enable_tracking" 
                                   name="enable_tracking" 
                                   <?php checked(get_option('ccc_enable_installation_tracking', false)); ?>>
                            Enable automatic installation tracking
                        </label>
                        <p class="description">When enabled, new user registrations will automatically create installation records.</p>
                    </div>
                </div>
            </div>
            
            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h3>🌍 Central Server Configuration</h3>
                
                <div class="form-table">
                    <div class="form-field">
                        <label for="license_server_url">Central License Server URL:</label>
                        <input type="url" 
                               id="license_server_url" 
                               name="license_server_url" 
                               class="regular-text" 
                               placeholder="https://your-domain.com"
                               value="<?php echo esc_attr(get_option('ccc_license_server_url', 'https://custom-craft-component-extended.local')); ?>">
                        <p class="description">URL of your central license server where all installations report to.</p>
                    </div>
                    
                    <div class="form-field">
                        <button type="button" 
                                id="test-connection" 
                                class="button button-secondary">
                            Test Connection
                        </button>
                        <p class="description">Test connection to the central license server.</p>
                    </div>
                    
                    <div class="form-field">
                        <label>
                            <input type="checkbox" 
                                   id="enable_central_reporting" 
                                   name="enable_central_reporting" 
                                   <?php checked(get_option('ccc_enable_central_reporting', true)); ?>>
                            Enable central server reporting
                        </label>
                        <p class="description">When enabled, this installation will report to your central server.</p>
                    </div>
                </div>
            </div>
            
            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h3>⚠️ Important Notes:</h3>
                <ul>
                    <li><strong>Keep it secure:</strong> Don't share the master password with unauthorized users</li>
                    <li><strong>Regular changes:</strong> Consider changing the master password periodically</li>
                    <li><strong>Backup:</strong> Keep a secure backup of the master password</li>
                    <li><strong>Access control:</strong> Only trusted administrators should have access</li>
                </ul>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Handle initial installation creation
            $('#create-installation').on('click', function() {
                var adminEmail = $('#admin_email').val();
                
                if (!adminEmail) {
                    alert('Please enter an admin email address.');
                    return;
                }
                
                if (!confirm('Are you sure you want to create the initial installation record for this domain?')) {
                    return;
                }
                
                $.post(ajaxurl, {
                    action: 'ccc_create_initial_installation',
                    admin_email: adminEmail,
                    nonce: '<?php echo wp_create_nonce('ccc_create_initial_installation'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Initial installation record created successfully!');
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                });
            });
            
            // Handle tracking toggle
            $('#enable_tracking').on('change', function() {
                var enabled = $(this).is(':checked');
                
                $.post(ajaxurl, {
                    action: 'ccc_update_tracking_setting',
                    enabled: enabled,
                    nonce: '<?php echo wp_create_nonce('ccc_update_tracking'); ?>'
                }, function(response) {
                    if (response.success) {
                        console.log('Tracking setting updated');
                    } else {
                        console.error('Error updating tracking setting');
                    }
                });
            });
            
            // Handle license server URL change
            $('#license_server_url').on('change', function() {
                var url = $(this).val();
                
                $.post(ajaxurl, {
                    action: 'ccc_update_license_server_url',
                    url: url,
                    nonce: '<?php echo wp_create_nonce('ccc_update_license_server_url'); ?>'
                }, function(response) {
                    if (response.success) {
                        console.log('License server URL updated');
                    } else {
                        console.error('Error updating license server URL');
                    }
                });
            });
            
            // Handle central reporting toggle
            $('#enable_central_reporting').on('change', function() {
                var enabled = $(this).is(':checked');
                
                $.post(ajaxurl, {
                    action: 'ccc_update_central_reporting',
                    enabled: enabled,
                    nonce: '<?php echo wp_create_nonce('ccc_update_central_reporting'); ?>'
                }, function(response) {
                    if (response.success) {
                        console.log('Central reporting setting updated');
                    } else {
                        console.error('Error updating central reporting setting');
                    }
                });
            });
            
            // Handle test connection
            $('#test-connection').on('click', function() {
                var button = $(this);
                var originalText = button.text();
                
                button.text('Testing...').prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'ccc_test_central_connection',
                    nonce: '<?php echo wp_create_nonce('ccc_test_central_connection'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('✅ Connection successful!\n\n' + response.data.message);
                    } else {
                        alert('❌ Connection failed!\n\n' + response.data.message);
                    }
                }).always(function() {
                    button.text(originalText).prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render section description
     */
    public function renderSectionDescription() {
        echo '<p>Configure the master password that provides access to administrative functions.</p>';
    }
    
    /**
     * Render password field
     */
    public function renderPasswordField() {
        $current_password = get_option($this->option_name);
        ?>
        <input type="password" 
               name="<?php echo esc_attr($this->option_name); ?>" 
               id="<?php echo esc_attr($this->option_name); ?>" 
               class="regular-text" 
               placeholder="Enter new master password">
        <p class="description">
            <?php if ($current_password): ?>
                ✅ Master password is currently set. Enter a new password to change it.
            <?php else: ?>
                ⚠️ No master password set. Please set one to enable admin access.
            <?php endif; ?>
        </p>
        <?php
    }
    
    /**
     * Sanitize and hash the password
     */
    public function sanitizePassword($input) {
        if (empty($input)) {
            add_settings_error(
                $this->option_name,
                'empty_password',
                'Master password cannot be empty.',
                'error'
            );
            return get_option($this->option_name); // Return current value
        }
        
        // Hash the password before storing
        return wp_hash_password($input);
    }
    
    /**
     * AJAX: Create initial installation record
     */
    public function createInitialInstallation() {
        check_ajax_referer('ccc_create_initial_installation', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $admin_email = sanitize_email($_POST['admin_email']);
        
        if (empty($admin_email)) {
            wp_send_json_error(['message' => 'Admin email is required']);
        }
        
        $user_manager = new \CCC\Services\UserManager();
        $result = $user_manager->createInitialInstallation($admin_email);
        
        if ($result !== false) {
            wp_send_json_success(['message' => 'Initial installation record created successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to create installation record']);
        }
    }
    
    /**
     * AJAX: Update installation tracking setting
     */
    public function updateTrackingSetting() {
        check_ajax_referer('ccc_update_tracking', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
        
        $user_manager = new \CCC\Services\UserManager();
        $user_manager->setInstallationTracking($enabled);
        
        wp_send_json_success(['message' => 'Tracking setting updated successfully']);
    }
    
    /**
     * AJAX: Update license server URL
     */
    public function updateLicenseServerUrl() {
        check_ajax_referer('ccc_update_license_server_url', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $url = esc_url_raw($_POST['url']);
        
        if (empty($url)) {
            wp_send_json_error(['message' => 'URL cannot be empty']);
        }
        
        update_option('ccc_license_server_url', $url);
        
        wp_send_json_success(['message' => 'License server URL updated successfully']);
    }
    
    /**
     * AJAX: Update central reporting setting
     */
    public function updateCentralReporting() {
        check_ajax_referer('ccc_update_central_reporting', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
        
        update_option('ccc_enable_central_reporting', $enabled);
        
        wp_send_json_success(['message' => 'Central reporting setting updated successfully']);
    }
    
    /**
     * AJAX: Test connection to central server
     */
    public function testCentralConnection() {
        check_ajax_referer('ccc_test_central_connection', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $central_server = new \CCC\Services\CentralLicenseServer();
        $result = $central_server->testConnection();
        
        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }
}
