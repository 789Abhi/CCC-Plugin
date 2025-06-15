<?php
namespace CCC\Fields;

defined('ABSPATH') || exit;

class TextField extends BaseField {
    public function render($post_id, $instance_id, $value = '') {
        $field_id = "ccc_field_{$this->name}_{$instance_id}";
        $field_name = "ccc_field_values[{$this->component_id}][{$instance_id}][{$this->name}]";
        
        $required = $this->required ? 'required' : '';
        $placeholder = esc_attr($this->placeholder);
        
        ob_start();
        ?>
        <div class="ccc-field ccc-field-text">
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
                class="ccc-text-input" 
                value="<?php echo esc_attr($value); ?>"
                placeholder="<?php echo $placeholder; ?>" 
                <?php echo $required; ?>
            >
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function save() {
        // Implementation for saving the field
        return true;
    }
}
