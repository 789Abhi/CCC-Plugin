<?php

namespace CCC\Admin;

/**
 * License Settings Page
 * Handles license key management and validation
 */
class LicenseSettings {
    
    private $license_validator;
    
    public function __construct() {
        $this->license_validator = new \CCC\Services\LicenseValidator();
        add_action('admin_menu', [$this, 'add_license_menu']);
        add_action('admin_init', [$this, 'register_license_settings']);
    }
    
    /**
     * Add license menu to admin
     */
    public function add_license_menu() {
        add_submenu_page(
            'custom-craft-component',
            'License Settings',
            'License Settings',
            'manage_options',
            'ccc-license-settings',
            [$this, 'license_settings_page']
        );
    }
    
    /**
     * Register license settings
     */
    public function register_license_settings() {
        register_setting('ccc_license_settings', 'ccc_license_key');
        register_setting('ccc_license_settings', 'ccc_license_required');
        register_setting('ccc_license_settings', 'ccc_license_api_url');
        register_setting('ccc_license_settings', 'ccc_license_api_key');
    }
    
    /**
     * License settings page
     */
    public function license_settings_page() {
        $license_key = get_option('ccc_license_key', '');
        $license_required = get_option('ccc_license_required', false);
        $api_url = get_option('ccc_license_api_url', 'https://custom-craft-component-backend.vercel.app/api');
        $api_key = get_option('ccc_license_api_key', '');
        
        // Handle license validation
        if (isset($_POST['validate_license']) && !empty($license_key)) {
            $validation = $this->license_validator->validate_license($license_key);
            
            // Debug information
            $debug_info = '';
            if (isset($validation['license'])) {
                $debug_info .= '<p><strong>Plan:</strong> ' . esc_html($validation['license']['plan'] ?? 'Unknown') . '</p>';
                $debug_info .= '<p><strong>Status:</strong> ' . esc_html($validation['license']['status'] ?? 'Unknown') . '</p>';
                $debug_info .= '<p><strong>Expires:</strong> ' . esc_html($validation['license']['expiresAt'] ?? 'Unknown') . '</p>';
            }
            
            $validation_message = $validation['valid'] ? 
                '<div class="notice notice-success"><p>License is valid!</p>' . $debug_info . '</div>' : 
                '<div class="notice notice-error"><p>' . esc_html($validation['message']) . '</p>' . $debug_info . '</div>';
        }
        
        ?>
        <div class="wrap">
            <h1>License Settings</h1>
            
            <?php if (isset($validation_message)) echo $validation_message; ?>
            
            <form method="post" action="options.php">
                <?php settings_fields('ccc_license_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">License Key</th>
                        <td>
                            <input type="text" name="ccc_license_key" value="<?php echo esc_attr($license_key); ?>" class="regular-text" />
                            <p class="description">Enter your Custom Craft Component license key</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Require License for AI</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ccc_license_required" value="1" <?php checked($license_required); ?> />
                                Require valid license to use AI component generation
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">API URL</th>
                        <td>
                            <input type="url" name="ccc_license_api_url" value="<?php echo esc_attr($api_url); ?>" class="regular-text" />
                            <p class="description">Backend API URL for license validation</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">API Key</th>
                        <td>
                            <input type="password" name="ccc_license_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                            <p class="description">Optional API key for authentication</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save Settings'); ?>
            </form>
            
            <?php if (!empty($license_key)): ?>
                <form method="post" style="margin-top: 20px;">
                    <?php wp_nonce_field('validate_license'); ?>
                    <input type="submit" name="validate_license" class="button button-secondary" value="Validate License" />
                </form>
            <?php endif; ?>
            
            <?php $this->display_license_status($license_key); ?>
        </div>
        <?php
    }
    
    /**
     * Display license status
     */
    private function display_license_status($license_key) {
        if (empty($license_key)) {
            return;
        }
        
        $status = $this->license_validator->get_license_status($license_key);
        
        ?>
        <div class="card" style="margin-top: 20px;">
            <h2>License Status</h2>
            
            <?php if ($status['status'] === 'valid'): ?>
                <div class="notice notice-success inline">
                    <p><strong>Status:</strong> Active</p>
                    <p><strong>Plan:</strong> <?php echo esc_html(ucfirst($status['plan'])); ?></p>
                    <p><strong>Usage:</strong> <?php echo esc_html($status['usage_count']); ?> / <?php echo esc_html($status['max_usage']); ?> (<?php echo esc_html(round($status['usage_percentage'], 1)); ?>%)</p>
                    <p><strong>Expires:</strong> <?php echo esc_html(date('F j, Y', strtotime($status['expires_at']))); ?></p>
                </div>
            <?php else: ?>
                <div class="notice notice-error inline">
                    <p><strong>Status:</strong> <?php echo esc_html($status['message']); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
