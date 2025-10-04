<?php

namespace CCC\Services;

/**
 * Manifest Service
 * Handles fetching field configuration from the backend API manifest
 */
class ManifestService {
    
    private $api_url;
    private $cache_key = 'ccc_field_config_cache';
    private $cache_duration = 300; // 5 minutes (reduced from 1 hour for faster sync)
    
    public function __construct() {
        $this->api_url = defined('CCC_LICENSE_API_URL') ? CCC_LICENSE_API_URL : 'https://custom-craft-component-backend.vercel.app/api';
    }
    
    /**
     * Fetch field configuration from backend API
     */
    public function fetch_field_configuration() {
        try {
            $plugin_version = defined('CCC_VERSION') ? CCC_VERSION : get_option('ccc_plugin_version', '1.0.0');
            
            $response = wp_remote_get($this->api_url . '/pro-features/config', [
                'timeout' => 15,
                'headers' => [
                    'User-Agent' => 'Custom-Craft-Component-Plugin/' . $plugin_version,
                    'Accept' => 'application/json',
                    'X-Plugin-Version' => $plugin_version
                ],
                'body' => [
                    'version' => $plugin_version
                ]
            ]);
            
            if (is_wp_error($response)) {
                error_log('CCC ManifestService: Failed to fetch config - ' . $response->get_error_message());
                return $this->get_default_field_configuration();
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!$data || !$data['success']) {
                error_log('CCC ManifestService: Invalid config response - ' . $body);
                return $this->get_default_field_configuration();
            }
            
            // Convert the backend API response format to the expected format
            $field_configuration = [];
            
            // Process field types from backend API
            if (isset($data['fieldTypes'])) {
                foreach ($data['fieldTypes'] as $fieldType => $config) {
                    $field_configuration[$fieldType] = [
                        'required_plan' => $config['requiredPlan'] ?? 'free',
                        'is_pro' => $config['isPro'] ?? false,
                        'description' => $config['description'] ?? '',
                        'name' => ucfirst($fieldType), // Generate name from field type
                        'icon' => '📝', // Default icon
                        'category' => 'basic', // Default category
                        'order' => 1, // Default order
                        'version' => $config['version'] ?? '1.0.0',
                        'min_plugin_version' => $config['minPluginVersion'] ?? '1.0.0',
                        'max_plugin_version' => $config['maxPluginVersion'] ?? null,
                        'effective_date' => $config['effectiveDate'] ?? null,
                        'compatible_version' => $data['compatibleVersion'] ?? $plugin_version
                    ];
                }
            }
            
            // Process special features from backend API
            if (isset($data['specialFeatures'])) {
                foreach ($data['specialFeatures'] as $featureType => $config) {
                    $field_configuration[$featureType] = [
                        'required_plan' => $config['requiredPlan'] ?? 'free',
                        'is_pro' => $config['isPro'] ?? false,
                        'description' => $config['description'] ?? '',
                        'name' => ucfirst($featureType), // Generate name from feature type
                        'icon' => '⭐', // Default icon for special features
                        'category' => 'special',
                        'order' => 999, // Default order for special features
                        'version' => $config['version'] ?? '1.0.0',
                        'min_plugin_version' => $config['minPluginVersion'] ?? '1.0.0',
                        'max_plugin_version' => $config['maxPluginVersion'] ?? null,
                        'effective_date' => $config['effectiveDate'] ?? null,
                        'compatible_version' => $data['compatibleVersion'] ?? $plugin_version
                    ];
                }
            }
            
            error_log('CCC ManifestService: Successfully fetched ' . count($field_configuration) . ' field configurations from backend API');
            return !empty($field_configuration) ? $field_configuration : $this->get_default_field_configuration();
            
        } catch (\Exception $e) {
            error_log('CCC ManifestService: Exception fetching config - ' . $e->getMessage());
            return $this->get_default_field_configuration();
        }
    }
    
    /**
     * Get field configuration (hardcoded - no API dependency)
     */
    public function get_field_configuration() {
        // Always return hardcoded configuration - no API calls
        return $this->get_default_field_configuration();
    }
    
    /**
     * Force refresh field configuration (hardcoded - no API dependency)
     */
    public function refresh_field_configuration() {
        // Clear cache
        delete_transient($this->cache_key);
        
        // Return hardcoded configuration
        $config = $this->get_default_field_configuration();
        
        // Update the stored configuration
        update_option('ccc_field_configuration', $config);
        
        return $config;
    }
    
    /**
     * Get default field configuration (hardcoded - no API dependency)
     */
    private function get_default_field_configuration() {
        return [
            // Basic fields (free)
            'text' => [
                'required_plan' => 'free', 
                'is_pro' => false,
                'name' => 'Text Field',
                'description' => 'Single line text input',
                'icon' => '📝',
                'category' => 'basic',
                'order' => 1
            ],
            'textarea' => [
                'required_plan' => 'free', 
                'is_pro' => false,
                'name' => 'Text Area',
                'description' => 'Multi-line text input',
                'icon' => '📄',
                'category' => 'basic',
                'order' => 2
            ],
            'email' => [
                'required_plan' => 'free', 
                'is_pro' => false,
                'name' => 'Email Field',
                'description' => 'Email input with validation',
                'icon' => '📧',
                'category' => 'basic',
                'order' => 3
            ],
            'number' => [
                'required_plan' => 'free', 
                'is_pro' => false,
                'name' => 'Number Field',
                'description' => 'Numeric input',
                'icon' => '🔢',
                'category' => 'basic',
                'order' => 4
            ],
            'link' => [
                'required_plan' => 'free', 
                'is_pro' => false,
                'name' => 'Link Field',
                'description' => 'URL input',
                'icon' => '🔗',
                'category' => 'basic',
                'order' => 5
            ],
            'select' => [
                'required_plan' => 'free', 
                'is_pro' => false,
                'name' => 'Select Field',
                'description' => 'Dropdown selection',
                'icon' => '📋',
                'category' => 'basic',
                'order' => 6
            ],
            'checkbox' => [
                'required_plan' => 'free', 
                'is_pro' => false,
                'name' => 'Checkbox Field',
                'description' => 'Multiple checkbox options',
                'icon' => '☑️',
                'category' => 'basic',
                'order' => 7
            ],
            'radio' => [
                'required_plan' => 'free', 
                'is_pro' => false,
                'name' => 'Radio Field',
                'description' => 'Single choice options',
                'icon' => '🔘',
                'category' => 'basic',
                'order' => 8
            ],
            'toggle' => [
                'required_plan' => 'free', 
                'is_pro' => false,
                'name' => 'Toggle Field',
                'description' => 'On/off switch',
                'icon' => '🔀',
                'category' => 'basic',
                'order' => 9
            ],
            'color' => [
                'required_plan' => 'free', 
                'is_pro' => false,
                'name' => 'Color Field',
                'description' => 'Color picker',
                'icon' => '🎨',
                'category' => 'basic',
                'order' => 10
            ],
            'range' => [
                'required_plan' => 'free', 
                'is_pro' => false,
                'name' => 'Range Field',
                'description' => 'Slider input',
                'icon' => '📊',
                'category' => 'basic',
                'order' => 11
            ],
            'date' => [
                'required_plan' => 'free', 
                'is_pro' => false,
                'name' => 'Date Field',
                'description' => 'Date picker',
                'icon' => '📅',
                'category' => 'basic',
                'order' => 12
            ],
            'file' => [
                'required_plan' => 'free', 
                'is_pro' => false,
                'name' => 'File Field',
                'description' => 'File upload',
                'icon' => '📁',
                'category' => 'media',
                'order' => 13
            ],
            'wysiwyg' => [
                'required_plan' => 'free', 
                'is_pro' => false,
                'name' => 'WYSIWYG Editor',
                'description' => 'Rich text editor',
                'icon' => '✏️',
                'category' => 'advanced',
                'order' => 14
            ],
            'oembed' => [
                'required_plan' => 'free', 
                'is_pro' => false,
                'name' => 'oEmbed Field',
                'description' => 'Embed external content',
                'icon' => '🔗',
                'category' => 'advanced',
                'order' => 15
            ],
            'relationship' => [
                'required_plan' => 'free', 
                'is_pro' => false,
                'name' => 'Relationship Field',
                'description' => 'Link to other posts',
                'icon' => '🔗',
                'category' => 'advanced',
                'order' => 16
            ],
            'image' => [
                'required_plan' => 'free', 
                'is_pro' => false,
                'name' => 'Image Field',
                'description' => 'Single image upload',
                'icon' => '🖼️',
                'category' => 'media',
                'order' => 17
            ],
            'video' => [
                'required_plan' => 'free', 
                'is_pro' => false,
                'name' => 'Video Field',
                'description' => 'Video upload',
                'icon' => '🎥',
                'category' => 'media',
                'order' => 18
            ],
            'password' => [
                'required_plan' => 'free', 
                'is_pro' => false,
                'name' => 'Password Field',
                'description' => 'Password input',
                'icon' => '🔒',
                'category' => 'basic',
                'order' => 19
            ],
            
            // PRO fields (require license)
            'repeater' => [
                'required_plan' => 'basic', 
                'is_pro' => true,
                'name' => 'Repeater Field',
                'description' => 'Repeatable field group',
                'icon' => '🔄',
                'category' => 'pro',
                'order' => 21
            ],
            'gallery' => [
                'required_plan' => 'basic', 
                'is_pro' => true,
                'name' => 'Gallery Field',
                'description' => 'Multiple image uploads',
                'icon' => '🖼️',
                'category' => 'pro',
                'order' => 22
            ],
            
            // Special features (not field types - excluded from dropdown)
            'ai_generator' => [
                'required_plan' => 'max', 
                'is_pro' => true,
                'name' => 'AI Component Generator',
                'description' => 'Generate components with AI',
                'icon' => '🤖',
                'category' => 'special',
                'order' => 999
            ],
            'conditional_logic' => [
                'required_plan' => 'max', 
                'is_pro' => true,
                'name' => 'Conditional Logic',
                'description' => 'Show/hide fields based on conditions',
                'icon' => '⚡',
                'category' => 'special',
                'order' => 999
            ],
            'custom_validation' => [
                'required_plan' => 'max', 
                'is_pro' => true,
                'name' => 'Custom Validation',
                'description' => 'Custom field validation rules',
                'icon' => '✅',
                'category' => 'special',
                'order' => 999
            ],
            'api_integration' => [
                'required_plan' => 'max', 
                'is_pro' => true,
                'name' => 'API Integration',
                'description' => 'Connect to external APIs',
                'icon' => '🔌',
                'category' => 'special',
                'order' => 999
            ]
        ];
    }
    
    /**
     * Check if field is PRO
     */
    public function is_field_pro($field_type) {
        $config = $this->get_field_configuration();
        return isset($config[$field_type]) && $config[$field_type]['is_pro'];
    }
    
    /**
     * Get required plan for field
     */
    public function get_field_required_plan($field_type) {
        $config = $this->get_field_configuration();
        return $config[$field_type]['required_plan'] ?? 'free';
    }
    
    /**
     * Get field configuration for specific field
     */
    public function get_field_config($field_type) {
        $config = $this->get_field_configuration();
        $field_config = $config[$field_type] ?? ['required_plan' => 'free', 'is_pro' => false];
        
        // Debug logging
        error_log('CCC ManifestService: Field config for ' . $field_type . ': ' . json_encode($field_config));
        
        return $field_config;
    }
    
    /**
     * Get all field configurations
     */
    public function get_all_field_configurations() {
        return $this->get_field_configuration();
    }
    
    /**
     * Get field configurations with availability flags based on license status
     */
    public function get_filtered_field_configurations() {
        $license_key = get_option('ccc_license_key', '');
        $has_valid_license = !empty($license_key);
        $is_pro_license = false;
        $plugin_version = defined('CCC_VERSION') ? CCC_VERSION : get_option('ccc_plugin_version', '1.0.0');
        
        // Check if license is valid and PRO
        if ($has_valid_license) {
            $license_validator = new LicenseValidator();
            $validation = $license_validator->validate_license($license_key);
            $is_pro_license = $validation['valid'] && ($validation['license']['isPro'] ?? false);
            
            // Debug logging
            error_log('CCC ManifestService: License validation result: ' . json_encode($validation));
            error_log('CCC ManifestService: is_pro_license: ' . ($is_pro_license ? 'true' : 'false'));
        }
        
        // Get all field configurations (hardcoded)
        $all_configs = $this->get_field_configuration();
        $filtered_configs = [];
        
        foreach ($all_configs as $field_type => $config) {
            $field_config = $config;
            
            // Check version compatibility (always true for hardcoded config)
            $is_version_compatible = true;
            
            // Add availability flag based on license
            if ($config['is_pro'] ?? false) {
                // PRO field - available only if user has valid license
                $field_config['available'] = $has_valid_license && $is_pro_license;
                
                // Debug logging for PRO fields
                if ($field_type === 'repeater' || $field_type === 'gallery') {
                    error_log("CCC ManifestService: {$field_type} field availability - has_valid_license: " . ($has_valid_license ? 'true' : 'false') . ", is_pro_license: " . ($is_pro_license ? 'true' : 'false') . ", available: " . ($field_config['available'] ? 'true' : 'false'));
                }
            } else {
                // Free field - always available
                $field_config['available'] = true;
            }
            
            // Add version compatibility info
            $field_config['version_compatible'] = $is_version_compatible;
            $field_config['plugin_version'] = $plugin_version;
            
            $filtered_configs[$field_type] = $field_config;
        }
        
        return $filtered_configs;
    }
    
    /**
     * Check if plugin version is compatible with field configuration
     */
    private function is_version_compatible($plugin_version, $config) {
        $min_version = $config['min_plugin_version'] ?? '1.0.0';
        $max_version = $config['max_plugin_version'] ?? null;
        
        // Simple version comparison (you might want to use a proper semver library)
        $plugin_version_parts = explode('.', $plugin_version);
        $min_version_parts = explode('.', $min_version);
        
        // Check minimum version
        for ($i = 0; $i < max(count($plugin_version_parts), count($min_version_parts)); $i++) {
            $plugin_part = intval($plugin_version_parts[$i] ?? 0);
            $min_part = intval($min_version_parts[$i] ?? 0);
            
            if ($plugin_part < $min_part) {
                return false;
            } elseif ($plugin_part > $min_part) {
                break;
            }
        }
        
        // Check maximum version if specified
        if ($max_version) {
            $max_version_parts = explode('.', $max_version);
            
            for ($i = 0; $i < max(count($plugin_version_parts), count($max_version_parts)); $i++) {
                $plugin_part = intval($plugin_version_parts[$i] ?? 0);
                $max_part = intval($max_version_parts[$i] ?? 0);
                
                if ($plugin_part > $max_part) {
                    return false;
                } elseif ($plugin_part < $max_part) {
                    break;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Get only free field configurations
     */
    private function get_free_field_configurations() {
        $all_configs = $this->get_field_configuration();
        $free_configs = [];
        
        foreach ($all_configs as $field_type => $config) {
            if (!($config['is_pro'] ?? false)) {
                $free_configs[$field_type] = $config;
            }
        }
        
        return $free_configs;
    }
    
    /**
     * Schedule automatic sync
     */
    public function schedule_auto_sync() {
        if (!wp_next_scheduled('ccc_sync_field_configuration')) {
            wp_schedule_event(time(), 'hourly', 'ccc_sync_field_configuration');
        }
    }
    
    /**
     * Unschedule automatic sync
     */
    public function unschedule_auto_sync() {
        wp_clear_scheduled_hook('ccc_sync_field_configuration');
    }
    
    /**
     * Handle automatic sync
     */
    public function handle_auto_sync() {
        try {
            $this->refresh_field_configuration();
            error_log('CCC ManifestService: Field configuration synced successfully');
        } catch (\Exception $e) {
            error_log('CCC ManifestService: Auto sync failed - ' . $e->getMessage());
        }
    }
    
    /**
     * Get manifest version info
     */
    public function get_manifest_info() {
        try {
            $response = wp_remote_get($this->api_url . '/pro-features/manifest', [
                'timeout' => 10,
                'headers' => [
                    'User-Agent' => 'Custom-Craft-Component-Plugin/' . get_option('ccc_plugin_version', '1.0.0')
                ]
            ]);
            
            if (is_wp_error($response)) {
                return null;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!$data || !$data['success']) {
                return null;
            }
            
            return [
                'version' => $data['version'] ?? '1.0.0',
                'last_updated' => $data['last_updated'] ?? null,
                'plan_limits' => $data['plan_limits'] ?? []
            ];
            
        } catch (\Exception $e) {
            error_log('CCC ManifestService: Failed to get manifest info - ' . $e->getMessage());
            return null;
        }
    }
}
