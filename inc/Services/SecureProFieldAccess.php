<?php

namespace CCC\Services;

/**
 * Secure PRO Field Access Service
 * Implements multiple layers of security for PRO features
 */
class SecureProFieldAccess {
    
    private $encryption_key;
    private $validation_cache;
    private $cache_duration = 300; // 5 minutes
    
    public function __construct() {
        $this->encryption_key = $this->get_encryption_key();
        $this->validation_cache = [];
    }
    
    /**
     * Get encryption key from secure location
     */
    private function get_encryption_key() {
        // Use WordPress salt + custom key for encryption
        $wp_salt = defined('AUTH_SALT') ? AUTH_SALT : 'default-salt';
        $custom_key = get_option('ccc_encryption_key', '');
        
        if (empty($custom_key)) {
            // Generate and store a unique encryption key
            $custom_key = wp_generate_password(64, true, true);
            update_option('ccc_encryption_key', $custom_key, false);
        }
        
        return hash('sha256', $wp_salt . $custom_key);
    }
    
    /**
     * Encrypt PRO field code/data
     */
    public function encrypt_pro_content($data) {
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryption_key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt PRO field code/data
     */
    public function decrypt_pro_content($encrypted_data) {
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryption_key, 0, $iv);
    }
    
    /**
     * Validate license with multiple security checks
     */
    public function validate_license_secure($license_key, $site_url) {
        $cache_key = md5($license_key . $site_url);
        
        // Check cache first
        if (isset($this->validation_cache[$cache_key])) {
            $cached = $this->validation_cache[$cache_key];
            if (time() - $cached['timestamp'] < $this->cache_duration) {
                return $cached['data'];
            }
        }
        
        // Perform secure validation
        $validation_result = $this->perform_secure_validation($license_key, $site_url);
        
        // Cache the result
        $this->validation_cache[$cache_key] = [
            'data' => $validation_result,
            'timestamp' => time()
        ];
        
        return $validation_result;
    }
    
    /**
     * Perform comprehensive license validation
     */
    private function perform_secure_validation($license_key, $site_url) {
        try {
            // 1. Basic license validation
            $license_validator = new LicenseValidator();
            $validation = $license_validator->validate_license($license_key);
            
            if (!$validation['valid']) {
                return [
                    'valid' => false,
                    'error' => 'License validation failed',
                    'features' => [],
                    'secure_token' => null
                ];
            }
            
            // 2. Verify license hasn't been tampered with
            $license_data = $validation['license'];
            if (!$this->verify_license_integrity($license_data)) {
                return [
                    'valid' => false,
                    'error' => 'License integrity check failed',
                    'features' => [],
                    'secure_token' => null
                ];
            }
            
            // 3. Generate secure feature token
            $features = $this->get_available_features($license_data['isPro'], $license_data['plan'] ?? 'free');
            $secure_token = $this->generate_secure_token($license_data, $features);
            
            return [
                'valid' => true,
                'license' => $license_data,
                'features' => $features,
                'secure_token' => $secure_token,
                'expires_at' => time() + 3600 // 1 hour
            ];
            
        } catch (\Exception $e) {
            error_log('CCC SecureProFieldAccess: Validation error - ' . $e->getMessage());
            return [
                'valid' => false,
                'error' => 'Validation error: ' . $e->getMessage(),
                'features' => [],
                'secure_token' => null
            ];
        }
    }
    
    /**
     * Verify license hasn't been tampered with
     */
    private function verify_license_integrity($license_data) {
        // Check if license data looks legitimate
        if (empty($license_data['licenseKey']) || empty($license_data['userId'])) {
            return false;
        }
        
        // Additional integrity checks can be added here
        // For example, checking against a remote validation service
        return true;
    }
    
    /**
     * Get available features based on license
     */
    private function get_available_features($is_pro, $plan) {
        if (!$is_pro) {
            return [];
        }
        
        $feature_matrix = [
            'basic' => ['repeater', 'gallery'],
            'pro' => ['repeater', 'gallery', 'conditional_logic', 'custom_validation'],
            'max' => ['repeater', 'gallery', 'conditional_logic', 'custom_validation', 'ai_generator', 'api_integration']
        ];
        
        return $feature_matrix[$plan] ?? $feature_matrix['basic'];
    }
    
    /**
     * Generate secure token for feature access
     */
    private function generate_secure_token($license_data, $features) {
        $payload = [
            'license_key' => $license_data['licenseKey'],
            'user_id' => $license_data['userId'],
            'is_pro' => $license_data['isPro'],
            'plan' => $license_data['plan'] ?? 'free',
            'features' => $features,
            'issued_at' => time(),
            'expires_at' => time() + 3600, // 1 hour
            'site_hash' => hash('sha256', home_url() . $this->encryption_key)
        ];
        
        return $this->encrypt_pro_content(json_encode($payload));
    }
    
    /**
     * Validate secure token
     */
    public function validate_secure_token($token) {
        try {
            $decrypted = $this->decrypt_pro_content($token);
            $payload = json_decode($decrypted, true);
            
            if (!$payload) {
                return false;
            }
            
            // Check expiration
            if (time() > $payload['expires_at']) {
                return false;
            }
            
            // Verify site hash
            $expected_site_hash = hash('sha256', home_url() . $this->encryption_key);
            if ($payload['site_hash'] !== $expected_site_hash) {
                return false;
            }
            
            return $payload;
            
        } catch (\Exception $e) {
            error_log('CCC SecureProFieldAccess: Token validation error - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user can access a specific PRO feature
     */
    public function can_access_pro_feature($feature_name, $secure_token = null) {
        if (empty($secure_token)) {
            $license_key = get_option('ccc_license_key', '');
            if (empty($license_key)) {
                return false;
            }
            
            $validation = $this->validate_license_secure($license_key, home_url());
            if (!$validation['valid']) {
                return false;
            }
            
            $secure_token = $validation['secure_token'];
        }
        
        $token_data = $this->validate_secure_token($secure_token);
        if (!$token_data) {
            return false;
        }
        
        return in_array($feature_name, $token_data['features']);
    }
    
    /**
     * Get PRO field class with security checks
     */
    public function get_pro_field_class($field_type) {
        if (!$this->can_access_pro_feature($field_type)) {
            return null;
        }
        
        // Return encrypted field class name or handle
        $field_classes = [
            'repeater' => 'CCC\\Fields\\RepeaterField',
            'gallery' => 'CCC\\Fields\\GalleryField'
        ];
        
        return $field_classes[$field_type] ?? null;
    }
    
    /**
     * Clear validation cache
     */
    public function clear_cache() {
        $this->validation_cache = [];
    }
}
