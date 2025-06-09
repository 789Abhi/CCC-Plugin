<?php
namespace CCC\Fields;

defined('ABSPATH') || exit;

class ImageField extends BaseField {
    private $return_type = 'url';
    
    public function __construct($label, $name, $component_id, $required = false, $placeholder = '', $config = []) {
        parent::__construct($label, $name, $component_id, $required, $placeholder);
        
        if (is_string($config)) {
            $config = json_decode($config, true);
        }
        
        $this->return_type = isset($config['return_type']) ? $config['return_type'] : 'url';
    }
    
    public function render($post_id, $instance_id, $value = '') {
        $field_id = "ccc_field_{$this->name}_{$instance_id}";
        $field_name = "ccc_field_values[{$this->component_id}][{$instance_id}][{$this->name}]";
        
        $required = $this->required ? 'required' : '';
        
        // Handle array values for full image data
        $image_id = 0;
        $image_url = '';
        
        if (!empty($value)) {
            if ($this->return_type === 'array' && is_string($value)) {
                $value = json_decode($value, true);
            }
            
            if (is_array($value)) {
                $image_id = isset($value['id']) ? (int)$value['id'] : 0;
                $image_url = isset($value['url']) ? $value['url'] : '';
            } else {
                $image_url = $value;
                // Try to get image ID from URL
                $image_id = attachment_url_to_postid($image_url);
            }
        }
        
        // Ensure WordPress media scripts are loaded
        wp_enqueue_media();
        wp_enqueue_script('jquery');
        
        ob_start();
        ?>
        <div class="ccc-field ccc-field-image">
            <label for="<?php echo esc_attr($field_id); ?>" class="ccc-field-label">
                <?php echo esc_html($this->label); ?>
                <?php if ($this->required): ?>
                    <span class="ccc-required">*</span>
                <?php endif; ?>
            </label>
            
            <div class="ccc-image-field-container" data-return-type="<?php echo esc_attr($this->return_type); ?>">
                <div class="ccc-image-preview-wrapper">
                    <?php if (!empty($image_url)): ?>
                        <div class="ccc-image-preview">
                            <img src="<?php echo esc_url($image_url); ?>" alt="">
                            <button type="button" class="ccc-remove-image" title="Remove image">&times;</button>
                        </div>
                    <?php else: ?>
                        <div class="ccc-image-placeholder">
                            <span>No image selected</span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="ccc-image-actions">
                    <button type="button" class="button ccc-upload-image">Select Image</button>
                </div>
                
                <?php if ($this->return_type === 'array'): ?>
                    <input 
                        type="hidden" 
                        name="<?php echo esc_attr($field_name); ?>" 
                        value="<?php echo esc_attr(json_encode($value)); ?>"
                        <?php echo $required; ?>
                    >
                    <input type="hidden" class="ccc-image-id" value="<?php echo esc_attr($image_id); ?>">
                <?php else: ?>
                    <input 
                        type="hidden" 
                        name="<?php echo esc_attr($field_name); ?>" 
                        value="<?php echo esc_attr($image_url); ?>"
                        <?php echo $required; ?>
                    >
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var $container = $('.ccc-field-image .ccc-image-field-container').last();
            var $valueInput = $container.find('input[name="<?php echo esc_attr($field_name); ?>"]');
            var $idInput = $container.find('.ccc-image-id');
            var returnType = $container.data('return-type');
            
            // Handle upload button
            $container.on('click', '.ccc-upload-image', function(e) {
                e.preventDefault();
                
                var frame = wp.media({
                    title: 'Select or Upload an Image',
                    button: {
                        text: 'Use this image'
                    },
                    multiple: false,
                    library: {
                        type: 'image'
                    }
                });
                
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    
                    // Update preview
                    var $preview = $('<div class="ccc-image-preview">' +
                        '<img src="' + attachment.url + '" alt="">' +
                        '<button type="button" class="ccc-remove-image" title="Remove image">&times;</button>' +
                        '</div>');
                    
                    $container.find('.ccc-image-preview-wrapper').empty().append($preview);
                    
                    // Update value based on return type
                    if (returnType === 'array') {
                        var imageData = {
                            id: attachment.id,
                            url: attachment.url,
                            alt: attachment.alt,
                            title: attachment.title,
                            caption: attachment.caption,
                            description: attachment.description
                        };
                        $valueInput.val(JSON.stringify(imageData));
                        $idInput.val(attachment.id);
                    } else {
                        $valueInput.val(attachment.url);
                    }
                });
                
                frame.open();
            });
            
            // Handle remove button
            $container.on('click', '.ccc-remove-image', function(e) {
                e.preventDefault();
                
                // Clear preview
                var $placeholder = $('<div class="ccc-image-placeholder"><span>No image selected</span></div>');
                $container.find('.ccc-image-preview-wrapper').empty().append($placeholder);
                
                // Clear value
                $valueInput.val('');
                $idInput.val('');
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function save() {
        // Implementation for saving the field
        return true;
    }
}
