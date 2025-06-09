<?php
namespace CCC\Fields;

defined('ABSPATH') || exit;

class ToggleField extends BaseField {
    private $default_value = false;
    
    public function __construct($label, $name, $component_id, $required = false, $placeholder = '', $config = []) {
        parent::__construct($label, $name, $component_id, $required, $placeholder);
        
        if (is_string($config)) {
            $config = json_decode($config, true);
        }
        
        $this->default_value = isset($config['default_value']) ? (bool)$config['default_value'] : false;
    }
    
    public function render($post_id, $instance_id, $value = '') {
        $field_id = "ccc_field_{$this->name}_{$instance_id}";
        $field_name = "ccc_field_values[{$this->component_id}][{$instance_id}][{$this->name}]";
        
        // Convert value to boolean
        if ($value === '') {
            $value = $this->default_value;
        } else {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }
        
        wp_enqueue_script('jquery');
        
        ob_start();
        ?>
        <div class="ccc-field ccc-field-toggle">
            <label for="<?php echo esc_attr($field_id); ?>" class="ccc-field-label">
                <?php echo esc_html($this->label); ?>
                <?php if ($this->required): ?>
                    <span class="ccc-required">*</span>
                <?php endif; ?>
            </label>
            
            <div class="ccc-toggle-switch">
                <input 
                    type="checkbox" 
                    id="<?php echo esc_attr($field_id); ?>" 
                    class="ccc-toggle-input" 
                    <?php echo $value ? 'checked' : ''; ?>
                >
                <label for="<?php echo esc_attr($field_id); ?>" class="ccc-toggle-label"></label>
                <input 
                    type="hidden" 
                    name="<?php echo esc_attr($field_name); ?>" 
                    value="<?php echo $value ? '1' : '0'; ?>"
                >
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#<?php echo esc_attr($field_id); ?>').on('change', function() {
                var isChecked = $(this).is(':checked');
                $(this).siblings('input[type="hidden"]').val(isChecked ? '1' : '0');
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
