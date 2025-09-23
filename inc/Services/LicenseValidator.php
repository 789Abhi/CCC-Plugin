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
        $this->api_url = defined('CCC_LICENSE_API_URL') ? CCC_LICENSE_API_URL : 'https://api.customcraftcomponents.com/api';
        $this->api_key = defined('CCC_LICENSE_API_KEY') ? CCC_LICENSE_API_KEY : '';
    }
    
    /**
     * Validate a license key with the backend API
     */
    public function validate_license($license_key) {
        if (empty($license_key)) {
            return [
                'valid' => false,
                'message' => 'License key is required'
            ];
        }
        
        $response = wp_remote_post($this->api_url . '/licenses/validate', [
            'body' => json_encode(['licenseKey' => $license_key]),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => !empty($this->api_key) ? 'Bearer ' . $this->api_key : ''
            ],
            'timeout' => 10
        ]);
        
        if (is_wp_error($response)) {
            return [
                'valid' => false,
                'message' => 'Failed to validate license: ' . $response->get_error_message()
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data) {
            return [
                'valid' => false,
                'message' => 'Invalid response from license server'
            ];
        }
        
        return [
            'valid' => $data['success'] ?? false,
            'message' => $data['message'] ?? 'Unknown error',
            'license' => $data['license'] ?? null
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
        $usage_percentage = ($license['usageCount'] / $license['maxUsage']) * 100;
        
        return [
            'status' => 'valid',
            'message' => 'License is active',
            'can_use_ai' => true,
            'usage_count' => $license['usageCount'],
            'max_usage' => $license['maxUsage'],
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
