<?php

namespace CCC\Services;

/**
 * Manifest Service
 * Handles fetching field configuration from the backend API manifest
 */
class ManifestService {
    
    private $api_url;
    private $cache_key = 'ccc_field_config_cache';
    private $cache_duration = 3600; // 1 hour
    
    public function __construct() {
        $this->api_url = defined('CCC_LICENSE_API_URL') ? CCC_LICENSE_API_URL : 'https://api.customcraftcomponents.com/api';
    }
    
    /**
     * Fetch field configuration from manifest endpoint
     */
    public function fetch_field_configuration() {
        try {
            $response = wp_remote_get($this->api_url . '/pro-features/manifest', [
                'timeout' => 10,
                'headers' => [
                    'User-Agent' => 'Custom-Craft-Component-Plugin/' . get_option('ccc_plugin_version', '1.0.0')
                ]
            ]);
            
            if (is_wp_error($response)) {
                error_log('CCC ManifestService: Failed to fetch manifest - ' . $response->get_error_message());
                return $this->get_default_field_configuration();
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!$data || !$data['success']) {
                error_log('CCC ManifestService: Invalid manifest response');
                return $this->get_default_field_configuration();
            }
            
            return $data['field_configuration'] ?? $this->get_default_field_configuration();
            
        } catch (\Exception $e) {
            error_log('CCC ManifestService: Exception fetching manifest - ' . $e->getMessage());
            return $this->get_default_field_configuration();
        }
    }
    
    /**
     * Get cached field configuration or fetch from API
     */
    public function get_field_configuration() {
        // Try to get from cache first
        $cached_config = get_transient($this->cache_key);
        
        if ($cached_config !== false) {
            return $cached_config;
        }
        
        // Fetch from API
        $config = $this->fetch_field_configuration();
        
        // Cache the result
        set_transient($this->cache_key, $config, $this->cache_duration);
        
        return $config;
    }
    
    /**
     * Force refresh field configuration from API
     */
    public function refresh_field_configuration() {
        // Clear cache
        delete_transient($this->cache_key);
        
        // Fetch fresh data
        $config = $this->fetch_field_configuration();
        
        // Cache the result
        set_transient($this->cache_key, $config, $this->cache_duration);
        
        // Update the stored configuration
        update_option('ccc_field_configuration', $config);
        
        return $config;
    }
    
    /**
     * Get default field configuration (fallback)
     */
    private function get_default_field_configuration() {
        return [
            'text' => ['required_plan' => 'free', 'is_pro' => false],
            'textarea' => ['required_plan' => 'free', 'is_pro' => false],
            'image' => ['required_plan' => 'free', 'is_pro' => false],
            'video' => ['required_plan' => 'free', 'is_pro' => false],
            'oembed' => ['required_plan' => 'free', 'is_pro' => false],
            'relationship' => ['required_plan' => 'free', 'is_pro' => false],
            'link' => ['required_plan' => 'free', 'is_pro' => false],
            'email' => ['required_plan' => 'free', 'is_pro' => false],
            'number' => ['required_plan' => 'free', 'is_pro' => false],
            'range' => ['required_plan' => 'free', 'is_pro' => false],
            'file' => ['required_plan' => 'free', 'is_pro' => false],
            'repeater' => ['required_plan' => 'pro', 'is_pro' => true],
            'wysiwyg' => ['required_plan' => 'free', 'is_pro' => false],
            'color' => ['required_plan' => 'free', 'is_pro' => false],
            'select' => ['required_plan' => 'free', 'is_pro' => false],
            'checkbox' => ['required_plan' => 'free', 'is_pro' => false],
            'radio' => ['required_plan' => 'free', 'is_pro' => false],
            'toggle' => ['required_plan' => 'free', 'is_pro' => false],
            'gallery' => ['required_plan' => 'pro', 'is_pro' => true],
            'date' => ['required_plan' => 'free', 'is_pro' => false],
            'date_range' => ['required_plan' => 'pro', 'is_pro' => true],
            'datetime' => ['required_plan' => 'free', 'is_pro' => false],
            'time' => ['required_plan' => 'free', 'is_pro' => false],
            'time_range' => ['required_plan' => 'pro', 'is_pro' => true],
            'ai_generator' => ['required_plan' => 'pro', 'is_pro' => true],
            'conditional_logic' => ['required_plan' => 'max', 'is_pro' => true],
            'custom_validation' => ['required_plan' => 'max', 'is_pro' => true],
            'api_integration' => ['required_plan' => 'max', 'is_pro' => true]
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
        return $config[$field_type] ?? ['required_plan' => 'free', 'is_pro' => false];
    }
    
    /**
     * Get all field configurations
     */
    public function get_all_field_configurations() {
        return $this->get_field_configuration();
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
