<?php

namespace CCC\Services;

defined('ABSPATH') || exit;

class CentralLicenseServer {
    
    private $license_server_url;
    private $plugin_identifier;
    private $current_domain;
    
    public function __construct() {
        $this->license_server_url = get_option('ccc_license_server_url', 'https://custom-craft-component-extended.local');
        $this->plugin_identifier = 'custom-craft-component';
        $this->current_domain = $_SERVER['HTTP_HOST'];
        
        // Only report if central reporting is enabled
        if (get_option('ccc_enable_central_reporting', true)) {
            // Hook into user registration to report to central server
            add_action('ccc_user_registered', [$this, 'reportUserRegistration'], 10, 2);
            
            // Hook into plugin activation to report installation
            add_action('ccc_plugin_activated', [$this, 'reportPluginInstallation']);
            
            // Hook into plugin deactivation to report removal
            add_action('ccc_plugin_deactivated', [$this, 'reportPluginDeactivation']);
            
            // Periodic health check and reporting
            add_action('wp_scheduled_delete', [$this, 'periodicHealthCheck']);
        }
    }
    
    /**
     * Report new user registration to central server
     */
    public function reportUserRegistration($user_id, $user_data) {
        $report_data = [
            'action' => 'report_user_registration',
            'domain' => $this->current_domain,
            'user_id' => $user_id,
            'user_email' => $user_data['email'],
            'user_phone' => $user_data['phone'],
            'user_role' => $user_data['role'],
            'registration_date' => current_time('mysql'),
            'plugin_version' => get_option('ccc_plugin_version', '2.0'),
            'wordpress_version' => get_bloginfo('version'),
            'site_url' => get_site_url(),
            'admin_email' => get_option('admin_email'),
            'timestamp' => time()
        ];
        
        $this->sendToCentralServer($report_data);
    }
    
    /**
     * Report plugin installation to central server
     */
    public function reportPluginInstallation() {
        $report_data = [
            'action' => 'report_plugin_installation',
            'domain' => $this->current_domain,
            'site_name' => get_bloginfo('name'),
            'site_description' => get_bloginfo('description'),
            'admin_email' => get_option('admin_email'),
            'installation_date' => current_time('mysql'),
            'plugin_version' => get_option('ccc_plugin_version', '2.0'),
            'wordpress_version' => get_bloginfo('version'),
            'site_url' => get_site_url(),
            'timestamp' => time()
        ];
        
        $this->sendToCentralServer($report_data);
    }
    
    /**
     * Report plugin deactivation to central server
     */
    public function reportPluginDeactivation() {
        $report_data = [
            'action' => 'report_plugin_deactivation',
            'domain' => $this->current_domain,
            'deactivation_date' => current_time('mysql'),
            'timestamp' => time()
        ];
        
        $this->sendToCentralServer($report_data);
    }
    
    /**
     * Periodic health check and reporting
     */
    public function periodicHealthCheck() {
        // Only run once per day
        $last_check = get_option('ccc_last_health_check', 0);
        if (time() - $last_check < 86400) {
            return;
        }
        
        $health_data = [
            'action' => 'health_check',
            'domain' => $this->current_domain,
            'site_name' => get_bloginfo('name'),
            'admin_email' => get_option('admin_email'),
            'plugin_version' => get_option('ccc_plugin_version', '2.0'),
            'wordpress_version' => get_bloginfo('version'),
            'site_url' => get_site_url(),
            'active_users_count' => $this->getActiveUsersCount(),
            'components_count' => $this->getComponentsCount(),
            'last_activity' => current_time('mysql'),
            'timestamp' => time()
        ];
        
        $this->sendToCentralServer($health_data);
        update_option('ccc_last_health_check', time());
    }
    
    /**
     * Send data to central license server
     */
    private function sendToCentralServer($data) {
        $url = $this->license_server_url . '/wp-json/ccc-central/v1/report';
        
        $response = wp_remote_post($url, [
            'method' => 'POST',
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => false, // Non-blocking for better performance
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Custom-Craft-Component/' . get_option('ccc_plugin_version', '2.0'),
                'X-CCC-Domain' => $this->current_domain,
                'X-CCC-Timestamp' => time()
            ],
            'body' => json_encode($data),
            'cookies' => []
        ]);
        
        // Log the attempt
        if (is_wp_error($response)) {
            error_log('CCC: Failed to report to central server: ' . $response->get_error_message());
        } else {
            error_log('CCC: Successfully reported to central server: ' . $data['action']);
        }
    }
    
    /**
     * Get active users count for this installation
     */
    private function getActiveUsersCount() {
        global $wpdb;
        $table = $wpdb->prefix . 'ccc_users';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
            return $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'active'");
        }
        
        return 0;
    }
    
    /**
     * Get components count for this installation
     */
    private function getComponentsCount() {
        global $wpdb;
        $table = $wpdb->prefix . 'cc_components';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
            return $wpdb->get_var("SELECT COUNT(*) FROM $table");
        }
        
        return 0;
    }
    
    /**
     * Test connection to central server
     */
    public function testConnection() {
        $test_data = [
            'action' => 'test_connection',
            'domain' => $this->current_domain,
            'timestamp' => time()
        ];
        
        $url = $this->license_server_url . '/wp-json/ccc-central/v1/test';
        
        $response = wp_remote_post($url, [
            'method' => 'POST',
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Custom-Craft-Component/' . get_option('ccc_plugin_version', '2.0')
            ],
            'body' => json_encode($test_data)
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $response->get_error_message()
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && isset($data['success'])) {
            return [
                'success' => true,
                'message' => 'Connection successful: ' . ($data['message'] ?? 'Connected')
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Invalid response from server'
        ];
    }
    
    /**
     * Get license server URL
     */
    public function getLicenseServerUrl() {
        return $this->license_server_url;
    }
    
    /**
     * Set license server URL
     */
    public function setLicenseServerUrl($url) {
        $this->license_server_url = $url;
        update_option('ccc_license_server_url', $url);
    }
}
