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
                <?php 
                if (empty($repeater_value)) {
                    // Always show one empty item if none exist
                    $this->renderRepeaterItem(0, [], $nested_field_definitions);
                } else {
                    foreach ($repeater_value as $item_index => $item_data) {
                        $this->renderRepeaterItem($item_index, $item_data, $nested_field_definitions);
                    }
                }
                ?>
            </div>
            
            <input type="hidden" 
                   class="ccc-repeater-main-input"
                   id="<?php echo esc_attr($this->getFieldId()); ?>"
                   name="<?php echo esc_attr($this->getFieldName()); ?>"
                   value="<?php echo esc_attr($this->field_value ?: '[]'); ?>" />
        </div>
        <script>
        jQuery(document).ready(function($) {
            // Use a single shared media frame for all image fields (including nested)
            var cccMediaFrame = null;

            function openCCCFrame($field, $input, returnType, $btn) {
                if (!cccMediaFrame) {
                    cccMediaFrame = wp.media({
                        title: 'Select or Upload an Image',
                        button: { text: 'Use this image' },
                        multiple: false,
                        library: { type: 'image' }
                    });
                }
                // Remove all previous handlers before binding new ones
                cccMediaFrame.off('select');
                cccMediaFrame.off('open');

                cccMediaFrame.on('select', function() {
                    var attachment = cccMediaFrame.state().get('selection').first().toJSON();
                    var imageUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                    if ($btn && $btn.hasClass('ccc-upload-image-btn')) {
                        var previewHtml = '<div class="ccc-image-preview">' +
                            '<img src="' + imageUrl + '" alt="Selected image" style="max-width: 150px; height: auto; display: block; margin: 0 auto;" />' +
                            '<div class="ccc-image-overlay">' +
                                '<button type="button" class="ccc-change-image-btn" data-field-id="' + $btn.data('field-id') + '" data-instance-id="' + $btn.data('instance-id') + '" data-return-type="' + returnType + '"><span class="dashicons dashicons-edit"></span>Change Image</button>' +
                                '<button type="button" class="ccc-remove-image-btn" data-field-id="' + $btn.data('field-id') + '" data-instance-id="' + $btn.data('instance-id') + '" data-return-type="' + returnType + '"><span class="dashicons dashicons-trash"></span>Remove</button>' +
                            '</div>' +
                        '</div>';
                        $field.find('.ccc-image-upload-area').html(previewHtml).removeClass('no-image').addClass('has-image');
                    } else {
                        $field.find('img').attr('src', imageUrl);
                    }
                    if (returnType === 'url') {
                        $input.val(imageUrl);
                    } else {
                        var imageData = {
                            id: attachment.id,
                            url: imageUrl,
                            alt: attachment.alt,
                            title: attachment.title,
                            caption: attachment.caption,
                            description: attachment.description
                        };
                        $input.val(JSON.stringify(imageData));
                    }
                    $field.find('.ccc-image-upload-area').removeClass('no-image').addClass('has-image');
                    cccMediaFrame.close();
                });

                // Always clear previous selection on open
                cccMediaFrame.on('open', function() {
                    var selection = cccMediaFrame.state().get('selection');
                    selection.reset();
                });

                cccMediaFrame.open();
            }

            // Change Image
            $(document).on('click', '.ccc-change-image-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $field = $btn.closest('.ccc-image-field');
                var $input = $field.find('.ccc-image-field-input');
                var returnType = $btn.data('return-type');
                openCCCFrame($field, $input, returnType, $btn);
            });

            // First time image selection
            $(document).on('click', '.ccc-upload-image-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $field = $btn.closest('.ccc-image-field');
                var $input = $field.find('.ccc-image-field-input');
                var returnType = $btn.data('return-type');
                openCCCFrame($field, $input, returnType, $btn);
            });

            $(document).on('click', '.ccc-remove-image-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $field = $btn.closest('.ccc-image-field');
                var $input = $field.find('.ccc-image-field-input');
                $input.val('');
                // Restore the placeholder HTML
                var placeholderHtml = '<div class="ccc-image-placeholder">' +
                    '<div class="ccc-upload-icon"><span class="dashicons dashicons-cloud-upload"></span></div>' +
                    '<h4>Upload an Image</h4>' +
                    '<p>Click to select an image from your media library</p>' +
                    '<button type="button" class="ccc-upload-image-btn button button-primary" data-field-id="' + $btn.data('field-id') + '" data-instance-id="' + $btn.data('instance-id') + '" data-return-type="' + $btn.data('return-type') + '">Select Image</button>' +
                '</div>';
                $field.find('.ccc-image-upload-area').html(placeholderHtml).removeClass('has-image').addClass('no-image');
            });
        });
        </script>
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
                        // Ensure config is always set
                        if (!isset($nested_field['config']) || !is_array($nested_field['config'])) {
                            $nested_field['config'] = [];
                        }
                        $nested_field_config = $nested_field['config'];
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
            case 'image':
                $return_type = isset($field_config['config']['return_type']) ? $field_config['config']['return_type'] : 'url';
                $image_src = '';
                $image_id = '';
                if (!empty($value)) {
                    if ($return_type === 'url') {
                        $image_src = $value;
                        $image_id = attachment_url_to_postid($image_src);
                    } else if ($return_type === 'array' && is_string($value)) {
                        $image_data = json_decode($value, true);
                        $image_src = isset($image_data['url']) ? $image_data['url'] : '';
                        $image_id = isset($image_data['id']) ? $image_data['id'] : '';
                    }
                }
                // If we have an image ID, get the medium size URL
                if ($image_id) {
                    $medium = wp_get_attachment_image_src($image_id, 'medium');
                    if ($medium && isset($medium[0])) {
                        $image_src = $medium[0];
                    }
                }
                $field_id = $field_config['name'];
                $instance_id = uniqid('nestedimg_');
                echo '<div class="ccc-image-field ccc-nested-image-field">';
                echo '<input type="hidden" class="ccc-image-field-input ccc-nested-field-input" id="' . esc_attr($instance_id) . '" data-nested-field-type="image" data-return-type="' . esc_attr($return_type) . '" value="' . esc_attr($value) . '" />';
                echo '<div class="ccc-image-upload-area ' . ($image_src ? 'has-image' : 'no-image') . '">';
                if ($image_src) {
                    echo '<div class="ccc-image-preview">';
                    echo '<img src="' . esc_url($image_src) . '" alt="Selected image" style="max-width: 150px; height: auto; display: block; margin: 0 auto;" />';
                    echo '<div class="ccc-image-overlay">';
                    echo '<button type="button" class="ccc-change-image-btn" data-field-id="' . esc_attr($field_id) . '" data-instance-id="' . esc_attr($instance_id) . '" data-return-type="' . esc_attr($return_type) . '"><span class="dashicons dashicons-edit"></span>Change Image</button>';
                    echo '<button type="button" class="ccc-remove-image-btn" data-field-id="' . esc_attr($field_id) . '" data-instance-id="' . esc_attr($instance_id) . '" data-return-type="' . esc_attr($return_type) . '"><span class="dashicons dashicons-trash"></span>Remove</button>';
                    echo '</div>';
                    echo '</div>';
                } else {
                    echo '<div class="ccc-image-placeholder">';
                    echo '<div class="ccc-upload-icon"><span class="dashicons dashicons-cloud-upload"></span></div>';
                    echo '<h4>Upload an Image</h4>';
                    echo '<p>Click to select an image from your media library</p>';
                    echo '<button type="button" class="ccc-upload-image-btn button button-primary" data-field-id="' . esc_attr($field_id) . '" data-instance-id="' . esc_attr($instance_id) . '" data-return-type="' . esc_attr($return_type) . '">Select Image</button>';
                    echo '</div>';
                }
                echo '</div>';
                echo '</div>';
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
