<?php
namespace CCC\Fields;

defined('ABSPATH') || exit;

class OEmbedField extends BaseField {
    public function render($post_id, $instance_id, $value = '') {
        $field_id = "ccc_field_{$this->name}_{$instance_id}";
        $field_name = "ccc_field_values[{$this->component_id}][{$instance_id}][{$this->name}]";
        
        $required = $this->required ? 'required' : '';
        $placeholder = esc_attr($this->placeholder ?: 'Enter URL to embed (Twitter, Instagram, etc.)');
        
        wp_enqueue_script('jquery');
        
        ob_start();
        ?>
        <div class="ccc-field ccc-field-oembed">
            <label for="<?php echo esc_attr($field_id); ?>" class="ccc-field-label">
                <?php echo esc_html($this->label); ?>
                <?php if ($this->required): ?>
                    <span class="ccc-required">*</span>
                <?php endif; ?>
            </label>
            
            <input 
                type="url" 
                id="<?php echo esc_attr($field_id); ?>" 
                name="<?php echo esc_attr($field_name); ?>" 
                class="ccc-oembed-input" 
                value="<?php echo esc_attr($value); ?>"
                placeholder="<?php echo $placeholder; ?>"
                <?php echo $required; ?>
            >
            
            <?php if (!empty($value)): ?>
                <div class="ccc-oembed-preview" id="<?php echo esc_attr($field_id); ?>_preview">
                    <?php echo wp_oembed_get($value, array('width' => 400)); ?>
                </div>
            <?php else: ?>
                <div class="ccc-oembed-preview" id="<?php echo esc_attr($field_id); ?>_preview" style="display: none;"></div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#<?php echo esc_attr($field_id); ?>').on('change', function() {
                var url = $(this).val();
                var $preview = $('#<?php echo esc_attr($field_id); ?>_preview');
                
                if (url) {
                    $.post(ajaxurl, {
                        action: 'ccc_get_oembed',
                        url: url,
                        nonce: '<?php echo wp_create_nonce('ccc_oembed'); ?>'
                    }, function(response) {
                        if (response.success && response.data) {
                            $preview.html(response.data).show();
                        } else {
                            $preview.html('<p class="ccc-error">Invalid URL or unable to embed content.</p>').show();
                        }
                    });
                } else {
                    $preview.empty().hide();
                }
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
