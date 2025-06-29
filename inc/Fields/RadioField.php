<?php
namespace CCC\Fields;

defined('ABSPATH') || exit;

class RadioField extends BaseField {
    private $options = [];
    
    public function __construct($label, $name, $component_id, $required = false, $placeholder = '', $config = []) {
        parent::__construct($label, $name, $component_id, $required, $placeholder);
        
        if (is_string($config)) {
            $config = json_decode($config, true);
        }
        
        $this->options = isset($config['options']) ? $config['options'] : [];
    }
    
    public function render($post_id, $instance_id, $value = '') {
        $field_id = "ccc_field_{$this->name}_{$instance_id}";
        $field_name = "ccc_field_values[{$this->component_id}][{$instance_id}][{$this->name}]";
        
        ob_start();
        ?>
        <div class="ccc-field ccc-field-radio">
            <label class="ccc-field-label">
                <?php echo esc_html($this->label); ?>
                <?php if ($this->required): ?>
                    <span class="ccc-required">*</span>
                <?php endif; ?>
            </label>
            <div class="ccc-radio-options">
                <?php foreach ($this->options as $option_value => $option_label): ?>
                    <label class="ccc-radio-option">
                        <input 
                            type="radio" 
                            name="<?php echo esc_attr($field_name); ?>" 
                            value="<?php echo esc_attr($option_value); ?>"
                            <?php checked($value, $option_value); ?>
                            <?php echo $this->required ? 'required' : ''; ?>
                        >
                        <span class="ccc-radio-label"><?php echo esc_html($option_label); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function save() {
        // Implementation for saving the field
        return true;
    }
    
    public function getOptions() {
        return $this->options;
    }
} 