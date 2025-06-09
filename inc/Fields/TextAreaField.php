<?php
namespace CCC\Fields;

defined('ABSPATH') || exit;

class TextareaField extends BaseField {
    public function render($post_id, $instance_id, $value = '') {
        $field_id = "ccc_field_{$this->name}_{$instance_id}";
        $field_name = "ccc_field_values[{$this->component_id}][{$instance_id}][{$this->name}]";
        
        $required = $this->required ? 'required' : '';
        $placeholder = esc_attr($this->placeholder);
        
        ob_start();
        ?>
        <div class="ccc-field ccc-field-textarea">
            <label for="<?php echo esc_attr($field_id); ?>" class="ccc-field-label">
                <?php echo esc_html($this->label); ?>
                <?php if ($this->required): ?>
                    <span class="ccc-required">*</span>
                <?php endif; ?>
            </label>
            <textarea 
                id="<?php echo esc_attr($field_id); ?>" 
                name="<?php echo esc_attr($field_name); ?>" 
                class="ccc-textarea-input" 
                placeholder="<?php echo $placeholder; ?>"
                <?php echo $required; ?>
                rows="5"
            ><?php echo esc_textarea($value); ?></textarea>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function save() {
        // Implementation for saving the field
        return true;
    }
}
