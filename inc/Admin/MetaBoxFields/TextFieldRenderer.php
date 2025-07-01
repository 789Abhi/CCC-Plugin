<?php

namespace CCC\Admin\MetaBoxFields;

defined('ABSPATH') || exit;

class TextFieldRenderer extends BaseFieldRenderer {
    public function render() {
        $required = $this->field->getRequired() ? 'required' : '';
        $placeholder = esc_attr($this->field->getPlaceholder());
        
        $content = sprintf(
            '<input type="text" id="%s" name="%s" value="%s" placeholder="%s" class="ccc-text-input" %s />',
            esc_attr($this->getFieldId()),
            esc_attr($this->getFieldName()),
            esc_attr($this->field_value),
            $placeholder,
            $required
        );

        return $this->renderFieldWrapper($content) . $this->renderFieldStyles();
    }

    protected function renderFieldStyles() {
        return '
        <style>
            .ccc-field-text .ccc-text-input {
                width: 100%;
                padding: 10px 12px;
                border: 2px solid #e1e5e9;
                border-radius: 6px;
                font-size: 14px;
                line-height: 1.4;
                transition: all 0.2s ease;
                background-color: #fff;
            }
            
            .ccc-field-text .ccc-text-input:focus {
                outline: none;
                border-color: #0073aa;
                box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.1);
            }
            
            .ccc-field-text .ccc-text-input:hover {
                border-color: #c3c4c7;
            }
            
            .ccc-field-text .ccc-field-label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: #1d2327;
                font-size: 14px;
            }
            
            .ccc-field-text .ccc-required {
                color: #d63638;
                margin-left: 3px;
            }
        </style>';
    }
}
