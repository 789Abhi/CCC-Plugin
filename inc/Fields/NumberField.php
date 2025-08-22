<?php

namespace CCC\Fields;

use CCC\Fields\BaseField;
use Exception;

class NumberField extends BaseField
{
    public function __construct($label, $name, $component_id, $required = false, $placeholder = '', $config = '')
    {
        parent::__construct($label, $name, $component_id, $required, $placeholder, $config);
    }

    public function render($post_id, $instance_id, $value = '')
    {
        $field_name = $this->getName();
        $field_config = $this->getConfig();
        $field_value = $value;
        $field_required = $this->isRequired() ? 'true' : 'false';
        
        // Parse config to get number field specific options
        $config = [];
        if (!empty($field_config)) {
            try {
                $config = is_string($field_config) ? json_decode($field_config, true) : $field_config;
            } catch (Exception $e) {
                $config = [];
            }
        }
        
        // Ensure all config values are properly set
        $config = array_merge([
            'unique' => false,
            'min_value' => null,
            'max_value' => null,
            'min_length' => null,
            'max_length' => null,
            'prepend' => '',
            'append' => ''
        ], $config);
        
        // Output the hidden input for the value and div for React component
        echo '<div class="w-full mb-4">';
        echo '<input type="hidden" name="' . esc_attr($field_name) . '" value="' . esc_attr($field_value) . '" />';
        echo '<div id="ccc-number-field-' . esc_attr($instance_id) . '" 
                   data-field-name="' . esc_attr($field_name) . '"
                   data-field-config="' . esc_attr(json_encode($config)) . '"
                   data-field-value="' . esc_attr($field_value) . '"
                   data-field-required="' . esc_attr($field_required) . '">
             </div>';
        echo '</div>';
    }

    public function save()
    {
        // Saving is handled by FieldValue model
        return true;
    }

    public function sanitize($value)
    {
        if (!is_numeric($value)) {
            return '';
        }
        
        $num = floatval($value);
        $config = $this->getConfig();
        
        // Parse config if it's a string
        if (is_string($config)) {
            try {
                $config = json_decode($config, true);
            } catch (Exception $e) {
                $config = [];
            }
        }
        
        // Apply min/max constraints ONLY for normal number type
        if (isset($config['number_type']) && $config['number_type'] === 'normal') {
            if (isset($config['min_value']) && $config['min_value'] !== null && $num < $config['min_value']) {
                $num = $config['min_value'];
            }
            
            if (isset($config['max_value']) && $config['max_value'] !== null && $num > $config['max_value']) {
                $num = $config['max_value'];
            }
        }
        
        // Apply character length constraints ONLY for phone number type
        if (isset($config['number_type']) && $config['number_type'] === 'phone') {
            $value_str = (string)$num;
            if (isset($config['min_length']) && $config['min_length'] !== null && strlen($value_str) < $config['min_length']) {
                // Pad with zeros to meet minimum length requirement
                $num = str_pad($num, $config['min_length'], '0', STR_PAD_LEFT);
            }
            
            if (isset($config['max_length']) && $config['max_length'] !== null && strlen($value_str) > $config['max_length']) {
                // Truncate to meet maximum length requirement
                $num = substr($value_str, 0, $config['max_length']);
            }
        }
        
        return $num;
    }

    /**
     * Check if a number is unique across all posts and within the same post
     * This prevents duplicate values when the same component is used multiple times
     * or when using repeater fields within the same post
     * 
     * @param mixed $value The value to check
     * @param int|null $current_post_id The current post ID
     * @param int|null $field_id The field ID
     * @param string|null $current_instance_id The current instance ID (for repeater fields)
     * @return bool True if unique, false if duplicate found
     */
    public function isUnique($value, $current_post_id = null, $field_id = null, $current_instance_id = null)
    {
        if (empty($value) || !is_numeric($value)) {
            error_log("CCC DEBUG: isUnique - Empty or non-numeric value, returning true");
            return true;
        }

        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cc_field_values';
        
        error_log("CCC DEBUG: isUnique - Checking uniqueness for value: $value, post_id: $current_post_id, field_id: $field_id, instance_id: $current_instance_id");
        error_log("CCC DEBUG: isUnique - Using table: $table_name");
        
        // If no field_id provided, try to get it from the current field
        if (!$field_id) {
            $field_id = $this->getId();
        }
        
        if (!$field_id) {
            error_log("CCC DEBUG: isUnique - No field_id provided, returning true");
            return true; // Can't check uniqueness without field_id
        }
        
        // Check for duplicates across different posts
        $cross_post_query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE field_id = %d 
             AND value = %s 
             AND post_id != %d",
            $field_id,
            $value,
            $current_post_id ?: 0
        );
        
        error_log("CCC DEBUG: isUnique - Cross-post query: $cross_post_query");
        $cross_post_count = $wpdb->get_var($cross_post_query);
        error_log("CCC DEBUG: isUnique - Cross-post count: $cross_post_count");
        
        if ($cross_post_count > 0) {
            error_log("CCC DEBUG: isUnique - Value already exists in another post, returning false");
            return false; // Value already exists in another post
        }
        
        // Check for duplicates within the same post (different instances of the same component)
        if ($current_post_id) {
            // Build the query to check for duplicates in the same post
            $same_post_query = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} 
                 WHERE field_id = %d 
                 AND value = %s 
                 AND post_id = %d",
                $field_id,
                $value,
                $current_post_id
            );
            
            // If we have an instance_id, exclude it from the check (for editing)
            if ($current_instance_id) {
                $same_post_query .= $wpdb->prepare(
                    " AND instance_id != %s",
                    $current_instance_id
                );
            }
            
            error_log("CCC DEBUG: isUnique - Same-post query: $same_post_query");
            $same_post_count = $wpdb->get_var($same_post_query);
            error_log("CCC DEBUG: isUnique - Same-post count: $same_post_count");
            
            if ($same_post_count > 0) {
                error_log("CCC DEBUG: isUnique - Value already exists in the same post, returning false");
                return false; // Value already exists in the same post (different instance)
            }
        }
        
        error_log("CCC DEBUG: isUnique - Value is unique, returning true");
        return true;
    }
} 