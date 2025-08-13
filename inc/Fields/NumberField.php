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
        
        // Apply min/max constraints
        if (isset($config['min_value']) && $config['min_value'] !== null && $num < $config['min_value']) {
            $num = $config['min_value'];
        }
        
        if (isset($config['max_value']) && $config['max_value'] !== null && $num > $config['max_value']) {
            $num = $config['max_value'];
        }
        
        return $num;
    }

    /**
     * Check if a number is unique across all posts
     */
    public function isUnique($value, $current_post_id = null, $field_id = null)
    {
        if (empty($value) || !is_numeric($value)) {
            return true;
        }

        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ccc_field_values';
        
        // If no field_id provided, try to get it from the current field
        if (!$field_id) {
            $field_id = $this->getId();
        }
        
        if (!$field_id) {
            return true; // Can't check uniqueness without field_id
        }
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE field_id = %d 
             AND value = %s 
             AND post_id != %d",
            $field_id,
            $value,
            $current_post_id ?: 0
        );
        
        $count = $wpdb->get_var($query);
        
        return $count == 0;
    }
} 