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
}

// Global helper functions
if (!function_exists('get_ccc_post_components')) {
    function get_ccc_post_components($post_id = null) {
        return \CCC\Helpers\TemplateHelpers::getCccPostComponents($post_id);
    }
}

if (!function_exists('get_ccc_field')) {
    function get_ccc_field($field_name, $post_id = null, $component_id = null) {
        global $wpdb, $ccc_current_component, $ccc_current_post_id;
        
        // Use global context if available
        if (!$post_id) {
            $post_id = $ccc_current_post_id ?: get_the_ID();
        }
        
        if (!$component_id && isset($ccc_current_component['id'])) {
            $component_id = $ccc_current_component['id'];
        }
        
        if (!$post_id) {
            return '';
        }
        
        $fields_table = $wpdb->prefix . 'cc_fields';
        $values_table = $wpdb->prefix . 'cc_field_values';
        
        $query = "
            SELECT fv.value 
            FROM $values_table fv
            INNER JOIN $fields_table f ON f.id = fv.field_id
            WHERE fv.post_id = %d 
            AND f.name = %s
        ";
        
        $params = [$post_id, $field_name];
        
        // If component_id is specified, add it to the query
        if ($component_id) {
            $query .= " AND f.component_id = %d";
            $params[] = $component_id;
        }
        
        $query .= " ORDER BY fv.id DESC LIMIT 1";
        
        $value = $wpdb->get_var($wpdb->prepare($query, $params));
        
        return $value ?: '';
    }
}

if (!function_exists('get_ccc_component_fields')) {
    function get_ccc_component_fields($component_id, $post_id = null) {
        global $wpdb, $ccc_current_post_id;
        
        if (!$post_id) {
            $post_id = $ccc_current_post_id ?: get_the_ID();
        }
        
        if (!$post_id || !$component_id) {
            return [];
        }
        
        $fields_table = $wpdb->prefix . 'cc_fields';
        $values_table = $wpdb->prefix . 'cc_field_values';
        
        $query = "
            SELECT f.name, f.label, f.type, COALESCE(fv.value, '') as value
            FROM $fields_table f
            LEFT JOIN $values_table fv ON f.id = fv.field_id AND fv.post_id = %d
            WHERE f.component_id = %d
            ORDER BY f.field_order, f.created_at
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($query, $post_id, $component_id), ARRAY_A);
        
        $fields = [];
        foreach ($results as $result) {
            $fields[$result['name']] = $result['value'];
        }
        
        return $fields;
    }
}
