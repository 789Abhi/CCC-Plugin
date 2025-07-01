<?php

namespace CCC\Admin\MetaBoxFields;

defined('ABSPATH') || exit;

class RepeaterFieldRenderer extends BaseFieldRenderer {
    public function render() {
        $config = $this->getFieldConfig();
        $repeater_value = $this->field_value ? json_decode($this->field_value, true) : [];
        $max_sets = isset($config['max_sets']) ? intval($config['max_sets']) : 0;
        $nested_field_definitions = isset($config['nested_fields']) ? $config['nested_fields'] : [];
        
        if (!is_array($repeater_value)) {
            $repeater_value = [];
        }
        
        ob_start();
        ?>
        <div class="ccc-repeater-container" 
             data-field-id="<?php echo esc_attr($this->field->getId()); ?>"
             data-instance-id="<?php echo esc_attr($this->instance_id); ?>"
             data-max-sets="<?php echo esc_attr($max_sets); ?>"
             data-nested-field-definitions='<?php echo esc_attr(json_encode($nested_field_definitions)); ?>'>
            
            <div class="ccc-repeater-header">
                <div class="ccc-repeater-info">
                    <span class="ccc-repeater-count"><?php echo count($repeater_value); ?> item(s)</span>
                    <?php if ($max_sets > 0): ?>
                        <span class="ccc-repeater-limit">Max: <?php echo $max_sets; ?></span>
                    <?php endif; ?>
                </div>
                <button type="button" 
                        class="ccc-repeater-add button button-primary"
                        data-field-id="<?php echo esc_attr($this->field->getId()); ?>"
                        data-instance-id="<?php echo esc_attr($this->instance_id); ?>">
                    <span class="dashicons dashicons-plus-alt"></span>
                    Add Item
                </button>
            </div>
            
            <div class="ccc-repeater-items">
                <?php if (!empty($repeater_value)): ?>
                    <?php foreach ($repeater_value as $item_index => $item_data): ?>
                        <?php $this->renderRepeaterItem($item_index, $item_data, $nested_field_definitions); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="ccc-repeater-empty">
                        <div class="ccc-empty-icon">
                            <span class="dashicons dashicons-list-view"></span>
                        </div>
                        <p>No items added yet. Click "Add Item" to get started.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <input type="hidden" 
                   class="ccc-repeater-main-input"
                   id="<?php echo esc_attr($this->getFieldId()); ?>"
                   name="<?php echo esc_attr($this->getFieldName()); ?>"
                   value="<?php echo esc_attr($this->field_value ?: '[]'); ?>" />
        </div>
        <?php
        $content = ob_get_clean();

        return $this->renderFieldWrapper($content) . $this->renderFieldStyles();
    }
    
    protected function renderRepeaterItem($item_index, $item_data, $nested_field_definitions) {
        ?>
        <div class="ccc-repeater-item" data-index="<?php echo esc_attr($item_index); ?>">
            <div class="ccc-repeater-item-header">
                <div class="ccc-repeater-item-title">
                    <span class="ccc-drag-handle dashicons dashicons-menu"></span>
                    <strong>Item #<?php echo esc_html($item_index + 1); ?></strong>
                </div>
                <div class="ccc-repeater-item-controls">
                    <button type="button" class="ccc-repeater-toggle" title="Toggle">
                        <span class="dashicons dashicons-arrow-up-alt2"></span>
                    </button>
                    <button type="button" class="ccc-repeater-remove" title="Remove">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            </div>
            <div class="ccc-repeater-item-content">
                <div class="ccc-repeater-item-fields">
                    <?php foreach ($nested_field_definitions as $nested_field): 
                        $nested_field_value = $item_data[$nested_field['name']] ?? '';
                        $nested_field_config = $nested_field['config'] ?? [];
                    ?>
                        <div class="ccc-nested-field" 
                             data-nested-field-name="<?php echo esc_attr($nested_field['name']); ?>" 
                             data-nested-field-type="<?php echo esc_attr($nested_field['type']); ?>">
                            <label class="ccc-nested-field-label"><?php echo esc_html($nested_field['label']); ?></label>
                            <?php $this->renderNestedField($nested_field, $nested_field_value); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    protected function renderNestedField($field_config, $value) {
        $type = $field_config['type'];
        
        switch ($type) {
            case 'text':
                echo '<input type="text" class="ccc-nested-field-input" data-nested-field-type="text" value="' . esc_attr($value) . '" />';
                break;
            case 'textarea':
                echo '<textarea class="ccc-nested-field-input" data-nested-field-type="textarea" rows="3">' . esc_textarea($value) . '</textarea>';
                break;
            case 'color':
                echo '<input type="text" class="ccc-nested-field-input ccc-color-picker" data-nested-field-type="color" value="' . esc_attr($value) . '" />';
                break;
            case 'select':
                $this->renderNestedSelectField($field_config, $value);
                break;
            case 'checkbox':
                $this->renderNestedCheckboxField($field_config, $value);
                break;
            case 'radio':
                $this->renderNestedRadioField($field_config, $value);
                break;
            case 'wysiwyg':
                echo '<textarea class="ccc-nested-field-input ccc-nested-wysiwyg" data-nested-field-type="wysiwyg" rows="5">' . esc_textarea($value) . '</textarea>';
                break;
            default:
                echo '<input type="text" class="ccc-nested-field-input" data-nested-field-type="text" value="' . esc_attr($value) . '" />';
                break;
        }
    }
    
    protected function renderNestedSelectField($field_config, $value) {
        $options = $field_config['config']['options'] ?? [];
        $multiple = isset($field_config['config']['multiple']) && $field_config['config']['multiple'];
        
        echo '<select class="ccc-nested-field-input" data-nested-field-type="select"' . ($multiple ? ' multiple' : '') . '>';
        if (!$multiple) {
            echo '<option value="">— Select —</option>';
        }
        foreach ($options as $option_value => $option_label) {
            $selected = ($multiple && is_array($value)) ? 
                (in_array($option_value, $value) ? 'selected' : '') : 
                selected($value, $option_value, false);
            echo '<option value="' . esc_attr($option_value) . '" ' . $selected . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
    }
    
    protected function renderNestedCheckboxField($field_config, $value) {
        $options = $field_config['config']['options'] ?? [];
        $selected_values = is_array($value) ? $value : explode(',', $value);
        
        echo '<div class="ccc-nested-checkbox-options">';
        foreach ($options as $option_value => $option_label) {
            $checked = in_array($option_value, $selected_values) ? 'checked' : '';
            echo '<label><input type="checkbox" class="ccc-nested-field-input" data-nested-field-type="checkbox" value="' . esc_attr($option_value) . '" ' . $checked . '> ' . esc_html($option_label) . '</label>';
        }
        echo '</div>';
    }
    
    protected function renderNestedRadioField($field_config, $value) {
        $options = $field_config['config']['options'] ?? [];
        
        echo '<div class="ccc-nested-radio-options">';
        foreach ($options as $option_value => $option_label) {
            $checked = checked($value, $option_value, false);
            echo '<label><input type="radio" class="ccc-nested-field-input" data-nested-field-type="radio" value="' . esc_attr($option_value) . '" ' . $checked . '> ' . esc_html($option_label) . '</label>';
        }
        echo '</div>';
    }

    protected function renderFieldStyles() {
        return '
        <style>
            .ccc-field-repeater .ccc-repeater-container {
                border: 2px solid #e1e5e9;
                border-radius: 8px;
                background: #fff;
                overflow: hidden;
            }
            
            .ccc-field-repeater .ccc-repeater-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 16px 20px;
                background: #f6f7f7;
                border-bottom: 1px solid #e1e5e9;
            }
            
            .ccc-field-repeater .ccc-repeater-info {
                display: flex;
                align-items: center;
                gap: 12px;
                font-size: 14px;
                color: #646970;
            }
            
            .ccc-field-repeater .ccc-repeater-count {
                font-weight: 600;
                color: #1d2327;
            }
            
            .ccc-field-repeater .ccc-repeater-limit {
                background: #dbeafe;
                color: #1e40af;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 500;
            }
            
            .ccc-field-repeater .ccc-repeater-add {
                display: flex;
                align-items: center;
                gap: 6px;
                padding: 8px 16px;
                font-size: 13px;
                border-radius: 6px;
            }
            
            .ccc-field-repeater .ccc-repeater-add .dashicons {
                width: 16px;
                height: 16px;
                font-size: 16px;
            }
            
            .ccc-field-repeater .ccc-repeater-items {
                min-height: 60px;
            }
            
            .ccc-field-repeater .ccc-repeater-empty {
                padding: 40px 20px;
                text-align: center;
                color: #646970;
                background: #fafafa;
            }
            
            .ccc-field-repeater .ccc-empty-icon {
                font-size: 48px;
                color: #c3c4c7;
                margin-bottom: 16px;
            }
            
            .ccc-field-repeater .ccc-empty-icon .dashicons {
                width: 48px;
                height: 48px;
                font-size: 48px;
            }
            
            .ccc-field-repeater .ccc-repeater-item {
                border-bottom: 1px solid #e1e5e9;
                background: #fff;
                transition: all 0.2s ease;
            }
            
            .ccc-field-repeater .ccc-repeater-item:last-child {
                border-bottom: none;
            }
            
            .ccc-field-repeater .ccc-repeater-item:hover {
                background: #f9f9f9;
            }
            
            .ccc-field-repeater .ccc-repeater-item-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 20px;
                background: #f6f7f7;
                border-bottom: 1px solid #e1e5e9;
                cursor: move;
            }
            
            .ccc-field-repeater .ccc-repeater-item-title {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
                font-weight: 600;
                color: #1d2327;
            }
            
            .ccc-field-repeater .ccc-drag-handle {
                color: #c3c4c7;
                cursor: grab;
                transition: color 0.2s ease;
            }
            
            .ccc-field-repeater .ccc-drag-handle:hover {
                color: #0073aa;
            }
            
            .ccc-field-repeater .ccc-repeater-item-controls {
                display: flex;
                gap: 4px;
            }
            
            .ccc-field-repeater .ccc-repeater-toggle,
            .ccc-field-repeater .ccc-repeater-remove {
                padding: 6px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                transition: all 0.2s ease;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .ccc-field-repeater .ccc-repeater-toggle {
                background: #0073aa;
                color: white;
            }
            
            .ccc-field-repeater .ccc-repeater-toggle:hover {
                background: #005a87;
            }
            
            .ccc-field-repeater .ccc-repeater-remove {
                background: #d63638;
                color: white;
            }
            
            .ccc-field-repeater .ccc-repeater-remove:hover {
                background: #b32d2e;
            }
            
            .ccc-field-repeater .ccc-repeater-toggle .dashicons,
            .ccc-field-repeater .ccc-repeater-remove .dashicons {
                width: 16px;
                height: 16px;
                font-size: 16px;
            }
            
            .ccc-field-repeater .ccc-repeater-item-content {
                padding: 20px;
            }
            
            .ccc-field-repeater .ccc-repeater-item-fields {
                display: grid;
                gap: 16px;
            }
            
            .ccc-field-repeater .ccc-nested-field {
                display: flex;
                flex-direction: column;
                gap: 6px;
            }
            
            .ccc-field-repeater .ccc-nested-field-label {
                font-weight: 600;
                color: #1d2327;
                font-size: 13px;
            }
            
            .ccc-field-repeater .ccc-nested-field-input {
                padding: 8px 12px;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                font-size: 13px;
                transition: border-color 0.2s ease;
            }
            
            .ccc-field-repeater .ccc-nested-field-input:focus {
                outline: none;
                border-color: #0073aa;
                box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
            }
            
            .ccc-field-repeater .ccc-field-label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: #1d2327;
                font-size: 14px;
            }
            
            .ccc-field-repeater .ccc-required {
                color: #d63638;
                margin-left: 3px;
            }
        </style>';
    }
}
