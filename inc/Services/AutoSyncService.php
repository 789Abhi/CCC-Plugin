<?php

namespace CCC\Services;

/**
 * Auto Sync Service
 * Handles automatic synchronization of field configuration from manifest
 */
class AutoSyncService {
    
    private $manifest_service;
    
    public function __construct() {
        $this->manifest_service = new ManifestService();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Hook for automatic sync
        add_action('ccc_sync_field_configuration', [$this, 'handle_auto_sync']);
        
        // Hook for plugin activation
        add_action('ccc_plugin_activated', [$this, 'schedule_auto_sync']);
        
        // Hook for plugin deactivation
        add_action('ccc_plugin_deactivated', [$this, 'unschedule_auto_sync']);
        
        // Hook for admin init to ensure sync is scheduled
        add_action('admin_init', [$this, 'ensure_sync_scheduled']);
        
        // Hook for plugin updates
        add_action('upgrader_process_complete', [$this, 'handle_plugin_update'], 10, 2);
    }
    
    /**
     * Handle automatic sync
     */
    public function handle_auto_sync() {
        try {
            $this->manifest_service->handle_auto_sync();
            
            // Log successful sync
            error_log('CCC AutoSyncService: Field configuration synced successfully');
            
            // Update last sync time
            update_option('ccc_last_sync_time', current_time('timestamp'));
            
        } catch (\Exception $e) {
            error_log('CCC AutoSyncService: Auto sync failed - ' . $e->getMessage());
        }
    }
    
    /**
     * Schedule automatic sync
     */
    public function schedule_auto_sync() {
        $this->manifest_service->schedule_auto_sync();
        error_log('CCC AutoSyncService: Auto sync scheduled');
    }
    
    /**
     * Unschedule automatic sync
     */
    public function unschedule_auto_sync() {
        $this->manifest_service->unschedule_auto_sync();
        error_log('CCC AutoSyncService: Auto sync unscheduled');
    }
    
    /**
     * Ensure sync is scheduled (called on admin_init)
     */
    public function ensure_sync_scheduled() {
        if (!wp_next_scheduled('ccc_sync_field_configuration')) {
            $this->schedule_auto_sync();
        }
    }
    
    /**
     * Handle plugin update
     */
    public function handle_plugin_update($upgrader_object, $options) {
        // Check if our plugin was updated
        if ($options['type'] === 'plugin' && isset($options['plugins'])) {
            foreach ($options['plugins'] as $plugin) {
                if (strpos($plugin, 'custom-craft-component') !== false) {
                    // Plugin was updated, refresh field configuration
                    $this->handle_auto_sync();
                    break;
                }
            }
        }
    }
    
    /**
     * Force manual sync
     */
    public function force_sync() {
        try {
            $configurations = $this->manifest_service->refresh_field_configuration();
            
            // Update last sync time
            update_option('ccc_last_sync_time', current_time('timestamp'));
            
            return [
                'success' => true,
                'message' => 'Field configuration synced successfully',
                'configurations' => $configurations
            ];
            
        } catch (\Exception $e) {
            error_log('CCC AutoSyncService: Force sync failed - ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to sync field configuration: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get sync status
     */
    public function get_sync_status() {
        $last_sync = get_option('ccc_last_sync_time', 0);
        $next_sync = wp_next_scheduled('ccc_sync_field_configuration');
        
        return [
            'last_sync' => $last_sync ? date('Y-m-d H:i:s', $last_sync) : 'Never',
            'next_sync' => $next_sync ? date('Y-m-d H:i:s', $next_sync) : 'Not scheduled',
            'is_scheduled' => $next_sync !== false,
            'time_since_last_sync' => $last_sync ? human_time_diff($last_sync) . ' ago' : 'Never'
        ];
    }
    
    /**
     * Check if sync is needed
     */
    public function is_sync_needed() {
        $last_sync = get_option('ccc_last_sync_time', 0);
        $sync_interval = 3600; // 1 hour
        
        return (current_time('timestamp') - $last_sync) > $sync_interval;
    }
}
