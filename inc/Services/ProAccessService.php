<?php

namespace CCC\Services;

/**
 * PRO Access Service
 * Handles PRO feature access checking and restrictions
 */
class ProAccessService {
    
    private $license_validator;
    private $manifest_service;
    
    public function __construct() {
        $this->license_validator = new LicenseValidator();
        $this->manifest_service = new ManifestService();
    }
    
    /**
     * Check if user can access a specific field type
     */
    public function can_access_field($field_type) {
        try {
            // Get field configuration from manifest
            $field_config = $this->manifest_service->get_field_config($field_type);
            
            // If field is not PRO, allow access
            if (!$field_config['is_pro']) {
                return $this->get_access_result(true, 'Field is available', 'free');
            }
            
            $license_key = get_option('ccc_license_key', '');
            
            if (empty($license_key)) {
                return $this->get_access_result(false, 'No license key found', 'free');
            }
            
            // Validate license
            $validation = $this->license_validator->validate_license($license_key);
            
            if (!$validation['valid']) {
                return $this->get_access_result(false, $validation['message'], 'free');
            }
            
            $user_plan = $validation['license']['plan'] ?? 'free';
            $required_plan = $field_config['required_plan'];
            
            // Check plan hierarchy
            $plan_hierarchy = ['free' => 0, 'basic' => 1, 'pro' => 2, 'max' => 3];
            $user_plan_level = $plan_hierarchy[$user_plan] ?? 0;
            $required_plan_level = $plan_hierarchy[$required_plan] ?? 0;
            
            $can_access = $user_plan_level >= $required_plan_level;
            
            if (!$can_access) {
                $message = sprintf(
                    'This field requires a %s plan or higher. Your current plan: %s',
                    ucfirst($required_plan),
                    ucfirst($user_plan)
                );
            } else {
                $message = 'Access granted';
            }
            
            return $this->get_access_result($can_access, $message, $user_plan, $required_plan);
            
        } catch (\Exception $e) {
            error_log('CCC ProAccessService: Error checking field access - ' . $e->getMessage());
            return $this->get_access_result(false, 'Error checking field access', 'free');
        }
    }
    
    /**
     * Check if user can access AI features
     */
    public function can_access_ai() {
        $license_key = get_option('ccc_license_key', '');
        
        if (empty($license_key)) {
            return $this->get_access_result(false, 'No license key found', 'free');
        }
        
        return $this->license_validator->can_use_ai_service($license_key);
    }
    
    /**
     * Get site registration status
     */
    public function get_site_registration_status() {
        $license_key = get_option('ccc_license_key', '');
        
        if (empty($license_key)) {
            return [
                'success' => false,
                'message' => 'No license key found',
                'isRegistered' => false,
                'canRegister' => false
            ];
        }
        
        $site_usage = $this->license_validator->check_site_usage($license_key);
        
        if (!$site_usage['success']) {
            return [
                'success' => false,
                'message' => $site_usage['message'],
                'isRegistered' => false,
                'canRegister' => false
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Site status checked',
            'isRegistered' => $site_usage['isSiteRegistered'] ?? false,
            'canRegister' => $site_usage['canAddSite'] ?? false,
            'maxSites' => $site_usage['maxSites'] ?? 1,
            'currentSites' => $site_usage['currentSites'] ?? 0,
            'plan' => $site_usage['plan'] ?? 'free'
        ];
    }
    
    /**
     * Register current site with license
     */
    public function register_current_site() {
        $license_key = get_option('ccc_license_key', '');
        
        if (empty($license_key)) {
            return [
                'success' => false,
                'message' => 'No license key found'
            ];
        }
        
        $site_url = home_url();
        $site_name = get_bloginfo('name');
        
        return $this->license_validator->register_site($license_key, $site_url, $site_name);
    }
    
    /**
     * Get all PRO fields configuration
     */
    public function get_pro_fields_config() {
        return $this->manifest_service->get_all_field_configurations();
    }
    
    /**
     * Get license status
     */
    public function get_license_status() {
        $license_key = get_option('ccc_license_key', '');
        
        if (empty($license_key)) {
            return [
                'status' => 'no_license',
                'message' => 'No license key found',
                'plan' => 'free'
            ];
        }
        
        return $this->license_validator->get_license_status($license_key);
    }
    
    /**
     * Check if field should show PRO indicator
     */
    public function should_show_pro_indicator($field_type) {
        $field_config = $this->manifest_service->get_field_config($field_type);
        return $field_config['is_pro'];
    }
    
    /**
     * Get PRO upgrade message for field
     */
    public function get_pro_upgrade_message($field_type) {
        $field_config = $this->manifest_service->get_field_config($field_type);
        
        if (!$field_config['is_pro']) {
            return null;
        }
        
        $required_plan = $field_config['required_plan'] ?? 'basic';
        
        return sprintf(
            'Upgrade to %s plan to use this field',
            ucfirst($required_plan)
        );
    }
    
    /**
     * Helper method to format access result
     */
    private function get_access_result($can_access, $message, $user_plan, $required_plan = null) {
        return [
            'canAccess' => $can_access,
            'message' => $message,
            'userPlan' => $user_plan,
            'requiredPlan' => $required_plan,
            'success' => true
        ];
    }
    
    /**
     * Get all field configurations from manifest
     */
    public function get_all_field_configurations() {
        return $this->manifest_service->get_all_field_configurations();
    }
    
    /**
     * Refresh field configuration from manifest
     */
    public function refresh_field_configuration() {
        return $this->manifest_service->refresh_field_configuration();
    }
    
    /**
     * Get manifest info
     */
    public function get_manifest_info() {
        return $this->manifest_service->get_manifest_info();
    }
    
    /**
     * Schedule automatic sync
     */
    public function schedule_auto_sync() {
        $this->manifest_service->schedule_auto_sync();
    }
    
    /**
     * Unschedule automatic sync
     */
    public function unschedule_auto_sync() {
        $this->manifest_service->unschedule_auto_sync();
    }
    
    /**
     * Get plan comparison data
     */
    public function get_plan_comparison() {
        return [
            'free' => [
                'name' => 'Free',
                'sites' => 1,
                'price' => 0,
                'features' => ['Basic fields (text, textarea, number, email, url, date)']
            ],
            'basic' => [
                'name' => 'Basic',
                'sites' => 1,
                'price' => 39,
                'features' => [
                    'All free features',
                    'Date Range Picker',
                    'Gallery Field',
                    'File Upload Field',
                    'AI Component Generator'
                ]
            ],
            'pro' => [
                'name' => 'Pro',
                'sites' => 15,
                'price' => 99,
                'features' => [
                    'All basic features',
                    'Relationship Field',
                    'Repeater Field',
                    'Advanced Date Features'
                ]
            ],
            'max' => [
                'name' => 'Max',
                'sites' => 'Unlimited',
                'price' => 199,
                'features' => [
                    'All pro features',
                    'Conditional Logic',
                    'Custom Validation Rules',
                    'API Integration'
                ]
            ]
        ];
    }
}
