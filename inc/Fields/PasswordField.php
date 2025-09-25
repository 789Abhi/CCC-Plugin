<?php

namespace CCC\Fields;

use CCC\Fields\BaseField;

class PasswordField extends BaseField
{
    protected $field_type = 'password';
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
        $field_label = $this->getLabel();
        $field_placeholder = $this->getPlaceholder();
        
        // Output the hidden input for the value and div for React component
        echo '<div class="w-full mb-4 ccc-password-field-wrapper">';
        echo '<input type="hidden" name="' . esc_attr($field_name) . '" value="' . esc_attr($field_value) . '" class="ccc-password-hidden-input" />';
        echo '<div id="ccc-password-field-' . esc_attr($instance_id) . '-' . esc_attr($this->getId()) . '" 
                   class="ccc-password-field-container"
                   data-field-name="' . esc_attr($field_name) . '"
                   data-field-label="' . esc_attr($field_label) . '"
                   data-field-placeholder="' . esc_attr($field_placeholder) . '"
                   data-field-config="' . esc_attr(json_encode($field_config)) . '"
                   data-field-value="' . esc_attr($field_value) . '"
                   data-field-required="' . esc_attr($field_required) . '"
                   data-field-id="' . esc_attr($this->getId()) . '"
                   data-instance-id="' . esc_attr($instance_id) . '"
                   data-post-id="' . esc_attr($post_id) . '">
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
        // Save password as normal text value (sanitized)
        return sanitize_text_field($value);
    }

    /**
     * Verify password against stored value
     * 
     * @param string $password Plain text password to verify
     * @param string $stored_password Stored password value
     * @return bool True if password matches stored value
     */
    public function verifyPassword($password, $stored_password)
    {
        return $password === $stored_password;
    }

    /**
     * Get field type for frontend
     * 
     * @return string
     */
    public function getType()
    {
        return 'password';
    }

    /**
     * Get field configuration with defaults
     * 
     * @return array
     */
    public function getFieldConfig()
    {
        $config = is_string($this->config) ? json_decode($this->config, true) : $this->config;
        
        $defaults = [
            'placeholder' => $this->getPlaceholder() ?: 'Enter password',
            'description' => ''
        ];
        
        return array_merge($defaults, (array) $config);
    }

    /**
     * Validate password field value
     * 
     * @param string $value
     * @return array Array with 'valid' boolean and 'message' string
     */
    public function validate($value)
    {
        // Check if required
        if ($this->isRequired() && empty($value)) {
            return [
                'valid' => false,
                'message' => 'Password is required'
            ];
        }
        
        return [
            'valid' => true,
            'message' => 'Password is valid'
        ];
    }
}
