<?php
namespace CCC\Fields;

defined('ABSPATH') || exit;

class ToggleField extends BaseField {
    private $conditional_logic = [];
    private $default_value = false;
    private $ui_style = 'switch'; // switch, checkbox, button
    
    public function __construct($label, $name, $component_id, $required = false, $placeholder = '', $config = '') {
        parent::__construct($label, $name, $component_id, $required, $placeholder, $config);
        
        if (is_string($config)) {
            $config = json_decode($config, true);
        }
        
        $this->conditional_logic = isset($config['conditional_logic']) ? $config['conditional_logic'] : [];
        $this->default_value = isset($config['default_value']) ? (bool)$config['default_value'] : false;
        $this->ui_style = isset($config['ui_style']) ? $config['ui_style'] : 'switch';
    }
    
    public function render($post_id, $instance_id, $value = '') {
        $field_id = "ccc_field_{$this->name}_{$instance_id}";
        $field_name = "ccc_field_values[{$this->component_id}][{$instance_id}][{$this->name}]";
        
        // Parse the value
        $checked = false;
        if ($value !== '') {
            $checked = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        } else {
            $checked = $this->default_value;
        }
        
        // Generate conditional logic data attributes
        $conditional_data = '';
        if (!empty($this->conditional_logic)) {
            $conditional_data = ' data-conditional-logic="' . esc_attr(json_encode($this->conditional_logic)) . '"';
        }
        
        ob_start();
        ?>
        <div class="ccc-field ccc-field-toggle"<?php echo $conditional_data; ?>>
            <label class="ccc-field-label">
                <?php echo esc_html($this->label); ?>
                <?php if ($this->required): ?>
                    <span class="ccc-required">*</span>
                <?php endif; ?>
            </label>
            
            <div class="ccc-toggle-container">
                <?php if ($this->ui_style === 'switch'): ?>
                    <!-- Switch Style Toggle -->
                    <div class="ccc-toggle-switch">
                        <input 
                            type="checkbox" 
                            id="<?php echo esc_attr($field_id); ?>" 
                            class="ccc-toggle-input" 
                            name="<?php echo esc_attr($field_name); ?>"
                            value="1"
                            <?php echo $checked ? 'checked' : ''; ?>
                            <?php echo $this->required ? 'required' : ''; ?>
                        >
                        <label for="<?php echo esc_attr($field_id); ?>" class="ccc-toggle-label"></label>
                    </div>
                <?php elseif ($this->ui_style === 'checkbox'): ?>
                    <!-- Checkbox Style Toggle -->
                    <div class="ccc-toggle-checkbox">
                        <input 
                            type="checkbox" 
                            id="<?php echo esc_attr($field_id); ?>" 
                            class="ccc-toggle-checkbox-input" 
                            name="<?php echo esc_attr($field_name); ?>"
                            value="1"
                            <?php echo $checked ? 'checked' : ''; ?>
                            <?php echo $this->required ? 'required' : ''; ?>
                        >
                        <label for="<?php echo esc_attr($field_id); ?>" class="ccc-toggle-checkbox-label">
                            <?php echo esc_html($checked ? 'Enabled' : 'Disabled'); ?>
                        </label>
                    </div>
                <?php else: ?>
                    <!-- Button Style Toggle -->
                    <div class="ccc-toggle-buttons">
                        <button 
                            type="button" 
                            class="ccc-toggle-btn <?php echo $checked ? 'ccc-toggle-btn-active' : ''; ?>"
                            data-value="1"
                            data-field="<?php echo esc_attr($field_id); ?>"
                        >
                            Enabled
                        </button>
                        <button 
                            type="button" 
                            class="ccc-toggle-btn <?php echo !$checked ? 'ccc-toggle-btn-active' : ''; ?>"
                            data-value="0"
                            data-field="<?php echo esc_attr($field_id); ?>"
                        >
                            Disabled
                        </button>
                        <input 
                            type="hidden" 
                            id="<?php echo esc_attr($field_id); ?>" 
                            name="<?php echo esc_attr($field_name); ?>"
                            value="<?php echo $checked ? '1' : '0'; ?>"
                            <?php echo $this->required ? 'required' : ''; ?>
                        >
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($this->conditional_logic)): ?>
                <div class="ccc-conditional-info">
                    <small class="ccc-conditional-hint">
                        <span class="dashicons dashicons-visibility"></span>
                        This toggle controls the visibility of other fields
                    </small>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Handle button style toggle clicks
            $('.ccc-toggle-btn').on('click', function() {
                const $btn = $(this);
                const $container = $btn.closest('.ccc-toggle-buttons');
                const $hiddenInput = $container.find('input[type="hidden"]');
                const value = $btn.data('value');
                
                // Update active state
                $container.find('.ccc-toggle-btn').removeClass('ccc-toggle-btn-active');
                $btn.addClass('ccc-toggle-btn-active');
                
                // Update hidden input
                $hiddenInput.val(value);
                
                // Trigger change event for conditional logic
                $hiddenInput.trigger('change');
            });
            
            // Handle conditional logic for all toggle fields
            $('.ccc-field-toggle[data-conditional-logic]').each(function() {
                const $toggleField = $(this);
                const conditionalLogic = JSON.parse($toggleField.attr('data-conditional-logic'));
                
                // Function to check if toggle is enabled
                const isToggleEnabled = function() {
                    const $input = $toggleField.find('input[type="checkbox"], input[type="hidden"]');
                    return $input.val() === '1' || $input.is(':checked');
                };
                
                // Function to apply conditional logic
                const applyConditionalLogic = function() {
                    const isEnabled = isToggleEnabled();
                    
                    conditionalLogic.forEach(function(rule) {
                        const targetField = rule.target_field;
                        const action = rule.action || 'show'; // show, hide, enable, disable
                        
                        // Find target field by name or ID
                        let $targetField = $('[name*="' + targetField + '"]').closest('.ccc-field');
                        if ($targetField.length === 0) {
                            $targetField = $('[id*="' + targetField + '"]').closest('.ccc-field');
                        }
                        
                        if ($targetField.length > 0) {
                            if (action === 'show' || action === 'hide') {
                                if (isEnabled) {
                                    if (action === 'show') {
                                        $targetField.show();
                                    } else {
                                        $targetField.hide();
                                    }
                                } else {
                                    if (action === 'show') {
                                        $targetField.hide();
                                    } else {
                                        $targetField.show();
                                    }
                                }
                            } else if (action === 'enable' || action === 'disable') {
                                const $inputs = $targetField.find('input, select, textarea');
                                if (isEnabled) {
                                    if (action === 'enable') {
                                        $inputs.prop('disabled', false);
                                    } else {
                                        $inputs.prop('disabled', true);
                                    }
                                } else {
                                    if (action === 'enable') {
                                        $inputs.prop('disabled', true);
                                    } else {
                                        $inputs.prop('disabled', false);
                                    }
                                }
                            }
                        }
                    });
                };
                
                // Apply logic on page load
                applyConditionalLogic();
                
                // Apply logic when toggle changes
                $toggleField.find('input').on('change', applyConditionalLogic);
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
    
    public function getConditionalLogic() {
        return $this->conditional_logic;
    }
    
    public function setConditionalLogic($logic) {
        $this->conditional_logic = $logic;
    }
    
    public function getDefaultValue() {
        return $this->default_value;
    }
    
    public function setDefaultValue($value) {
        $this->default_value = (bool)$value;
    }
    
    public function getUIStyle() {
        return $this->ui_style;
    }
    
    public function setUIStyle($style) {
        $this->ui_style = $style;
    }
}
