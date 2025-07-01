<?php

namespace CCC\Admin\MetaBoxFields;

defined('ABSPATH') || exit;

class TextareaFieldRenderer extends BaseFieldRenderer {
    public function render() {
        $required = $this->field->getRequired() ? 'required' : '';
        $placeholder = esc_attr($this->field->getPlaceholder());
        
        $content = sprintf(
            '<textarea id="%s" name="%s" placeholder="%s" rows="5" class="ccc-textarea-input" %s>%s</textarea>',
            esc_attr($this->getFieldId()),
            esc_attr($this->getFieldName()),
            $placeholder,
            $required,
            esc_textarea($this->field_value)
        );

        return $this->renderFieldWrapper($content) . $this->renderFieldStyles();
    }

    protected function renderFieldStyles() {
        return '
        <style>
            .ccc-field-textarea .ccc-textarea-input {
                width: 100%;
                padding: 10px 12px;
                border: 2px solid #e1e5e9;
                border-radius: 6px;
                font-size: 14px;
                line-height: 1.5;
                transition: all 0.2s ease;
                background-color: #fff;
                resize: vertical;
                min-height: 100px;
                font-family: inherit;
            }
            
            .ccc-field-textarea .ccc-textarea-input:focus {
                outline: none;
                border-color: #0073aa;
                box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.1);
            }
            
            .ccc-field-textarea .ccc-textarea-input:hover {
                border-color: #c3c4c7;
            }
            
            .ccc-field-textarea .ccc-field-label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: #1d2327;
                font-size: 14px;
            }
            
            .ccc-field-textarea .ccc-required {
                color: #d63638;
                margin-left: 3px;
            }
        </style>';
    }
}
