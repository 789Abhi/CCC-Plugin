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
        
        // Get field type and config from the fields table
        $field_info_query = $wpdb->prepare(
            "SELECT id, type, config FROM $fields_table WHERE name = %s",
            $field_name
        );
        $field_info = $wpdb->get_row($field_info_query);
        if (!$field_info) {
            error_log("CCC: Field '$field_name' not found in database.");
            return '';
        }

        $field_db_id = $field_info->id;
        $field_type = $field_info->type;
        $field_config = json_decode($field_info->config, true);

        // Base query to get the field value
        $query = "
            SELECT fv.value 
            FROM $values_table fv
            WHERE fv.post_id = %d 
            AND fv.field_id = %d
        ";
        
        $params = [$post_id, $field_db_id];
        
        // If instance_id is specified, add it to the query
        if ($instance_id) {
            $query .= " AND fv.instance_id = %s";
            $params[] = $instance_id;
        }
        
        $query .= " ORDER BY fv.id DESC LIMIT 1"; // Get the latest value
        $value = $wpdb->get_var($wpdb->prepare($query, $params));
        
        // Process value based on field type
        if ($field_type === 'repeater') {
            $decoded_value = json_decode($value, true) ?: [];
            // Check if this is the new format with data and state
            if (is_array($decoded_value) && isset($decoded_value['data']) && isset($decoded_value['state'])) {
                return $decoded_value['data']; // Return only the data part for backward compatibility
            } else {
                // Legacy format - return as is
                return $decoded_value;
            }
        } elseif ($field_type === 'image') {
            $return_type = $field_config['return_type'] ?? 'url';
            $decoded_value = json_decode($value, true);
            if ($return_type === 'array' && is_array($decoded_value)) {
                return $decoded_value;
            } elseif ($return_type === 'url' && is_array($decoded_value) && isset($decoded_value['url'])) {
                return $decoded_value['url'];
            }
            return $value ?: '';
        } elseif ($field_type === 'checkbox') {
            return $value ? explode(',', $value) : [];
        } elseif ($field_type === 'select') {
            $config = $field_config ?: [];
            $multiple = isset($config['multiple']) && $config['multiple'];
            if ($multiple) {
                return $value ? explode(',', $value) : [];
            }
            return $value ?: '';
        } elseif ($field_type === 'radio') {
            return $value ?: '';
        } elseif ($field_type === 'wysiwyg') {
            return wp_kses_post($value);
        } elseif ($field_type === 'color') {
            return $value ?: '';
        }
        
        error_log("CCC: get_ccc_field('$field_name', $post_id, $component_id, '$instance_id') = '" . ($value ?: 'EMPTY') . "'");
        
        return $value ?: '';
    }
}

// Helper functions for new field types

if (!function_exists('get_ccc_select_field')) {
    function get_ccc_select_field($field_name, $post_id = null, $instance_id = null) {
        return get_ccc_field($field_name, $post_id, null, $instance_id);
    }
}

if (!function_exists('get_ccc_checkbox_field')) {
    function get_ccc_checkbox_field($field_name, $post_id = null, $instance_id = null) {
        $value = get_ccc_field($field_name, $post_id, null, $instance_id);
        return is_array($value) ? $value : ($value ? explode(',', $value) : []);
    }
}

if (!function_exists('get_ccc_radio_field')) {
    function get_ccc_radio_field($field_name, $post_id = null, $instance_id = null) {
        return get_ccc_field($field_name, $post_id, null, $instance_id);
    }
}

if (!function_exists('get_ccc_wysiwyg_field')) {
    function get_ccc_wysiwyg_field($field_name, $post_id = null, $instance_id = null) {
        $value = get_ccc_field($field_name, $post_id, null, $instance_id);
        return wp_kses_post($value);
    }
}

if (!function_exists('get_ccc_color_field')) {
    function get_ccc_color_field($field_name, $post_id = null, $instance_id = null) {
        $value = get_ccc_field($field_name, $post_id, null, $instance_id);
        return $value ?: '';
    }
}

// New function to get repeater field with state information
if (!function_exists('get_ccc_repeater_field_with_state')) {
    function get_ccc_repeater_field_with_state($field_name, $post_id = null, $component_id = null, $instance_id = null) {
        global $wpdb, $ccc_current_post_id, $ccc_current_instance_id;
        
        if (!$post_id) {
            $post_id = $ccc_current_post_id ?: get_the_ID();
        }
        
        if (!$instance_id && isset($ccc_current_instance_id)) {
            $instance_id = $ccc_current_instance_id;
        }
        
        if (!$post_id || !$component_id) {
            error_log("CCC: Invalid parameters for get_ccc_repeater_field_with_state($field_name, $post_id, $component_id, '$instance_id')");
            return [];
        }
        
        $fields_table = $wpdb->prefix . 'cc_fields';
        $values_table = $wpdb->prefix . 'cc_field_values';
        
        $field_db_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $fields_table WHERE name = %s AND component_id = %d",
            $field_name,
            $component_id
        ));
        
        if (!$field_db_id) {
            error_log("CCC: Field '$field_name' not found for component $component_id");
            return [];
        }
        
        $field_config = $wpdb->get_var($wpdb->prepare(
            "SELECT config FROM $fields_table WHERE id = %d",
            $field_db_id
        ));
        
        $field_config = json_decode($field_config, true) ?: [];
        
        // Base query to get the field value
        $query = "
            SELECT fv.value 
            FROM $values_table fv
            WHERE fv.post_id = %d 
            AND fv.field_id = %d
        ";
        
        $params = [$post_id, $field_db_id];
        
        // If instance_id is specified, add it to the query
        if ($instance_id) {
            $query .= " AND fv.instance_id = %s";
            $params[] = $instance_id;
        }
        
        $query .= " ORDER BY fv.id DESC LIMIT 1"; // Get the latest value
        $value = $wpdb->get_var($wpdb->prepare($query, $params));
        
        // Process repeater value
        $decoded_value = json_decode($value, true) ?: [];
        // Check if this is the new format with data and state
        if (is_array($decoded_value) && isset($decoded_value['data']) && isset($decoded_value['state'])) {
            return $decoded_value; // Return the complete structure with data and state
        } else {
            // Legacy format - return as is
            return $decoded_value;
        }
    }
}

// Existing helper functions (keeping them for backward compatibility)

if (!function_exists('get_ccc_component_fields')) {
    function _ccc_process_field_value_recursive($value, $field_type, $field_config) {
        if ($field_type === 'repeater') {
            $decoded_value = json_decode($value, true) ?: [];
            
            // Check if this is the new format with data and state
            if (is_array($decoded_value) && isset($decoded_value['data']) && isset($decoded_value['state'])) {
                $repeater_data = $decoded_value['data'];
                $repeater_state = $decoded_value['state'];
                
                $processed_items = [];
                $nested_field_definitions = $field_config['nested_fields'] ?? [];

                foreach ($repeater_data as $item) {
                    $processed_item = [];
                    foreach ($nested_field_definitions as $nested_field_def) {
                        $nested_field_name = $nested_field_def['name'];
                        $nested_field_type = $nested_field_def['type'];
                        $nested_field_config = $nested_field_def['config'] ?? [];

                        if (isset($item[$nested_field_name])) {
                            $processed_item[$nested_field_name] = _ccc_process_field_value_recursive(
                                $item[$nested_field_name],
                                $nested_field_type,
                                $nested_field_config
                            );
                        }
                    }
                    $processed_items[] = $processed_item;
                }
                
                // Return only the processed data for backward compatibility
                return $processed_items;
            } else {
                // Legacy format - process as before
                $processed_items = [];
                $nested_field_definitions = $field_config['nested_fields'] ?? [];

                foreach ($decoded_value as $item) {
                    $processed_item = [];
                    foreach ($nested_field_definitions as $nested_field_def) {
                        $nested_field_name = $nested_field_def['name'];
                        $nested_field_type = $nested_field_def['type'];
                        $nested_field_config = $nested_field_def['config'] ?? [];

                        if (isset($item[$nested_field_name])) {
                            $processed_item[$nested_field_name] = _ccc_process_field_value_recursive(
                                $item[$nested_field_name],
                                $nested_field_type,
                                $nested_field_config
                            );
                        }
                    }
                    $processed_items[] = $processed_item;
                }
                return $processed_items;
            }
        } elseif ($field_type === 'image') {
            $return_type = $field_config['return_type'] ?? 'url';
            $decoded_value = json_decode($value, true);
            if ($return_type === 'array' && is_array($decoded_value)) {
                return $decoded_value;
            } elseif ($return_type === 'url' && is_array($decoded_value) && isset($decoded_value['url'])) {
                return $decoded_value['url'];
            }
            return $value ?: '';
        } elseif ($field_type === 'checkbox') {
            return $value ? explode(',', $value) : [];
        } elseif ($field_type === 'select') {
            $multiple = isset($field_config['multiple']) && $field_config['multiple'];
            if ($multiple) {
                return $value ? explode(',', $value) : [];
            }
            return $value ?: '';
        } elseif ($field_type === 'radio') {
            return $value ?: '';
        } elseif ($field_type === 'wysiwyg') {
            return wp_kses_post($value);
        } elseif ($field_type === 'color') {
            return $value ?: '';
        }

        return $value ?: '';
    }

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
            $field_config = json_decode($result['config'], true) ?: [];
            $fields[$result['name']] = _ccc_process_field_value_recursive(
                $result['value'],
                $result['type'],
                $field_config
            );
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
