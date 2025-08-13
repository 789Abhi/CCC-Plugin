<?php

namespace CCC\Fields;

use CCC\Fields\BaseField;

class EmailField extends BaseField
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
        
        // Output the hidden input for the value and div for React component
        echo '<div class="w-full mb-4">';
        echo '<input type="hidden" name="' . esc_attr($field_name) . '" value="' . esc_attr($field_value) . '" />';
        echo '<div id="ccc-email-field-' . esc_attr($instance_id) . '" 
                   data-field-name="' . esc_attr($field_name) . '"
                   data-field-config="' . esc_attr(json_encode($field_config)) . '"
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
        return sanitize_email($value);
    }
} 