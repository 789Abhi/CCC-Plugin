<?php

namespace CCC\Services;

/**
 * Universal Field Access Service
 * Handles PRO field access checking for ALL field types dynamically from API
 */
class UniversalFieldAccessService {
    
    private $license_validator;
    private $manifest_service;
    private $cached_features = null;
    private $cache_duration = 3600; // 1 hour
    
    public function __construct() {
        $this->license_validator = new LicenseValidator();
        $this->manifest_service = new ManifestService();
    }
    
    /**
     * Universal method to check if ANY field type can be accessed
     */
    public function can_access_field($field_type) {
        try {
            // Always get fresh field configuration from API
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
            
            // Always validate license fresh (no caching for dynamic updates)
            $validation = $this->license_validator->validate_license($license_key);
            
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
            error_log('CCC UniversalFieldAccessService: Error checking field access - ' . $e->getMessage());
            return [
                'canAccess' => false,
                'message' => 'Error checking field access: ' . $e->getMessage(),
                'userPlan' => 'free',
                'requiredPlan' => 'free',
                'success' => false,
                'isPro' => false
            ];
        }
    }
    
    /**
     * Render field with PRO styling (50% opacity + PRO tag) for ANY field type
     */
    public function render_pro_field_disabled($field_type, $field_label = '', $field_content = '') {
        $field_names = [
            'text' => 'Text Field',
            'textarea' => 'Text Area Field',
            'image' => 'Image Field',
            'video' => 'Video Field',
            'oembed' => 'O-Embed Field',
            'relationship' => 'Relationship Field',
            'link' => 'Link Field',
            'email' => 'Email Field',
            'number' => 'Number Field',
            'range' => 'Range Field',
            'file' => 'File Field',
            'repeater' => 'Repeater Field',
            'wysiwyg' => 'WYSIWYG Editor',
            'color' => 'Color Field',
            'select' => 'Select Field',
            'checkbox' => 'Checkbox Field',
            'radio' => 'Radio Field',
            'toggle' => 'Toggle Field',
            'gallery' => 'Gallery Field',
            'date' => 'Date Field',
            'date_range' => 'Date Range Field',
            'datetime' => 'Date Time Field',
            'time' => 'Time Field',
            'time_range' => 'Time Range Field',
            'ai_generator' => 'AI Component Generator',
            'conditional_logic' => 'Conditional Logic',
            'custom_validation' => 'Custom Validation',
            'api_integration' => 'API Integration',
            'password' => 'Password Field',
            'user' => 'User Field',
            'taxonomy_term' => 'Taxonomy Term Field'
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
     * Universal method to render ANY field with PRO access check
     */
    public function render_field_with_access_check($field_type, $field_label, $field_content_callback) {
        $access_check = $this->can_access_field($field_type);
        
        if (!$access_check['canAccess']) {
            // Generate field content first
            $field_content = '';
            if (is_callable($field_content_callback)) {
                ob_start();
                call_user_func($field_content_callback);
                $field_content = ob_get_clean();
            }
            
            return $this->render_pro_field_disabled($field_type, $field_label, $field_content);
        }
        
        // Field is accessible, render normally
        if (is_callable($field_content_callback)) {
            ob_start();
            call_user_func($field_content_callback);
            return ob_get_clean();
        }
        
        return '';
    }
    
    /**
     * Get all field types and their access status
     */
    public function get_all_field_access_status() {
        $all_fields = [
            'text', 'textarea', 'image', 'video', 'oembed', 'relationship',
            'link', 'email', 'number', 'range', 'file', 'repeater', 'wysiwyg',
            'color', 'select', 'checkbox', 'radio', 'toggle', 'gallery', 'date',
            'date_range', 'datetime', 'time', 'time_range', 'ai_generator',
            'conditional_logic', 'custom_validation', 'api_integration',
            'password', 'user', 'taxonomy_term'
        ];
        
        $field_status = [];
        
        foreach ($all_fields as $field_type) {
            $access = $this->can_access_field($field_type);
            $field_status[$field_type] = [
                'canAccess' => $access['canAccess'],
                'isPro' => $access['isPro'],
                'requiredPlan' => $access['requiredPlan'],
                'userPlan' => $access['userPlan'],
                'message' => $access['message']
            ];
        }
        
        return $field_status;
    }
    
    /**
     * Clear cache to force fresh API calls
     */
    public function clear_cache() {
        $this->cached_features = null;
        delete_transient('ccc_pro_features_cache');
        delete_transient('ccc_field_config_cache');
    }
}
