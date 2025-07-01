<?php

namespace CCC\Admin\MetaBoxFields;

defined('ABSPATH') || exit;

class CheckboxFieldRenderer extends BaseFieldRenderer {
    public function render() {
        $config = $this->getFieldConfig();
        $options = $config['options'] ?? [];
        $required = $this->field->getRequired() ? 'required' : '';
        
        // Parse selected values
        $selected_values = [];
        if (!empty($this->field_value)) {
            if (is_string($this->field_value)) {
                $selected_values = explode(',', $this->field_value);
            } elseif (is_array($this->field_value)) {
                $selected_values = $this->field_value;
            }
        }
        
        ob_start();
        ?>
        <div class="ccc-checkbox-options">
            <?php foreach ($options as $option_value => $option_label): ?>
                <label class="ccc-checkbox-option">
                    <input type="checkbox" 
                           name="<?php echo esc_attr($this->getFieldName()); ?>[]" 
                           value="<?php echo esc_attr($option_value); ?>"
                           class="ccc-checkbox-input"
                           <?php echo in_array($option_value, $selected_values) ? 'checked' : ''; ?>
                           <?php echo $required; ?> />
                    <span class="ccc-checkbox-checkmark"></span>
                    <span class="ccc-checkbox-label"><?php echo esc_html($option_label); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
        $content = ob_get_clean();

        return $this->renderFieldWrapper($content) . $this->renderFieldStyles();
    }

    protected function renderFieldStyles() {
        return '
        <style>
            .ccc-field-checkbox .ccc-checkbox-options {
                display: flex;
                flex-direction: column;
                gap: 12px;
                padding: 8px 0;
            }
            
            .ccc-field-checkbox .ccc-checkbox-option {
                display: flex;
                align-items: center;
                cursor: pointer;
                padding: 8px 12px;
                border: 1px solid #e1e5e9;
                border-radius: 6px;
                transition: all 0.2s ease;
                background-color: #fff;
                position: relative;
            }
            
            .ccc-field-checkbox .ccc-checkbox-option:hover {
                border-color: #0073aa;
                background-color: #f6f7f7;
            }
            
            .ccc-field-checkbox .ccc-checkbox-input {
                position: absolute;
                opacity: 0;
                cursor: pointer;
                height: 0;
                width: 0;
            }
            
            .ccc-field-checkbox .ccc-checkbox-checkmark {
                height: 18px;
                width: 18px;
                background-color: #fff;
                border: 2px solid #c3c4c7;
                border-radius: 3px;
                margin-right: 10px;
                position: relative;
                transition: all 0.2s ease;
                flex-shrink: 0;
            }
            
            .ccc-field-checkbox .ccc-checkbox-input:checked ~ .ccc-checkbox-checkmark {
                background-color: #0073aa;
                border-color: #0073aa;
            }
            
            .ccc-field-checkbox .ccc-checkbox-checkmark:after {
                content: "";
                position: absolute;
                display: none;
                left: 5px;
                top: 2px;
                width: 4px;
                height: 8px;
                border: solid white;
                border-width: 0 2px 2px 0;
                transform: rotate(45deg);
            }
            
            .ccc-field-checkbox .ccc-checkbox-input:checked ~ .ccc-checkbox-checkmark:after {
                display: block;
            }
            
            .ccc-field-checkbox .ccc-checkbox-label {
                font-size: 14px;
                color: #1d2327;
                line-height: 1.4;
                user-select: none;
            }
            
            .ccc-field-checkbox .ccc-field-label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: #1d2327;
                font-size: 14px;
            }
            
            .ccc-field-checkbox .ccc-required {
                color: #d63638;
                margin-left: 3px;
            }
        </style>';
    }
}
