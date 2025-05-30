<?php
// Global helper functions - NO NAMESPACE
defined('ABSPATH') || exit;

// Ensure these functions are only defined once
if (!function_exists('get_ccc_field')) {
    function get_ccc_field($field_name, $post_id = null, $component_id = null, $instance_id = null) {
        global $wpdb, $ccc_current_component, $ccc_current_post_id, $ccc_current_instance_id;
        
        // Use global context if available
        if (!$post_id) {
            $post_id = $ccc_current_post_id ?: get_the_ID();
        }
        
        if (!$component_id && isset($ccc_current_component['id'])) {
            $component_id = $ccc_current_component['id'];
        }
        
        if (!$instance_id && isset($ccc_current_instance_id)) {
            $instance_id = $ccc_current_instance_id;
        }
        
        if (!$post_id) {
            error_log("CCC: No post ID available for get_ccc_field('$field_name')");
            return '';
        }
        
        $fields_table = $wpdb->prefix . 'cc_fields';
        $values_table = $wpdb->prefix . 'cc_field_values';
        
        // Check if this is a repeater field
        $field_type_query = $wpdb->prepare(
            "SELECT type, config FROM $fields_table WHERE name = %s",
            $field_name
        );
        
        $field_info = $wpdb->get_row($field_type_query);
        
        if ($field_info && $field_info->type === 'repeater') {
            // For repeater fields, return the parsed JSON array
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
            
            // If instance_id is specified, add it to the query
            if ($instance_id) {
                $query .= " AND fv.instance_id = %s";
                $params[] = $instance_id;
            }
            
            $query .= " ORDER BY fv.id DESC LIMIT 1";
            
            $value = $wpdb->get_var($wpdb->prepare($query, $params));
            
            // Parse JSON for repeater fields
            if ($value) {
                return json_decode($value, true);
            }
            
            return [];
        } else {
            // For regular fields, return the value as string
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
            
            // If instance_id is specified, add it to the query
            if ($instance_id) {
                $query .= " AND fv.instance_id = %s";
                $params[] = $instance_id;
            }
            
            $query .= " ORDER BY fv.id DESC LIMIT 1";
            
            $value = $wpdb->get_var($wpdb->prepare($query, $params));
            
            error_log("CCC: get_ccc_field('$field_name', $post_id, $component_id, '$instance_id') = '" . ($value ?: 'EMPTY') . "'");
            
            return $value ?: '';
        }
    }
}

if (!function_exists('get_ccc_component_fields')) {
    function get_ccc_component_fields($component_id, $post_id = null, $instance_id = null) {
        global $wpdb, $ccc_current_post_id, $ccc_current_instance_id;
        
        if (!$post_id) {
            $post_id = $ccc_current_post_id ?: get_the_ID();
        }
        
        if (!$instance_id && isset($ccc_current_instance_id)) {
            $instance_id = $ccc_current_instance_id;
        }
        
        if (!$post_id || !$component_id) {
            error_log("CCC: Invalid parameters for get_ccc_component_fields($component_id, $post_id, '$instance_id')");
            return [];
        }
        
        $fields_table = $wpdb->prefix . 'cc_fields';
        $values_table = $wpdb->prefix . 'cc_field_values';
        
        $query = "
            SELECT f.name, f.label, f.type, f.config, COALESCE(fv.value, '') as value
            FROM $fields_table f
            LEFT JOIN $values_table fv ON f.id = fv.field_id AND fv.post_id = %d
        ";
        
        $params = [$post_id];
        
        if ($instance_id) {
            $query .= " AND fv.instance_id = %s";
            $params[] = $instance_id;
        }
        
        $query .= " WHERE f.component_id = %d ORDER BY f.field_order, f.created_at";
        $params[] = $component_id;
        
        $results = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
        
        $fields = [];
        foreach ($results as $result) {
            // For repeater fields, parse the JSON
            if ($result['type'] === 'repeater' && !empty($result['value'])) {
                $fields[$result['name']] = json_decode($result['value'], true);
            } else {
                $fields[$result['name']] = $result['value'];
            }
        }
        
        error_log("CCC: get_ccc_component_fields($component_id, $post_id, '$instance_id') returned " . count($fields) . " fields: " . implode(', ', array_keys($fields)));
        
        return $fields;
    }
}

if (!function_exists('get_ccc_post_components')) {
    function get_ccc_post_components($post_id = null) {
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

// Debug function to check if values exist in database
if (!function_exists('ccc_debug_field_values')) {
    function ccc_debug_field_values($post_id = null, $instance_id = null) {
        global $wpdb;
        
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        $values_table = $wpdb->prefix . 'cc_field_values';
        $fields_table = $wpdb->prefix . 'cc_fields';
        
        $query = "
            SELECT f.name, f.label, f.type, fv.value, f.component_id, fv.instance_id
            FROM $values_table fv
            INNER JOIN $fields_table f ON f.id = fv.field_id
            WHERE fv.post_id = %d
        ";
        
        $params = [$post_id];
        
        if ($instance_id) {
            $query .= " AND fv.instance_id = %s";
            $params[] = $instance_id;
        }
        
        $query .= " ORDER BY fv.instance_id, f.component_id";
        
        $results = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
        
        error_log("CCC DEBUG: Field values for post $post_id" . ($instance_id ? " instance $instance_id" : "") . ":");
        foreach ($results as $result) {
            error_log("  - Field: {$result['name']} = '{$result['value']}' (Component: {$result['component_id']}, Instance: {$result['instance_id']})");
        }
        
        return $results;
    }
}
