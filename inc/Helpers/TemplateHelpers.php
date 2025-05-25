<?php
namespace CCC\Helpers;

use CCC\Models\Field;

defined('ABSPATH') || exit;

class TemplateHelpers {
    
    /**
     * Get field value for current post
     * 
     * @param string $field_name Field name
     * @param int|null $post_id Post ID (optional, defaults to current post)
     * @param int|null $component_id Component ID (optional, for disambiguation)
     * @return string|null Field value
     */
    public static function getCccField($field_name, $post_id = null, $component_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        if (!$post_id) {
            return null;
        }
        
        global $wpdb;
        $fields_table = $wpdb->prefix . 'cc_fields';
        $values_table = $wpdb->prefix . 'cc_field_values';
        
        $sql = "
            SELECT fv.value 
            FROM $values_table fv
            INNER JOIN $fields_table f ON f.id = fv.field_id
            WHERE f.name = %s AND fv.post_id = %d
        ";
        
        $params = [$field_name, $post_id];
        
        if ($component_id) {
            $sql .= " AND f.component_id = %d";
            $params[] = $component_id;
        }
        
        $sql .= " LIMIT 1";
        
        return $wpdb->get_var($wpdb->prepare($sql, $params));
    }
    
    /**
     * Get all field values for a component on current post
     * 
     * @param int $component_id Component ID
     * @param int|null $post_id Post ID (optional, defaults to current post)
     * @return array Associative array of field_name => value
     */
    public static function getCccComponentFields($component_id, $post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        if (!$post_id) {
            return [];
        }
        
        global $wpdb;
        $fields_table = $wpdb->prefix . 'cc_fields';
        $values_table = $wpdb->prefix . 'cc_field_values';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT f.name, fv.value 
            FROM $fields_table f
            LEFT JOIN $values_table fv ON f.id = fv.field_id AND fv.post_id = %d
            WHERE f.component_id = %d
            ORDER BY f.field_order, f.created_at
        ", $post_id, $component_id), ARRAY_A);
        
        $fields = [];
        foreach ($results as $row) {
            $fields[$row['name']] = $row['value'];
        }
        
        return $fields;
    }
    
    /**
     * Get components assigned to current post in order
     * 
     * @param int|null $post_id Post ID (optional, defaults to current post)
     * @return array Array of component data with fields
     */
    public static function getCccPostComponents($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        if (!$post_id) {
            return [];
        }
        
        $components = get_post_meta($post_id, '_ccc_components', true);
        if (!is_array($components)) {
            return [];
        }
        
        // Sort by order
        usort($components, function($a, $b) {
            return ($a['order'] ?? 0) - ($b['order'] ?? 0);
        });
        
        // Add field data to each component
        foreach ($components as &$component) {
            $component['fields'] = self::getCccComponentFields($component['id'], $post_id);
        }
        
        return $components;
    }
}

// Global helper functions - Define them immediately
if (!function_exists('get_ccc_field')) {
    /**
     * Get CCC field value
     * 
     * @param string $field_name Field name
     * @param int|null $post_id Post ID (optional)
     * @param int|null $component_id Component ID (optional)
     * @return string|null
     */
    function get_ccc_field($field_name, $post_id = null, $component_id = null) {
        return \CCC\Helpers\TemplateHelpers::getCccField($field_name, $post_id, $component_id);
    }
}

if (!function_exists('get_ccc_component_fields')) {
    /**
     * Get all fields for a component
     * 
     * @param int $component_id Component ID
     * @param int|null $post_id Post ID (optional)
     * @return array
     */
    function get_ccc_component_fields($component_id, $post_id = null) {
        return \CCC\Helpers\TemplateHelpers::getCccComponentFields($component_id, $post_id);
    }
}

if (!function_exists('get_ccc_post_components')) {
    /**
     * Get all components for current post
     * 
     * @param int|null $post_id Post ID (optional)
     * @return array
     */
    function get_ccc_post_components($post_id = null) {
        return \CCC\Helpers\TemplateHelpers::getCccPostComponents($post_id);
    }
}
