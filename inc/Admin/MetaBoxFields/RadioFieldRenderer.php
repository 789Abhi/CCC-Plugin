<?php

namespace CCC\Admin\MetaBoxFields;

defined('ABSPATH') || exit;

class RadioFieldRenderer extends BaseFieldRenderer {
    public function render() {
        $config = $this->getFieldConfig();
        $options = $config['options'] ?? [];
        $required = $this->field->getRequired() ? 'required' : '';
        
        ob_start();
        ?>
        <div class="ccc-radio-options">
            <?php foreach ($options as $option_value => $option_label): ?>
                <label class="ccc-radio-option">
                    <input type="radio" 
                           name="<?php echo esc_attr($this->getFieldName()); ?>" 
                           value="<?php echo esc_attr($option_value); ?>"
                           class="ccc-radio-input"
                           <?php checked($this->field_value, $option_value); ?>
                           <?php echo $required; ?> />
                    <span class="ccc-radio-checkmark"></span>
                    <span class="ccc-radio-label"><?php echo esc_html($option_label); ?></span>
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
            .ccc-field-radio .ccc-radio-options {
                display: flex;
                flex-direction: column;
                gap: 12px;
                padding: 8px 0;
            }
            
            .ccc-field-radio .ccc-radio-option {
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
            
            .ccc-field-radio .ccc-radio-option:hover {
                border-color: #0073aa;
                background-color: #f6f7f7;
            }
            
            .ccc-field-radio .ccc-radio-input {
                position: absolute;
                opacity: 0;
                cursor: pointer;
                height: 0;
                width: 0;
            }
            
            .ccc-field-radio .ccc-radio-checkmark {
                height: 18px;
                width: 18px;
                background-color: #fff;
                border: 2px solid #c3c4c7;
                border-radius: 50%;
                margin-right: 10px;
                position: relative;
                transition: all 0.2s ease;
                flex-shrink: 0;
            }
            
            .ccc-field-radio .ccc-radio-input:checked ~ .ccc-radio-checkmark {
                border-color: #0073aa;
            }
            
            .ccc-field-radio .ccc-radio-checkmark:after {
                content: "";
                position: absolute;
                display: none;
                top: 3px;
                left: 3px;
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: #0073aa;
            }
            
            .ccc-field-radio .ccc-radio-input:checked ~ .ccc-radio-checkmark:after {
                display: block;
            }
            
            .ccc-field-radio .ccc-radio-label {
                font-size: 14px;
                color: #1d2327;
                line-height: 1.4;
                user-select: none;
            }
            
            .ccc-field-radio .ccc-field-label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: #1d2327;
                font-size: 14px;
            }
            
            .ccc-field-radio .ccc-required {
                color: #d63638;
                margin-left: 3px;
            }
        </style>';
    }
}
