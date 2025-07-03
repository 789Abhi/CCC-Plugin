<?php

namespace CCC\Admin\MetaBoxFields;

defined('ABSPATH') || exit;

class ImageFieldRenderer extends BaseFieldRenderer {
    public function render() {
        $config = $this->getFieldConfig();
        $image_return_type = $config['return_type'] ?? 'url';
        $required = $this->field->getRequired() ? 'required' : '';
        
        $image_src = '';
        $image_id = '';
        if ($this->field_value) {
            if ($image_return_type === 'url') {
                $image_src = $this->field_value;
                // Try to get image ID from URL
                $image_id = attachment_url_to_postid($image_src);
            } else {
                $decoded_value = json_decode($this->field_value, true);
                $image_src = $decoded_value['url'] ?? '';
                $image_id = $decoded_value['id'] ?? '';
            }
        }
        // If we have an image ID, get the medium size URL
        if ($image_id) {
            $medium = wp_get_attachment_image_src($image_id, 'medium');
            if ($medium && isset($medium[0])) {
                $image_src = $medium[0];
            }
        }
        
        ob_start();
        ?>
        <div class="ccc-image-field">
            <input type="hidden" 
                   class="ccc-image-field-input"
                   id="<?php echo esc_attr($this->getFieldId()); ?>"
                   name="<?php echo esc_attr($this->getFieldName()); ?>"
                   value="<?php echo esc_attr($this->field_value); ?>"
                   data-return-type="<?php echo esc_attr($image_return_type); ?>"
                   <?php echo $required; ?> />
            
            <div class="ccc-image-upload-area <?php echo $image_src ? 'has-image' : 'no-image'; ?>">
                <?php if ($image_src): ?>
                    <div class="ccc-image-preview">
                        <img src="<?php echo esc_url($image_src); ?>" alt="Selected image" style="max-width: 300px; height: auto; display: block; margin: 0 auto;" />
                        <div class="ccc-image-overlay">
                            <button type="button" class="ccc-change-image-btn" data-field-id="<?php echo esc_attr($this->field->getId()); ?>" data-instance-id="<?php echo esc_attr($this->instance_id); ?>" data-return-type="<?php echo esc_attr($image_return_type); ?>">
                                <span class="dashicons dashicons-edit"></span>
                                Change Image
                            </button>
                            <button type="button" class="ccc-remove-image-btn" data-field-id="<?php echo esc_attr($this->field->getId()); ?>" data-instance-id="<?php echo esc_attr($this->instance_id); ?>" data-return-type="<?php echo esc_attr($image_return_type); ?>">
                                <span class="dashicons dashicons-trash"></span>
                                Remove
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="ccc-image-placeholder">
                        <div class="ccc-upload-icon">
                            <span class="dashicons dashicons-cloud-upload"></span>
                        </div>
                        <h4>Upload an Image</h4>
                        <p>Click to select an image from your media library</p>
                        <button type="button" class="ccc-upload-image-btn button button-primary" data-field-id="<?php echo esc_attr($this->field->getId()); ?>" data-instance-id="<?php echo esc_attr($this->instance_id); ?>" data-return-type="<?php echo esc_attr($image_return_type); ?>">
                            Select Image
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="ccc-image-preview-container" id="ccc_image_preview_<?php echo esc_attr($this->instance_id . '_' . $this->field->getId()); ?>"></div>
        </div>
        <?php
        // Add inline JS for change/remove buttons using event delegation
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Use event delegation for dynamically loaded fields
            $(document).on('click', '.ccc-change-image-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $field = $btn.closest('.ccc-image-field');
                var $input = $field.find('.ccc-image-field-input');
                var returnType = $btn.data('return-type');
                var frame = wp.media({
                    title: 'Select or Upload an Image',
                    button: { text: 'Use this image' },
                    multiple: false,
                    library: { type: 'image' }
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    var imageUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                    $field.find('img').attr('src', imageUrl);
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
                    // Update UI
                    $field.find('.ccc-image-upload-area').removeClass('no-image').addClass('has-image');
                    frame.close();
                });
                frame.open();
            });
            // Handle first time image selection
            $(document).on('click', '.ccc-upload-image-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $field = $btn.closest('.ccc-image-field');
                var $input = $field.find('.ccc-image-field-input');
                var returnType = $btn.data('return-type');
                var frame = wp.media({
                    title: 'Select or Upload an Image',
                    button: { text: 'Use this image' },
                    multiple: false,
                    library: { type: 'image' }
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    var imageUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                    // If there is no preview img yet, create it
                    var previewHtml = '<div class="ccc-image-preview">' +
                        '<img src="' + imageUrl + '" alt="Selected image" style="max-width: 300px; height: auto; display: block; margin: 0 auto;" />' +
                        '<div class="ccc-image-overlay">' +
                            '<button type="button" class="ccc-change-image-btn" data-field-id="' + $btn.data('field-id') + '" data-instance-id="' + $btn.data('instance-id') + '" data-return-type="' + returnType + '"><span class="dashicons dashicons-edit"></span>Change Image</button>' +
                            '<button type="button" class="ccc-remove-image-btn" data-field-id="' + $btn.data('field-id') + '" data-instance-id="' + $btn.data('instance-id') + '" data-return-type="' + returnType + '"><span class="dashicons dashicons-trash"></span>Remove</button>' +
                        '</div>' +
                    '</div>';
                    $field.find('.ccc-image-upload-area').html(previewHtml).removeClass('no-image').addClass('has-image');
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
                    frame.close();
                });
                frame.open();
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

    protected function renderFieldStyles() {
        return '
        <style>
            .ccc-field-image .ccc-image-field {
                position: relative;
            }
            
            .ccc-field-image .ccc-image-upload-area {
                border: 2px dashed #e1e5e9;
                border-radius: 8px;
                transition: all 0.3s ease;
                position: relative;
                overflow: hidden;
            }
            
            .ccc-field-image .ccc-image-upload-area.no-image {
                padding: 40px 20px;
                text-align: center;
                background: #fafafa;
            }
            
            .ccc-field-image .ccc-image-upload-area.no-image:hover {
                border-color: #0073aa;
                background: #f0f6fc;
            }
            
            .ccc-field-image .ccc-image-upload-area.has-image {
                border: 2px solid #e1e5e9;
                border-radius: 8px;
                overflow: hidden;
            }
            
            .ccc-field-image .ccc-image-placeholder {
                color: #646970;
            }
            
            .ccc-field-image .ccc-upload-icon {
                font-size: 48px;
                color: #c3c4c7;
                margin-bottom: 16px;
            }
            
            .ccc-field-image .ccc-upload-icon .dashicons {
                width: 48px;
                height: 48px;
                font-size: 48px;
            }
            
            .ccc-field-image .ccc-image-placeholder h4 {
                margin: 0 0 8px 0;
                font-size: 16px;
                font-weight: 600;
                color: #1d2327;
            }
            
            .ccc-field-image .ccc-image-placeholder p {
                margin: 0 0 20px 0;
                font-size: 14px;
                color: #646970;
            }
            
            .ccc-field-image .ccc-image-preview {
                position: relative;
                display: inline-block;
            }
            
            .ccc-field-image .ccc-image-preview img {
                width: 100%;
                height: auto;
                max-height: 300px;
                object-fit: cover;
                display: block;
            }
            
            .ccc-field-image .ccc-image-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.7);
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            
            .ccc-field-image .ccc-image-preview:hover .ccc-image-overlay {
                opacity: 1;
            }
            
            .ccc-field-image .ccc-change-image-btn,
            .ccc-field-image .ccc-remove-image-btn {
                padding: 8px 16px;
                border: none;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s ease;
                display: flex;
                align-items: center;
                gap: 6px;
            }
            
            .ccc-field-image .ccc-change-image-btn {
                background: #0073aa;
                color: white;
            }
            
            .ccc-field-image .ccc-change-image-btn:hover {
                background: #005a87;
            }
            
            .ccc-field-image .ccc-remove-image-btn {
                background: #d63638;
                color: white;
            }
            
            .ccc-field-image .ccc-remove-image-btn:hover {
                background: #b32d2e;
            }
            
            .ccc-field-image .ccc-change-image-btn .dashicons,
            .ccc-field-image .ccc-remove-image-btn .dashicons {
                width: 16px;
                height: 16px;
                font-size: 16px;
            }
            
            .ccc-field-image .ccc-field-label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: #1d2327;
                font-size: 14px;
            }
            
            .ccc-field-image .ccc-required {
                color: #d63638;
                margin-left: 3px;
            }
        </style>';
    }
}
