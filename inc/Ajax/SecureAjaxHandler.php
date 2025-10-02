<?php

namespace CCC\Ajax;

use CCC\Services\SecureProFieldAccess;

/**
 * Secure AJAX Handler
 * Handles secure license validation and PRO feature access
 */
class SecureAjaxHandler {
    
    private $secure_pro_access;
    
    public function __construct() {
        $this->secure_pro_access = new SecureProFieldAccess();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_ccc_secure_license_validation', [$this, 'handle_secure_license_validation']);
        add_action('wp_ajax_ccc_validate_pro_feature', [$this, 'handle_validate_pro_feature']);
        add_action('wp_ajax_ccc_get_secure_field_access', [$this, 'handle_get_secure_field_access']);
    }
    
    /**
     * Handle secure license validation
     */
    public function handle_secure_license_validation() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ccc_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        $license_key = get_option('ccc_license_key', '');
        $site_url = home_url();
        $fingerprint = sanitize_text_field($_POST['fingerprint'] ?? '');
        
        if (empty($license_key)) {
            wp_send_json_error([
                'message' => 'No license key configured',
                'error_code' => 'NO_LICENSE'
            ]);
        }
        
        // Validate license securely
        $validation = $this->secure_pro_access->validate_license_secure($license_key, $site_url);
        
        if ($validation['valid']) {
            wp_send_json_success([
                'secure_token' => $validation['secure_token'],
                'license' => $validation['license'],
                'features' => $validation['features'],
                'expires_at' => $validation['expires_at']
            ]);
        } else {
            wp_send_json_error([
                'message' => $validation['error'] ?? 'License validation failed',
                'error_code' => 'VALIDATION_FAILED'
            ]);
        }
    }
    
    /**
     * Handle PRO feature validation
     */
    public function handle_validate_pro_feature() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ccc_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        $feature_name = sanitize_text_field($_POST['feature_name'] ?? '');
        $secure_token = sanitize_text_field($_POST['secure_token'] ?? '');
        
        if (empty($feature_name)) {
            wp_send_json_error([
                'message' => 'Feature name is required',
                'error_code' => 'NO_FEATURE'
            ]);
        }
        
        $can_access = $this->secure_pro_access->can_access_pro_feature($feature_name, $secure_token);
        
        wp_send_json_success([
            'can_access' => $can_access,
            'feature' => $feature_name
        ]);
    }
    
    /**
     * Handle secure field access data
     */
    public function handle_get_secure_field_access() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ccc_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        $license_key = get_option('ccc_license_key', '');
        $site_url = home_url();
        
        if (empty($license_key)) {
            wp_send_json_error([
                'message' => 'No license key configured',
                'field_types' => $this->get_free_field_types_only()
            ]);
        }
        
        // Get secure validation
        $validation = $this->secure_pro_access->validate_license_secure($license_key, $site_url);
        
        if ($validation['valid']) {
            $field_types = $this->get_field_types_with_access($validation['features']);
            wp_send_json_success([
                'field_types' => $field_types,
                'secure_token' => $validation['secure_token'],
                'license' => $validation['license'],
                'features' => $validation['features']
            ]);
        } else {
            wp_send_json_error([
                'message' => $validation['error'] ?? 'License validation failed',
                'field_types' => $this->get_free_field_types_only()
            ]);
        }
    }
    
    /**
     * Get field types with access control
     */
    private function get_field_types_with_access($available_features) {
        $all_field_types = [
            // Free fields
            'text' => [
                'name' => 'Text Field',
                'description' => 'Single line text input',
                'icon' => '📝',
                'category' => 'basic',
                'order' => 1,
                'is_pro' => false,
                'available' => true,
                'required_plan' => 'free'
            ],
            'textarea' => [
                'name' => 'Textarea Field',
                'description' => 'Multi-line text input',
                'icon' => '📄',
                'category' => 'basic',
                'order' => 2,
                'is_pro' => false,
                'available' => true,
                'required_plan' => 'free'
            ],
            'email' => [
                'name' => 'Email Field',
                'description' => 'Email input with validation',
                'icon' => '📧',
                'category' => 'basic',
                'order' => 3,
                'is_pro' => false,
                'available' => true,
                'required_plan' => 'free'
            ],
            'number' => [
                'name' => 'Number Field',
                'description' => 'Numeric input',
                'icon' => '🔢',
                'category' => 'basic',
                'order' => 4,
                'is_pro' => false,
                'available' => true,
                'required_plan' => 'free'
            ],
            'link' => [
                'name' => 'Link Field',
                'description' => 'URL input',
                'icon' => '🔗',
                'category' => 'basic',
                'order' => 5,
                'is_pro' => false,
                'available' => true,
                'required_plan' => 'free'
            ],
            'select' => [
                'name' => 'Select Field',
                'description' => 'Dropdown selection',
                'icon' => '📋',
                'category' => 'basic',
                'order' => 6,
                'is_pro' => false,
                'available' => true,
                'required_plan' => 'free'
            ],
            'checkbox' => [
                'name' => 'Checkbox Field',
                'description' => 'Multiple checkbox options',
                'icon' => '☑️',
                'category' => 'basic',
                'order' => 7,
                'is_pro' => false,
                'available' => true,
                'required_plan' => 'free'
            ],
            'radio' => [
                'name' => 'Radio Field',
                'description' => 'Single choice options',
                'icon' => '🔘',
                'category' => 'basic',
                'order' => 8,
                'is_pro' => false,
                'available' => true,
                'required_plan' => 'free'
            ],
            'toggle' => [
                'name' => 'Toggle Field',
                'description' => 'On/off switch',
                'icon' => '🔄',
                'category' => 'basic',
                'order' => 9,
                'is_pro' => false,
                'available' => true,
                'required_plan' => 'free'
            ],
            'color' => [
                'name' => 'Color Field',
                'description' => 'Color picker',
                'icon' => '🎨',
                'category' => 'basic',
                'order' => 10,
                'is_pro' => false,
                'available' => true,
                'required_plan' => 'free'
            ],
            'range' => [
                'name' => 'Range Field',
                'description' => 'Slider input',
                'icon' => '📊',
                'category' => 'basic',
                'order' => 11,
                'is_pro' => false,
                'available' => true,
                'required_plan' => 'free'
            ],
            'date' => [
                'name' => 'Date Field',
                'description' => 'Date picker',
                'icon' => '📅',
                'category' => 'basic',
                'order' => 12,
                'is_pro' => false,
                'available' => true,
                'required_plan' => 'free'
            ],
            'file' => [
                'name' => 'File Field',
                'description' => 'File upload',
                'icon' => '📁',
                'category' => 'basic',
                'order' => 13,
                'is_pro' => false,
                'available' => true,
                'required_plan' => 'free'
            ],
            'wysiwyg' => [
                'name' => 'WYSIWYG Field',
                'description' => 'Rich text editor',
                'icon' => '✏️',
                'category' => 'advanced',
                'order' => 14,
                'is_pro' => false,
                'available' => true,
                'required_plan' => 'free'
            ],
            'oembed' => [
                'name' => 'OEmbed Field',
                'description' => 'Embed external content',
                'icon' => '🎬',
                'category' => 'advanced',
                'order' => 15,
                'is_pro' => false,
                'available' => true,
                'required_plan' => 'free'
            ],
            'relationship' => [
                'name' => 'Relationship Field',
                'description' => 'Link to other posts',
                'icon' => '🔗',
                'category' => 'advanced',
                'order' => 16,
                'is_pro' => false,
                'available' => true,
                'required_plan' => 'free'
            ],
            'image' => [
                'name' => 'Image Field',
                'description' => 'Single image upload',
                'icon' => '🖼️',
                'category' => 'media',
                'order' => 17,
                'is_pro' => false,
                'available' => true,
                'required_plan' => 'free'
            ],
            'video' => [
                'name' => 'Video Field',
                'description' => 'Video upload',
                'icon' => '🎥',
                'category' => 'media',
                'order' => 18,
                'is_pro' => false,
                'available' => true,
                'required_plan' => 'free'
            ],
            'password' => [
                'name' => 'Password Field',
                'description' => 'Password input',
                'icon' => '🔒',
                'category' => 'basic',
                'order' => 19,
                'is_pro' => false,
                'available' => true,
                'required_plan' => 'free'
            ],
            'taxonomy_term' => [
                'name' => 'Taxonomy Term Field',
                'description' => 'Select taxonomy terms',
                'icon' => '🏷️',
                'category' => 'advanced',
                'order' => 20,
                'is_pro' => false,
                'available' => true,
                'required_plan' => 'free'
            ],
            
            // PRO fields
            'repeater' => [
                'name' => 'Repeater Field',
                'description' => 'Repeatable field group',
                'icon' => '🔄',
                'category' => 'pro',
                'order' => 21,
                'is_pro' => true,
                'available' => in_array('repeater', $available_features),
                'required_plan' => 'basic'
            ],
            'gallery' => [
                'name' => 'Gallery Field',
                'description' => 'Multiple image uploads',
                'icon' => '🖼️',
                'category' => 'pro',
                'order' => 22,
                'is_pro' => true,
                'available' => in_array('gallery', $available_features),
                'required_plan' => 'basic'
            ]
        ];
        
        return $all_field_types;
    }
    
    /**
     * Get only free field types (fallback)
     */
    private function get_free_field_types_only() {
        $free_fields = [
            'text', 'textarea', 'email', 'number', 'link', 'select', 'checkbox', 
            'radio', 'toggle', 'color', 'range', 'date', 'file', 'wysiwyg', 
            'oembed', 'relationship', 'image', 'video', 'password', 'taxonomy_term'
        ];
        
        $field_types = [];
        foreach ($free_fields as $field_type) {
            $field_types[$field_type] = [
                'name' => ucfirst(str_replace('_', ' ', $field_type)) . ' Field',
                'description' => 'Basic field type',
                'icon' => '📝',
                'category' => 'basic',
                'order' => 1,
                'is_pro' => false,
                'available' => true,
                'required_plan' => 'free'
            ];
        }
        
        return $field_types;
    }
}
