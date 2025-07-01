<?php

namespace CCC\Admin\MetaBoxFields;

defined('ABSPATH') || exit;

class SelectFieldRenderer extends BaseFieldRenderer {
    public function render() {
        $config = $this->getFieldConfig();
        $options = $config['options'] ?? [];
        $required = $this->field->getRequired() ? 'required' : '';
        $multiple = isset($config['multiple']) && $config['multiple'];
        
        $field_name = $this->getFieldName();
        if ($multiple) {
            $field_name .= '[]';
        }
        
        // Handle multiple values
        $selected_values = [];
        if ($multiple && !empty($this->field_value)) {
            $selected_values = is_array($this->field_value) ? $this->field_value : explode(',', $this->field_value);
        }
        
        ob_start();
        ?>
        <select id="<?php echo esc_attr($this->getFieldId()); ?>" 
                name="<?php echo esc_attr($field_name); ?>" 
                class="ccc-select-input" 
                <?php echo $required; ?>
                <?php echo $multiple ? 'multiple' : ''; ?>>
            
            <?php if (!$this->field->getRequired() && !$multiple): ?>
                <option value="">— Select an option —</option>
            <?php endif; ?>
            
            <?php foreach ($options as $option_value => $option_label): ?>
                <option value="<?php echo esc_attr($option_value); ?>" 
                    <?php 
                    if ($multiple) {
                        echo in_array($option_value, $selected_values) ? 'selected' : '';
                    } else {
                        selected($this->field_value, $option_value);
                    }
                    ?>>
                    <?php echo esc_html($option_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <?php if ($multiple): ?>
            <p class="ccc-field-description">Hold Ctrl (Cmd on Mac) to select multiple options</p>
        <?php endif; ?>
        <?php
        $content = ob_get_clean();

        return $this->renderFieldWrapper($content) . $this->renderFieldStyles();
    }

    protected function renderFieldStyles() {
        return '
        <style>
            .ccc-field-select .ccc-select-input {
                width: 100%;
                padding: 10px 12px;
                border: 2px solid #e1e5e9;
                border-radius: 6px;
                font-size: 14px;
                line-height: 1.4;
                transition: all 0.2s ease;
                background-color: #fff;
                background-image: url("data:image/svg+xml;charset=US-ASCII,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 4 5\'><path fill=\'%23666\' d=\'M2 0L0 2h4zm0 5L0 3h4z\'/></svg>");
                background-repeat: no-repeat;
                background-position: right 12px center;
                background-size: 12px;
                padding-right: 40px;
                cursor: pointer;
            }
            
            .ccc-field-select .ccc-select-input[multiple] {
                background-image: none;
                padding-right: 12px;
                min-height: 120px;
            }
            
            .ccc-field-select .ccc-select-input:focus {
                outline: none;
                border-color: #0073aa;
                box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.1);
            }
            
            .ccc-field-select .ccc-select-input:hover {
                border-color: #c3c4c7;
            }
            
            .ccc-field-select .ccc-field-label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: #1d2327;
                font-size: 14px;
            }
            
            .ccc-field-select .ccc-required {
                color: #d63638;
                margin-left: 3px;
            }
            
            .ccc-field-select .ccc-field-description {
                margin-top: 6px;
                font-size: 12px;
                color: #646970;
                font-style: italic;
            }
        </style>';
    }
}
