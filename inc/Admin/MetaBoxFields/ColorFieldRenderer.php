<?php

namespace CCC\Admin\MetaBoxFields;

defined('ABSPATH') || exit;

class ColorFieldRenderer extends BaseFieldRenderer {
    public function render() {
        $required = $this->field->getRequired() ? 'required' : '';
        $placeholder = esc_attr($this->field->getPlaceholder());
        
        $content = sprintf(
            '<div class="ccc-color-field-wrapper">
                <input type="text" id="%s" name="%s" class="ccc-color-picker" value="%s" placeholder="%s" %s />
                <div class="ccc-color-preview" style="background-color: %s;"></div>
            </div>',
            esc_attr($this->getFieldId()),
            esc_attr($this->getFieldName()),
            esc_attr($this->field_value),
            $placeholder ?: '#000000',
            $required,
            esc_attr($this->field_value ?: '#ffffff')
        );

        return $this->renderFieldWrapper($content) . $this->renderFieldStyles();
    }

    protected function renderFieldStyles() {
        return '
        <style>
            .ccc-field-color .ccc-color-field-wrapper {
                display: flex;
                align-items: center;
                gap: 12px;
                position: relative;
            }
            
            .ccc-field-color .ccc-color-picker {
                flex: 1;
                padding: 10px 12px;
                border: 2px solid #e1e5e9;
                border-radius: 6px;
                font-size: 14px;
                line-height: 1.4;
                transition: all 0.2s ease;
                background-color: #fff;
                font-family: monospace;
            }
            
            .ccc-field-color .ccc-color-picker:focus {
                outline: none;
                border-color: #0073aa;
                box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.1);
            }
            
            .ccc-field-color .ccc-color-picker:hover {
                border-color: #c3c4c7;
            }
            
            .ccc-field-color .ccc-color-preview {
                width: 40px;
                height: 40px;
                border: 2px solid #e1e5e9;
                border-radius: 6px;
                cursor: pointer;
                transition: all 0.2s ease;
                box-shadow: inset 0 0 0 1px rgba(0,0,0,0.1);
                flex-shrink: 0;
            }
            
            .ccc-field-color .ccc-color-preview:hover {
                border-color: #0073aa;
                transform: scale(1.05);
            }
            
            .ccc-field-color .wp-picker-container {
                display: flex;
                align-items: center;
                gap: 10px;
                width: 100%;
            }
            
            .ccc-field-color .wp-picker-container .wp-color-result {
                margin: 0;
                height: 40px;
                width: 40px;
                border-radius: 6px;
                border: 2px solid #e1e5e9;
                box-shadow: inset 0 0 0 1px rgba(0,0,0,0.1);
                transition: all 0.2s ease;
            }
            
            .ccc-field-color .wp-picker-container .wp-color-result:hover {
                border-color: #0073aa;
                transform: scale(1.05);
            }
            
            .ccc-field-color .wp-picker-container .wp-color-result:focus {
                box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.1);
            }
            
            .ccc-field-color .wp-picker-container .wp-picker-input-wrap {
                flex-grow: 1;
            }
            
            .ccc-field-color .wp-picker-container .wp-picker-input-wrap input[type="text"].wp-color-picker {
                width: 100% !important;
                padding: 10px 12px;
                border: 2px solid #e1e5e9;
                border-radius: 6px;
                font-family: monospace;
                font-size: 14px;
                transition: all 0.2s ease;
            }
            
            .ccc-field-color .wp-picker-container .wp-picker-input-wrap input[type="text"].wp-color-picker:focus {
                border-color: #0073aa;
                box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.1);
            }
            
            .ccc-field-color .wp-picker-container .wp-picker-clear {
                margin-left: 8px;
                padding: 8px 12px;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                background: #f6f7f7;
                color: #2c3338;
                text-decoration: none;
                font-size: 12px;
                transition: all 0.2s ease;
            }
            
            .ccc-field-color .wp-picker-container .wp-picker-clear:hover {
                border-color: #0073aa;
                color: #0073aa;
                background: #fff;
            }
            
            .ccc-field-color .ccc-field-label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: #1d2327;
                font-size: 14px;
            }
            
            .ccc-field-color .ccc-required {
                color: #d63638;
                margin-left: 3px;
            }
        </style>';
    }
}
