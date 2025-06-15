<?php
namespace CCC\Fields;

defined('ABSPATH') || exit;

class VideoField extends BaseField {
    public function render($post_id, $instance_id, $value = '') {
        $field_id = "ccc_field_{$this->name}_{$instance_id}";
        $field_name = "ccc_field_values[{$this->component_id}][{$instance_id}][{$this->name}]";
        
        $required = $this->required ? 'required' : '';
        $placeholder = esc_attr($this->placeholder ?: 'Enter video URL (YouTube, Vimeo, etc.)');
        
        // Ensure WordPress media scripts are loaded
        wp_enqueue_media();
        wp_enqueue_script('jquery');
        
        ob_start();
        ?>
        <div class="ccc-field ccc-field-video">
            <label for="<?php echo esc_attr($field_id); ?>" class="ccc-field-label">
                <?php echo esc_html($this->label); ?>
                <?php if ($this->required): ?>
                    <span class="ccc-required">*</span>
                <?php endif; ?>
            </label>
            
            <div class="ccc-video-input-wrapper">
                <input 
                    type="url" 
                    id="<?php echo esc_attr($field_id); ?>" 
                    name="<?php echo esc_attr($field_name); ?>" 
                    class="ccc-video-input" 
                    value="<?php echo esc_attr($value); ?>"
                    placeholder="<?php echo $placeholder; ?>"
                    <?php echo $required; ?>
                >
                <button type="button" class="button ccc-video-upload-button">Upload Video</button>
            </div>
            
            <?php if (!empty($value)): ?>
                <div class="ccc-video-preview" id="<?php echo esc_attr($field_id); ?>_preview">
                    <?php echo wp_oembed_get($value, array('width' => 400)); ?>
                </div>
            <?php else: ?>
                <div class="ccc-video-preview" id="<?php echo esc_attr($field_id); ?>_preview" style="display: none;"></div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Handle video URL input change
            $('#<?php echo esc_attr($field_id); ?>').on('change', function() {
                var videoUrl = $(this).val();
                var $preview = $('#<?php echo esc_attr($field_id); ?>_preview');
                
                if (videoUrl) {
                    // Use WordPress AJAX to get the oembed HTML
                    $.post(ajaxurl, {
                        action: 'ccc_get_video_embed',
                        url: videoUrl,
                        nonce: '<?php echo wp_create_nonce('ccc_video_embed'); ?>'
                    }, function(response) {
                        if (response.success && response.data) {
                            $preview.html(response.data).show();
                        } else {
                            $preview.html('<p class="ccc-error">Invalid video URL or unable to embed.</p>').show();
                        }
                    });
                } else {
                    $preview.empty().hide();
                }
            });
            
            // Handle upload button
            $('.ccc-video-upload-button').on('click', function(e) {
                e.preventDefault();
                
                var $input = $('#<?php echo esc_attr($field_id); ?>');
                
                var frame = wp.media({
                    title: 'Select or Upload a Video',
                    button: {
                        text: 'Use this video'
                    },
                    multiple: false,
                    library: {
                        type: 'video'
                    }
                });
                
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $input.val(attachment.url).trigger('change');
                });
                
                frame.open();
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
