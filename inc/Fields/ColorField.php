<?php
namespace CCC\Fields;

defined('ABSPATH') || exit;

class ColorField extends BaseField {
    public function render($post_id, $instance_id, $value = '') {
        $field_id = "ccc_field_{$this->name}_{$instance_id}";
        $field_name = "ccc_field_values[{$this->component_id}][{$instance_id}][{$this->name}]";
        
        // Ensure WordPress color picker is loaded
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_script('jquery');
        
        ob_start();
        ?>
        <div class="ccc-field ccc-field-color">
            <label for="<?php echo esc_attr($field_id); ?>" class="ccc-field-label">
                <?php echo esc_html($this->label); ?>
                <?php if ($this->required): ?>
                    <span class="ccc-required">*</span>
                <?php endif; ?>
            </label>
            
            <input 
                type="text" 
                id="<?php echo esc_attr($field_id); ?>" 
                name="<?php echo esc_attr($field_name); ?>" 
                class="ccc-color-picker" 
                value="<?php echo esc_attr($value); ?>"
            >
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#<?php echo esc_attr($field_id); ?>').wpColorPicker();
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
