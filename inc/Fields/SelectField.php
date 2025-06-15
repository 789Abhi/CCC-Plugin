<?php
namespace CCC\Fields;

defined('ABSPATH') || exit;

class SelectField extends BaseField {
    private $options = [];
    private $multiple = false;
    
    public function __construct($label, $name, $component_id, $required = false, $placeholder = '', $config = []) {
        parent::__construct($label, $name, $component_id, $required, $placeholder);
        
        if (is_string($config)) {
            $config = json_decode($config, true);
        }
        
        $this->options = isset($config['options']) ? $config['options'] : [];
        $this->multiple = isset($config['multiple']) ? (bool)$config['multiple'] : false;
    }
    
    public function render($post_id, $instance_id, $value = '') {
        $field_id = "ccc_field_{$this->name}_{$instance_id}";
        $field_name = "ccc_field_values[{$this->component_id}][{$instance_id}][{$this->name}]";
        
        // Handle array values for multiple select
        $selected_values = [];
        if (!empty($value)) {
            if (is_string($value) && strpos($value, ',') !== false) {
                $selected_values = explode(',', $value);
            } elseif (is_array($value)) {
                $selected_values = $value;
            } else {
                $selected_values = [$value];
            }
        }
        
        $required = $this->required ? 'required' : '';
        $multiple = $this->multiple ? 'multiple' : '';
        $field_name = $this->multiple ? $field_name . '[]' : $field_name;
        
        ob_start();
        ?>
        <div class="ccc-field ccc-field-select">
            <label for="<?php echo esc_attr($field_id); ?>" class="ccc-field-label">
                <?php echo esc_html($this->label); ?>
                <?php if ($this->required): ?>
                    <span class="ccc-required">*</span>
                <?php endif; ?>
            </label>
            
            <select 
                id="<?php echo esc_attr($field_id); ?>" 
                name="<?php echo esc_attr($field_name); ?>" 
                class="ccc-select" 
                <?php echo $required; ?> 
                <?php echo $multiple; ?>
            >
                <?php if (!$this->multiple && !$this->required): ?>
                    <option value=""><?php echo esc_html($this->placeholder ?: '— Select —'); ?></option>
                <?php endif; ?>
                
                <?php foreach ($this->options as $option_value => $option_label): ?>
                    <option 
                        value="<?php echo esc_attr($option_value); ?>"
                        <?php echo in_array($option_value, $selected_values) ? 'selected' : ''; ?>
                    >
                        <?php echo esc_html($option_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function save() {
        // Implementation for saving the field
        return true;
    }
}
