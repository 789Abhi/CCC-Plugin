<?php

namespace CCC\Services;

/**
 * License Validation Service
 * Handles license key validation with the backend API
 */
class LicenseValidator {
    
    private $api_url;
    private $api_key;
    
    public function __construct() {
        $this->api_url = defined('CCC_LICENSE_API_URL') ? CCC_LICENSE_API_URL : 'https://custom-craft-component-backend.vercel.app/api';
        $this->api_key = defined('CCC_LICENSE_API_KEY') ? CCC_LICENSE_API_KEY : '';
    }
    
    /**
     * Validate a license key with the backend API and get PRO features
     */
    public function validate_license($license_key) {
        if (empty($license_key)) {
            return [
                'valid' => false,
                'message' => 'License key is required'
            ];
        }
        
        $site_url = home_url();
        $site_name = get_bloginfo('name');
        
        // Get plugin version from plugin header
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $plugin_file = CCC_PLUGIN_PATH . 'custom-craft-component.php';
        $plugin_data = get_plugin_data($plugin_file);
        $version = $plugin_data['Version'] ?? (defined('CCC_VERSION') ? CCC_VERSION : '1.0.0');
        
        $wp_version = get_bloginfo('version');
        $php_version = PHP_VERSION;
        
        // Debug logging for version info
        error_log('CCC LicenseValidator: Plugin File: ' . $plugin_file);
        error_log('CCC LicenseValidator: Plugin Data: ' . print_r($plugin_data, true));
        error_log('CCC LicenseValidator: Plugin Version: ' . $version);
        error_log('CCC LicenseValidator: WordPress Version: ' . $wp_version);
        error_log('CCC LicenseValidator: PHP Version: ' . $php_version);
        
        // First validate with licenses endpoint for site tracking
        $license_response = wp_remote_post($this->api_url . '/licenses/validate', [
            'body' => json_encode([
                'licenseKey' => $license_key,
                'siteUrl' => $site_url,
                'siteName' => $site_name,
                'version' => $version,
                'wpVersion' => $wp_version,
                'phpVersion' => $php_version
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => !empty($this->api_key) ? 'Bearer ' . $this->api_key : ''
            ],
            'timeout' => 15
        ]);
        
        // Then get PRO features
        $response = wp_remote_post($this->api_url . '/pro-features/check', [
            'body' => json_encode([
                'licenseKey' => $license_key,
                'siteUrl' => $site_url,
                'siteName' => $site_name
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => !empty($this->api_key) ? 'Bearer ' . $this->api_key : ''
            ],
            'timeout' => 15
        ]);
        
        // Debug logging
        error_log('CCC LicenseValidator: API URL: ' . $this->api_url . '/pro-features/check');
        error_log('CCC LicenseValidator: Request data: ' . json_encode([
            'licenseKey' => $license_key,
            'siteUrl' => $site_url,
            'siteName' => $site_name
        ]));
        
        if (is_wp_error($response)) {
            error_log('CCC LicenseValidator: WP Error: ' . $response->get_error_message());
            return [
                'valid' => false,
                'message' => 'Failed to validate license: ' . $response->get_error_message()
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        error_log('CCC LicenseValidator: Response body: ' . $body);
        
        $data = json_decode($body, true);
        
        if (!$data) {
            error_log('CCC LicenseValidator: Failed to decode JSON response');
            return [
                'valid' => false,
                'message' => 'Invalid response from license server'
            ];
        }
        
        error_log('CCC LicenseValidator: Decoded data: ' . json_encode($data));
        
        return [
            'valid' => $data['success'] ?? false,
            'message' => $data['message'] ?? 'Unknown error',
            'license' => $data['license'] ?? null,
            'proFeatures' => $data['proFeatures'] ?? null
        ];
    }
    
    /**
     * Increment license usage
     */
    public function increment_usage($license_key) {
        if (empty($license_key)) {
            return false;
        }
        
        $response = wp_remote_post($this->api_url . '/licenses/usage', [
            'body' => json_encode(['licenseKey' => $license_key]),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => !empty($this->api_key) ? 'Bearer ' . $this->api_key : ''
            ],
            'timeout' => 10
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return $data['success'] ?? false;
    }
    
    /**
     * Check if license is valid for AI service
     */
    public function can_use_ai_service($license_key) {
        $validation = $this->validate_license($license_key);
        
        if (!$validation['valid']) {
            return false;
        }
        
        // Check if license has remaining usage
        $license = $validation['license'];
        if ($license && $license['usageCount'] >= $license['maxUsage']) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get license status for display
     */
    public function get_license_status($license_key) {
        $validation = $this->validate_license($license_key);
        
        if (!$validation['valid']) {
            return [
                'status' => 'invalid',
                'message' => $validation['message'],
                'can_use_ai' => false
            ];
        }
        
        $license = $validation['license'];
        
        // Handle usage data safely - backend API may not include these fields
        $usage_count = $license['usageCount'] ?? 0;
        $max_usage = $license['maxUsage'] ?? 1; // Default to 1 to avoid division by zero
        $usage_percentage = $max_usage > 0 ? ($usage_count / $max_usage) * 100 : 0;
        
        return [
            'status' => 'valid',
            'message' => 'License is active',
            'can_use_ai' => true,
            'usage_count' => $usage_count,
            'max_usage' => $max_usage,
            'usage_percentage' => $usage_percentage,
            'expires_at' => $license['expiresAt'],
            'plan' => $license['plan']
        ];
    }
    
    /**
     * Register site with license
     */
    public function register_site($license_key, $site_url = null, $site_name = null) {
        if (empty($license_key)) {
            return [
                'success' => false,
                'message' => 'License key is required'
            ];
        }
        
        $site_url = $site_url ?: home_url();
        $site_name = $site_name ?: get_bloginfo('name');
        
        $response = wp_remote_post($this->api_url . '/pro-features/register-site', [
            'body' => json_encode([
                'licenseKey' => $license_key,
                'siteUrl' => $site_url,
                'siteName' => $site_name
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => !empty($this->api_key) ? 'Bearer ' . $this->api_key : ''
            ],
            'timeout' => 10
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Failed to register site: ' . $response->get_error_message()
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data) {
            return [
                'success' => false,
                'message' => 'Invalid response from license server'
            ];
        }
        
        return $data;
    }
    
    /**
     * Check site usage limits
     */
    public function check_site_usage($license_key, $site_url = null) {
        if (empty($license_key)) {
            return [
                'success' => false,
                'message' => 'License key is required'
            ];
        }
        
        $site_url = $site_url ?: home_url();
        
        $response = wp_remote_post($this->api_url . '/pro-features/site-usage', [
            'body' => json_encode([
                'licenseKey' => $license_key,
                'siteUrl' => $site_url
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => !empty($this->api_key) ? 'Bearer ' . $this->api_key : ''
            ],
            'timeout' => 10
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Failed to check site usage: ' . $response->get_error_message()
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data) {
            return [
                'success' => false,
                'message' => 'Invalid response from license server'
            ];
        }
        
        return $data;
    }
    
    /**
     * Check PRO feature access
     */
    public function check_pro_feature_access($license_key, $feature_type) {
        if (empty($license_key) || empty($feature_type)) {
            return [
                'success' => false,
                'canAccess' => false,
                'message' => 'License key and feature type are required'
            ];
        }
        
        $response = wp_remote_post($this->api_url . '/pro-features/check-access', [
            'body' => json_encode([
                'licenseKey' => $license_key,
                'featureType' => $feature_type
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => !empty($this->api_key) ? 'Bearer ' . $this->api_key : ''
            ],
            'timeout' => 10
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'canAccess' => false,
                'message' => 'Failed to check PRO access: ' . $response->get_error_message()
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data) {
            return [
                'success' => false,
                'canAccess' => false,
                'message' => 'Invalid response from license server'
            ];
        }
        
        return $data;
    }
}
