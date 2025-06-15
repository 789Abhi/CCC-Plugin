<?php
namespace CCC\Fields;

defined('ABSPATH') || exit;

class ButtonGroupField extends BaseField {
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
        
        $required = $this->required ? 'required' : '';
        
        // Enqueue necessary scripts
        wp_enqueue_script('jquery');
        
        ob_start();
        ?>
        <div class="ccc-field ccc-field-button-group">
            <label class="ccc-field-label">
                <?php echo esc_html($this->label); ?>
                <?php if ($this->required): ?>
                    <span class="ccc-required">*</span>
                <?php endif; ?>
            </label>
            
            <div class="ccc-button-group" data-field-id="<?php echo esc_attr($field_id); ?>">
                <input type="hidden" name="<?php echo esc_attr($field_name); ?>" id="<?php echo esc_attr($field_id); ?>" value="<?php echo esc_attr($value); ?>">
                
                <?php foreach ($this->options as $option_value => $option_label): ?>
                    <button 
                        type="button" 
                        class="ccc-button-option <?php echo $value == $option_value ? 'selected' : ''; ?>" 
                        data-value="<?php echo esc_attr($option_value); ?>"
                    >
                        <?php echo esc_html($option_label); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.ccc-button-group[data-field-id="<?php echo esc_attr($field_id); ?>"] .ccc-button-option').on('click', function() {
                var $group = $(this).closest('.ccc-button-group');
                var $input = $('#<?php echo esc_attr($field_id); ?>');
                
                $group.find('.ccc-button-option').removeClass('selected');
                $(this).addClass('selected');
                $input.val($(this).data('value')).trigger('change');
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
