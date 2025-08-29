<?php
namespace CCC\Helpers;

defined('ABSPATH') || exit;

class TemplateHelpers {
    public static function getCccPostComponents($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        $components = get_post_meta($post_id, '_ccc_components', true);
        if (!is_array($components)) {
            $components = [];
        }
        
        // Sort by order
        usort($components, function($a, $b) {
            return ($a['order'] ?? 0) - ($b['order'] ?? 0);
        });
        
        return $components;
    }
    
    /**
     * Get components assigned to a post type
     * 
     * @param string $post_type The post type to get components for
     * @return array Array of component objects
     */
    public static function getCccPostTypeComponents($post_type) {
        if (empty($post_type)) {
            return [];
        }
        
        // Get stored components for this post type
        $component_ids = get_option('_ccc_post_type_components_' . $post_type, []);
        
        if (empty($component_ids)) {
            return [];
        }
        
        global $wpdb;
        $components_table = $wpdb->prefix . 'cc_components';
        $components = [];
        
        foreach ($component_ids as $component_id) {
            $component = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $components_table WHERE id = %d AND hidden = 0", 
                $component_id
            ));
            if ($component) {
                $components[] = $component;
            }
        }
        
        return $components;
    }
    
    /**
     * Get all components for a post (both direct and post type level)
     * 
     * @param int $post_id The post ID
     * @return array Array of component objects
     */
    public static function getAllCccComponents($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        $post = get_post($post_id);
        if (!$post) {
            return [];
        }
        
        // Get direct post components
        $direct_components = self::getCccPostComponents($post_id);
        
        // Get post type level components
        $post_type_components = self::getCccPostTypeComponents($post->post_type);
        
        // Merge and remove duplicates
        $all_components = [];
        $seen_ids = [];
        
        foreach ($direct_components as $component) {
            if (!in_array($component->id, $seen_ids)) {
                $all_components[] = $component;
                $seen_ids[] = $component->id;
            }
        }
        
        foreach ($post_type_components as $component) {
            if (!in_array($component->id, $seen_ids)) {
                $all_components[] = $component;
                $seen_ids[] = $component->id;
            }
        }
        
        return $all_components;
    }
}

// Load global helpers if not already loaded
$global_helpers_file = plugin_dir_path(__FILE__) . 'GlobalHelpers.php';
if (file_exists($global_helpers_file) && !function_exists('get_ccc_field')) {
    require_once $global_helpers_file;
}
