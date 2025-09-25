<?php

namespace CCC\Services;

/**
 * PRO Field Access Service
 * Handles PRO field access checking and restrictions for existing field classes
 */
class ProFieldAccessService {
    
    private $license_validator;
    private $manifest_service;
    private $cached_features = null;
    private $cache_duration = 3600; // 1 hour
    
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
                return [
                    'canAccess' => true,
                    'message' => 'Field is available',
                    'userPlan' => 'free',
                    'requiredPlan' => 'free',
                    'success' => true,
                    'isPro' => false
                ];
            }
            
            $license_key = get_option('ccc_license_key', '');
            
            if (empty($license_key)) {
                return [
                    'canAccess' => false,
                    'message' => 'No license key found. This field requires a PRO license.',
                    'userPlan' => 'free',
                    'requiredPlan' => $field_config['required_plan'],
                    'success' => true,
                    'isPro' => true
                ];
            }
            
            // Check cached license status first
            $cached_status = get_transient('ccc_license_status_' . md5($license_key));
            if ($cached_status !== false) {
                $validation = $cached_status;
            } else {
                // Validate license with API
                $validation = $this->license_validator->validate_license($license_key);
                
                // Cache the result for 5 minutes
                if ($validation['valid']) {
                    set_transient('ccc_license_status_' . md5($license_key), $validation, 300);
                }
            }
            
            // Log the validation result for debugging
            error_log('CCC ProFieldAccessService: License validation result for ' . $field_type . ': ' . json_encode($validation));
            
            if (!$validation['valid']) {
                return [
                    'canAccess' => false,
                    'message' => $validation['message'],
                    'userPlan' => 'free',
                    'requiredPlan' => $field_config['required_plan'],
                    'success' => true,
                    'isPro' => true
                ];
            }
            
            $this->cached_features = $validation['proFeatures'];
            
            // Check if field is available based on license plan
            if ($this->cached_features && isset($this->cached_features['fieldTypes'][$field_type])) {
                $field_availability = $this->cached_features['fieldTypes'][$field_type];
                
                if ($field_availability['available']) {
                    return [
                        'canAccess' => true,
                        'message' => 'Access granted',
                        'userPlan' => $this->cached_features['license']['plan'] ?? 'free',
                        'requiredPlan' => $field_config['required_plan'],
                        'success' => true,
                        'isPro' => true
                    ];
                } else {
                    return [
                        'canAccess' => false,
                        'message' => sprintf(
                            'This field requires a %s plan or higher. Your current plan: %s',
                            ucfirst($field_config['required_plan']),
                            ucfirst($this->cached_features['license']['plan'] ?? 'free')
                        ),
                        'userPlan' => $this->cached_features['license']['plan'] ?? 'free',
                        'requiredPlan' => $field_config['required_plan'],
                        'success' => true,
                        'isPro' => true
                    ];
                }
            }
            
            // Fallback: check plan hierarchy
            $user_plan = $this->cached_features['license']['plan'] ?? 'free';
            $required_plan = $field_config['required_plan'];
            
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
            
            return [
                'canAccess' => $can_access,
                'message' => $message,
                'userPlan' => $user_plan,
                'requiredPlan' => $required_plan,
                'success' => true,
                'isPro' => true
            ];
            
        } catch (\Exception $e) {
            error_log('CCC ProFieldAccessService: Error checking field access - ' . $e->getMessage());
            return [
                'canAccess' => false,
                'message' => 'Error checking field access',
                'userPlan' => 'free',
                'requiredPlan' => 'free',
                'success' => false,
                'isPro' => false
            ];
        }
    }
    
    /**
     * Render PRO upgrade notice for field
     */
    public function render_pro_upgrade_notice($field_type, $field_label = '') {
        $field_names = [
            'repeater' => 'Repeater Field',
            'gallery' => 'Gallery Field',
            'date_range' => 'Date Range Field',
            'time_range' => 'Time Range Field',
            'ai_generator' => 'AI Component Generator',
            'conditional_logic' => 'Conditional Logic',
            'custom_validation' => 'Custom Validation',
            'api_integration' => 'API Integration'
        ];
        
        $field_name = $field_label ?: (isset($field_names[$field_type]) ? $field_names[$field_type] : ucfirst($field_type));
        
        ob_start();
        ?>
        <div class="ccc-pro-field-notice">
            <div class="ccc-pro-badge">PRO</div>
            <h4><?php echo esc_html($field_name); ?></h4>
            <p>This field requires a PRO license. <a href="#" class="ccc-upgrade-link">Upgrade now</a></p>
        </div>
        
        <style>
        .ccc-pro-field-notice {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: 2px solid #667eea;
            border-radius: 12px;
            padding: 20px;
            margin: 15px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .ccc-pro-field-notice::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }
        
        .ccc-pro-badge {
            background: #ff6b6b;
            color: white;
            font-weight: bold;
            font-size: 12px;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .ccc-pro-field-notice h4 {
            color: white;
            margin: 0 0 10px 0;
            font-size: 18px;
            font-weight: 600;
        }
        
        .ccc-pro-field-notice p {
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
            font-size: 14px;
        }
        
        .ccc-upgrade-link {
            color: #ffd93d;
            text-decoration: none;
            font-weight: bold;
            border-bottom: 1px solid #ffd93d;
            transition: all 0.3s ease;
        }
        
        .ccc-upgrade-link:hover {
            color: white;
            border-bottom-color: white;
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render field with PRO styling (50% opacity + PRO tag)
     */
    public function render_pro_field_disabled($field_type, $field_label = '', $field_content = '') {
        $field_names = [
            'repeater' => 'Repeater Field',
            'gallery' => 'Gallery Field',
            'date_range' => 'Date Range Field',
            'time_range' => 'Time Range Field',
            'ai_generator' => 'AI Component Generator',
            'conditional_logic' => 'Conditional Logic',
            'custom_validation' => 'Custom Validation',
            'api_integration' => 'API Integration'
        ];
        
        $field_name = $field_label ?: (isset($field_names[$field_type]) ? $field_names[$field_type] : ucfirst($field_type));
        
        ob_start();
        ?>
        <div class="ccc-field ccc-pro-field-disabled" data-field-type="<?php echo esc_attr($field_type); ?>">
            <div class="ccc-field-header">
                <label class="ccc-field-label">
                    <?php echo esc_html($field_name); ?>
                    <span class="ccc-pro-tag">PRO</span>
                </label>
                <div class="ccc-pro-overlay">
                    <div class="ccc-pro-overlay-content">
                        <div class="ccc-pro-icon">🔒</div>
                        <p>This field requires a PRO license</p>
                        <a href="#" class="ccc-upgrade-button">Upgrade Now</a>
                    </div>
                </div>
            </div>
            <div class="ccc-field-content">
                <?php echo $field_content; ?>
            </div>
        </div>
        
        <style>
        .ccc-pro-field-disabled {
            position: relative;
            opacity: 0.5;
            pointer-events: none;
            border: 2px dashed #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            background: #f9fafb;
        }
        
        .ccc-field-header {
            position: relative;
            margin-bottom: 10px;
        }
        
        .ccc-field-label {
            font-weight: 600;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .ccc-pro-tag {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            font-size: 10px;
            font-weight: bold;
            padding: 2px 8px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(255, 107, 107, 0.3);
        }
        
        .ccc-pro-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            backdrop-filter: blur(2px);
        }
        
        .ccc-pro-overlay-content {
            text-align: center;
            padding: 20px;
        }
        
        .ccc-pro-icon {
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .ccc-pro-overlay-content p {
            margin: 0 0 12px 0;
            color: #6b7280;
            font-size: 14px;
        }
        
        .ccc-upgrade-button {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3);
        }
        
        .ccc-upgrade-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.4);
        }
        
        .ccc-field-content {
            opacity: 0.3;
        }
        </style>
        <?php
        return ob_get_clean();
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
     * Clear cached features (useful when license changes)
     */
    public function clear_cache() {
        $this->cached_features = null;
        delete_transient('ccc_pro_features_cache');
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
     * Get all available field types based on license
     */
    public function get_available_field_types() {
        $all_fields = [
            'text', 'textarea', 'image', 'video', 'oembed', 'relationship',
            'link', 'email', 'number', 'range', 'file', 'wysiwyg',
            'color', 'select', 'checkbox', 'radio', 'toggle', 'date',
            'datetime', 'time', 'repeater', 'gallery', 'date_range',
            'time_range', 'ai_generator', 'conditional_logic',
            'custom_validation', 'api_integration'
        ];
        
        $available_fields = [];
        
        foreach ($all_fields as $field_type) {
            $access = $this->can_access_field($field_type);
            $available_fields[$field_type] = [
                'available' => $access['canAccess'],
                'is_pro' => $access['isPro'],
                'required_plan' => $access['requiredPlan'],
                'message' => $access['message'],
                'user_plan' => $access['userPlan']
            ];
        }
        
        return $available_fields;
    }
    
    /**
     * Get field access data for frontend JavaScript
     */
    public function get_field_access_data() {
        $license_key = get_option('ccc_license_key', '');
        $license_status = $this->get_license_status();
        
        // Get the actual user plan from license status
        $user_plan = $license_status['plan'] ?? 'free';
        
        // Force refresh field configuration to get latest changes
        $field_configurations = $this->manifest_service->refresh_field_configuration();
        
        // Get available field types based on license (only core field types, not variations or special features)
        $field_types = [];
        foreach ($field_configurations as $field_type => $config) {
            // Skip special features - only include actual field types
            if ($config['category'] === 'special') {
                continue;
            }
            
            // Skip redundant date field variations as they are sub-components of the main 'date' field
            if (in_array($field_type, ['date_range', 'time_range', 'datetime', 'time'])) {
                continue;
            }
            
            $access = $this->can_access_field($field_type);
            $field_types[$field_type] = [
                'available' => $access['canAccess'],
                'is_pro' => $config['is_pro'],
                'required_plan' => $config['required_plan'],
                'message' => $access['message'],
                'user_plan' => $user_plan,
                'name' => $config['name'] ?? ucfirst($field_type),
                'description' => $config['description'] ?? '',
                'icon' => $config['icon'] ?? '📝',
                'category' => $config['category'] ?? 'basic',
                'order' => $config['order'] ?? 1
            ];
        }
        
        return [
            'hasLicense' => !empty($license_key),
            'licenseStatus' => $license_status,
            'fieldTypes' => $field_types,
            'proFields' => array_filter($field_types, function($field) {
                return $field['is_pro'] && $field['category'] !== 'special';
            })
        ];
    }
}
