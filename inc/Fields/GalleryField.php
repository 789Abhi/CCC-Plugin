<?php

namespace CCC\Fields;

class GalleryField extends BaseField {
    
    protected $max_images = 0;
    protected $min_images = 0;
    protected $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    protected $show_preview = true;
    protected $preview_size = 'medium';
    protected $field_type = 'gallery';
    
    public function __construct($label, $name, $component_id, $required = false, $placeholder = '', $config = '') {
        parent::__construct($label, $name, $component_id, $required, $placeholder, $config);
        
        // Parse field configuration
        $field_config = is_string($config) ? json_decode($config, true) : $config;
        
        $this->max_images = isset($field_config['max_images']) ? intval($field_config['max_images']) : 0;
        $this->min_images = isset($field_config['min_images']) ? intval($field_config['min_images']) : 0;
        $this->allowed_types = isset($field_config['allowed_types']) ? $field_config['allowed_types'] : ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $this->show_preview = isset($field_config['show_preview']) ? $field_config['show_preview'] : true;
        $this->preview_size = isset($field_config['preview_size']) ? $field_config['preview_size'] : 'medium';
    }
    
    public function render($post_id, $instance_id, $value = '') {
        $field_id = 'ccc-gallery-' . uniqid();
        $field_name = $this->name;
        $field_value = $value ?: $this->getValue();
        $field_config = json_encode([
            'max_images' => $this->max_images,
            'min_images' => $this->min_images,
            'allowed_types' => $this->allowed_types,
            'show_preview' => $this->show_preview,
            'preview_size' => $this->preview_size,
            'multiple' => $this->max_images !== 1
        ]);
        
        ob_start();
        ?>
        <div class="ccc-field ccc-gallery-field" data-field-name="<?php echo esc_attr($field_name); ?>">
            <input type="hidden" 
                   name="<?php echo esc_attr($field_name); ?>" 
                   id="<?php echo esc_attr($field_id); ?>" 
                   value="<?php echo esc_attr($field_value); ?>" />
            <div id="<?php echo esc_attr($field_id); ?>-container" 
                 data-field-config="<?php echo esc_attr($field_config); ?>"
                 data-field-value="<?php echo esc_attr($field_value); ?>">
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.CCCGalleryField && typeof window.CCCGalleryField.init === 'function') {
                window.CCCGalleryField.init('<?php echo esc_js($field_id); ?>-container');
            }
        });
        </script>
        <?php
        $field_content = ob_get_clean();
        
        // Check PRO access for gallery field
        $pro_access_service = new \CCC\Services\ProFieldAccessService();
        $access_check = $pro_access_service->can_access_field('gallery');
        
        if (!$access_check['canAccess']) {
            return $pro_access_service->render_pro_field_disabled('gallery', $this->label, $field_content);
        }
        
        return $field_content;
    }
    
    public function save() {
        // Implementation for saving the field
        return true;
    }
    
    public function sanitize($value) {
        error_log("CCC GalleryField: sanitize called with value type: " . gettype($value));
        error_log("CCC GalleryField: sanitize called with value: " . (is_string($value) ? $value : json_encode($value)));
        
        if (empty($value)) {
            error_log("CCC GalleryField: Empty value, returning empty string");
            return '';
        }
        
        // Handle JSON array format or comma-separated values
        if (is_string($value)) {
            if (strpos($value, '[') === 0) {
                $media_data = json_decode($value, true);
                error_log("CCC GalleryField: Decoded JSON string to: " . json_encode($media_data));
            } else {
                $media_data = explode(',', $value);
                error_log("CCC GalleryField: Exploded comma-separated string to: " . json_encode($media_data));
            }
        } else {
            $media_data = $value;
            error_log("CCC GalleryField: Using value as-is: " . json_encode($media_data));
        }
        
        // Process media data - keep full image objects for better functionality
        $processed_media = [];
        foreach ($media_data as $item) {
            if (is_array($item) && isset($item['id'])) {
                // Full image object - validate and keep it (including disabled ones for UI persistence)
                $media_id = intval($item['id']);
                $attachment = get_post($media_id);
                
                if ($attachment && $attachment->post_type === 'attachment') {
                    // Validate mime type
                    $mime_type = get_post_mime_type($media_id);
                    if (in_array($mime_type, $this->allowed_types)) {
                        // Ensure we have all necessary properties
                        $processed_item = [
                            'id' => $media_id,
                            'title' => $attachment->post_title ?: $item['title'] ?: basename(get_attached_file($media_id)),
                            'filename' => basename(get_attached_file($media_id)) ?: $item['filename'],
                            'url' => wp_get_attachment_url($media_id) ?: $item['url'],
                            'thumbnail' => wp_get_attachment_image_url($media_id, 'thumbnail') ?: $item['thumbnail'],
                            'medium' => wp_get_attachment_image_url($media_id, 'medium') ?: $item['medium'],
                            'large' => wp_get_attachment_image_url($media_id, 'large') ?: $item['large'],
                            'alt' => get_post_meta($media_id, '_wp_attachment_image_alt', true) ?: $item['alt'],
                            'caption' => $attachment->post_excerpt ?: $item['caption'],
                            'description' => $attachment->post_content ?: $item['description'],
                            'mime_type' => $mime_type,
                            'filesize' => filesize(get_attached_file($media_id)) ?: $item['filesize'],
                            'filesizeHumanReadable' => size_format(filesize(get_attached_file($media_id))) ?: $item['filesizeHumanReadable'],
                            'date' => $attachment->post_date,
                            'modified' => $attachment->post_modified,
                            'enabled' => isset($item['enabled']) ? $item['enabled'] : true, // Preserve enabled state
                            'dragKey' => isset($item['dragKey']) ? $item['dragKey'] : $media_id . '_' . time() . '_' . rand()
                        ];
                        $processed_media[] = $processed_item;
                        error_log("CCC GalleryField: Processed image object for ID: " . $media_id . " (enabled: " . ($processed_item['enabled'] ? 'true' : 'false') . ")");
                    } else {
                        error_log("CCC GalleryField: Skipping image with invalid mime type: " . $mime_type);
                    }
                } else {
                    error_log("CCC GalleryField: Skipping invalid attachment ID: " . $media_id);
                }
            } elseif (is_numeric($item)) {
                // Direct media ID - convert to full object
                $media_id = intval($item);
                $attachment = get_post($media_id);
                
                if ($attachment && $attachment->post_type === 'attachment') {
                    $mime_type = get_post_mime_type($media_id);
                    if (in_array($mime_type, $this->allowed_types)) {
                        $file_path = get_attached_file($media_id);
                        $file_size = $file_path ? filesize($file_path) : 0;
                        
                        $processed_item = [
                            'id' => $media_id,
                            'title' => $attachment->post_title ?: basename($file_path),
                            'filename' => basename($file_path),
                            'url' => wp_get_attachment_url($media_id),
                            'thumbnail' => wp_get_attachment_image_url($media_id, 'thumbnail'),
                            'medium' => wp_get_attachment_image_url($media_id, 'medium'),
                            'large' => wp_get_attachment_image_url($media_id, 'large'),
                            'alt' => get_post_meta($media_id, '_wp_attachment_image_alt', true),
                            'caption' => $attachment->post_excerpt,
                            'description' => $attachment->post_content,
                            'mime_type' => $mime_type,
                            'filesize' => $file_size,
                            'filesizeHumanReadable' => $file_size ? size_format($file_size) : 'Unknown',
                            'date' => $attachment->post_date,
                            'modified' => $attachment->post_modified
                        ];
                        $processed_media[] = $processed_item;
                        error_log("CCC GalleryField: Converted ID to full image object: " . $media_id);
                    } else {
                        error_log("CCC GalleryField: Skipping image with invalid mime type: " . $mime_type);
                    }
                } else {
                    error_log("CCC GalleryField: Skipping invalid attachment ID: " . $media_id);
                }
            } else {
                error_log("CCC GalleryField: Skipping invalid item: " . json_encode($item));
            }
        }
        
        // Allow duplicates - don't remove based on ID to allow same image multiple times
        $unique_media = $processed_media;
        
        error_log("CCC GalleryField: Final processed media count: " . count($unique_media));
        error_log("CCC GalleryField: All images (enabled and disabled, including duplicates) saved to database for UI persistence");
        
        // Validate against min/max limits
        if ($this->min_images > 0 && count($unique_media) < $this->min_images) {
            return new \WP_Error('min_images', sprintf(
                'Minimum %d images required, but only %d selected.',
                $this->min_images,
                count($unique_media)
            ));
        }
        
        if ($this->max_images > 0 && count($unique_media) > $this->max_images) {
            return new \WP_Error('max_images', sprintf(
                'Maximum %d images allowed, but %d selected.',
                $this->max_images,
                count($unique_media)
            ));
        }
        
        // Return as JSON string for database storage (full image objects)
        $result = json_encode($unique_media);
        error_log("CCC GalleryField: Returning sanitized result with " . count($unique_media) . " images");
        return $result;
    }
    
    public function getFieldConfig() {
        return [
            'max_images' => [
                'type' => 'number',
                'label' => 'Maximum Images',
                'description' => 'Maximum number of images allowed in the gallery. Set to 0 for unlimited.',
                'default' => 0,
                'min' => 0
            ],
            'min_images' => [
                'type' => 'number',
                'label' => 'Minimum Images',
                'description' => 'Minimum number of images required in the gallery.',
                'default' => 0,
                'min' => 0
            ],
            'allowed_types' => [
                'type' => 'checkbox',
                'label' => 'Allowed Image Types',
                'description' => 'Select which image types are allowed in the gallery.',
                'options' => [
                    'image/jpeg' => 'JPEG',
                    'image/png' => 'PNG',
                    'image/gif' => 'GIF',
                    'image/webp' => 'WebP',
                    'image/svg+xml' => 'SVG'
                ],
                'default' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
            ],
            'show_preview' => [
                'type' => 'checkbox',
                'label' => 'Show Preview',
                'description' => 'Display image previews in the gallery.',
                'default' => true
            ],
            'preview_size' => [
                'type' => 'select',
                'label' => 'Preview Size',
                'description' => 'Size of the preview images.',
                'options' => [
                    'thumbnail' => 'Thumbnail',
                    'medium' => 'Medium',
                    'large' => 'Large',
                    'full' => 'Full Size'
                ],
                'default' => 'medium'
            ]
        ];
    }
    
    public function getValue($post_id = null, $instance_id = null) {
        $post_id = $post_id ?: get_the_ID();
        $instance_id = $instance_id ?: '';
        
        $field_value = \CCC\Models\FieldValue::getValue($this->id, $post_id, $instance_id);
        
        if (empty($field_value)) {
            return [];
        }
        
        // Parse JSON value
        if (is_string($field_value)) {
            $media_data = json_decode($field_value, true);
        } else {
            $media_data = $field_value;
        }
        
        if (!is_array($media_data)) {
            return [];
        }
        
        // Return all images (enabled and disabled) for UI purposes
        return $media_data;
    }
    
    public function getEnabledValue($post_id = null, $instance_id = null) {
        $post_id = $post_id ?: get_the_ID();
        $instance_id = $instance_id ?: '';
        
        $field_value = \CCC\Models\FieldValue::getValue($this->id, $post_id, $instance_id);
        
        if (empty($field_value)) {
            return [];
        }
        
        // Parse JSON value
        if (is_string($field_value)) {
            $media_data = json_decode($field_value, true);
        } else {
            $media_data = $field_value;
        }
        
        if (!is_array($media_data)) {
            return [];
        }
        
        // Filter only enabled images for template rendering
        $enabled_images = [];
        foreach ($media_data as $image) {
            if (is_array($image) && (!isset($image['enabled']) || $image['enabled'] === true)) {
                $enabled_images[] = $image;
            }
        }
        
        return $enabled_images;
    }
    
    public function getAvailableMimeTypes() {
        return [
            'image/jpeg' => 'JPEG',
            'image/png' => 'PNG',
            'image/gif' => 'GIF',
            'image/webp' => 'WebP',
            'image/svg+xml' => 'SVG'
        ];
    }
    
    public function validate($value) {
        $errors = [];
        
        if ($this->required && empty($value)) {
            $errors[] = sprintf('Field "%s" is required.', $this->label);
        }
        
        if (!empty($value)) {
            $media_ids = is_string($value) ? json_decode($value, true) : $value;
            
            if ($this->min_images > 0 && count($media_ids) < $this->min_images) {
                $errors[] = sprintf(
                    'Field "%s" requires at least %d images, but only %d provided.',
                    $this->label,
                    $this->min_images,
                    count($media_ids)
                );
            }
            
            if ($this->max_images > 0 && count($media_ids) > $this->max_images) {
                $errors[] = sprintf(
                    'Field "%s" allows maximum %d images, but %d provided.',
                    $this->label,
                    $this->max_images,
                    count($media_ids)
                );
            }
        }
        
        return empty($errors) ? true : $errors;
    }
}

