<?php
namespace CCC\Fields;

use CCC\Models\Field;

defined('ABSPATH') || exit;

class RepeaterField extends BaseField {
    private $max_sets = 0;
    private $nested_fields = [];
    
    public function __construct($label, $name, $component_id, $required = false, $placeholder = '', $config = []) {
        parent::__construct($label, $name, $component_id, $required, $placeholder);
        
        if (is_string($config)) {
            $config = json_decode($config, true);
        }
        
        $this->max_sets = isset($config['max_sets']) ? (int)$config['max_sets'] : 0;
        $this->nested_fields = isset($config['nested_fields']) ? $config['nested_fields'] : [];
    }
    
    public function render($post_id, $instance_id, $value = '') {
        $field_id = "ccc_field_{$this->name}_{$instance_id}";
        $field_name = "ccc_field_values[{$this->component_id}][{$instance_id}][{$this->name}]";
        
        // Parse existing values
        $repeater_values = [];
        if (!empty($value)) {
            if (is_string($value)) {
                $repeater_values = json_decode($value, true);
            } elseif (is_array($value)) {
                $repeater_values = $value;
            }
        }
        
        if (!is_array($repeater_values)) {
            $repeater_values = [];
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-sortable');
        
        ob_start();
        ?>
        <div class="ccc-field ccc-field-repeater" data-field-id="<?php echo esc_attr($field_id); ?>">
            <label class="ccc-field-label">
                <?php echo esc_html($this->label); ?>
                <?php if ($this->required): ?>
                    <span class="ccc-required">*</span>
                <?php endif; ?>
            </label>
            
            <div class="ccc-repeater-container">
                <div class="ccc-repeater-items" data-max-sets="<?php echo esc_attr($this->max_sets); ?>">
                    <?php foreach ($repeater_values as $index => $item_values): ?>
                        <?php $this->renderRepeaterItem($index, $item_values, $field_name); ?>
                    <?php endforeach; ?>
                </div>
                
                <div class="ccc-repeater-actions">
                    <button type="button" class="button ccc-add-repeater-item">Add Item</button>
                </div>
            </div>
            
            <!-- Hidden template for new items -->
            <script type="text/template" class="ccc-repeater-template">
                <?php $this->renderRepeaterItem('{{INDEX}}', [], $field_name, true); ?>
            </script>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var $repeater = $('.ccc-field-repeater[data-field-id="<?php echo esc_attr($field_id); ?>"]');
            var $container = $repeater.find('.ccc-repeater-items');
            var $template = $repeater.find('.ccc-repeater-template');
            var maxSets = parseInt($container.data('max-sets')) || 0;
            
            // Make items sortable
            $container.sortable({
                handle: '.ccc-repeater-handle',
                placeholder: 'ccc-repeater-placeholder',
                update: function() {
                    updateIndexes();
                }
            });
            
            // Add new item
            $repeater.on('click', '.ccc-add-repeater-item', function(e) {
                e.preventDefault();
                
                var currentCount = $container.children('.ccc-repeater-item').length;
                
                if (maxSets > 0 && currentCount >= maxSets) {
                    alert('Maximum ' + maxSets + ' items allowed.');
                    return;
                }
                
                var newIndex = currentCount;
                var template = $template.html();
                var newItem = template.replace(/\{\{INDEX\}\}/g, newIndex);
                
                $container.append(newItem);
                updateIndexes();
                
                // Initialize any special fields in the new item
                initializeNewItemFields($container.children('.ccc-repeater-item').last());
            });
            
            // Remove item
            $repeater.on('click', '.ccc-remove-repeater-item', function(e) {
                e.preventDefault();
                
                if (confirm('Are you sure you want to remove this item?')) {
                    $(this).closest('.ccc-repeater-item').remove();
                    updateIndexes();
                }
            });
            
            function updateIndexes() {
                $container.children('.ccc-repeater-item').each(function(index) {
                    var $item = $(this);
                    
                    // Update all input names and IDs
                    $item.find('input, select, textarea').each(function() {
                        var $input = $(this);
                        var name = $input.attr('name');
                        var id = $input.attr('id');
                        
                        if (name) {
                            // Replace the index in the name
                            name = name.replace(/\[\d+\]/, '[' + index + ']');
                            $input.attr('name', name);
                        }
                        
                        if (id) {
                            // Replace the index in the ID
                            id = id.replace(/_\d+_/, '_' + index + '_');
                            $input.attr('id', id);
                        }
                    });
                    
                    // Update labels
                    $item.find('label').each(function() {
                        var $label = $(this);
                        var forAttr = $label.attr('for');
                        
                        if (forAttr) {
                            forAttr = forAttr.replace(/_\d+_/, '_' + index + '_');
                            $label.attr('for', forAttr);
                        }
                    });
                });
                serializeRepeater();
            }
            
            function initializeNewItemFields($item) {
                // Initialize color pickers
                $item.find('.ccc-color-picker').wpColorPicker();
                
                // Initialize any other special field types
                // Add more initialization as needed for different field types
            }
            
            // Serialize all repeater data into the hidden input
            function serializeRepeater() {
                var repeaterData = [];
                $container.children('.ccc-repeater-item').each(function() {
                    var $item = $(this);
                    var itemData = {};
                    $item.find('.ccc-nested-field').each(function() {
                        var $field = $(this);
                        var fieldName = $field.data('nested-field-name');
                        var fieldType = $field.data('nested-field-type');
                        var $input = $field.find('input, select, textarea').first();
                        var value;
                        if ($input.is(':checkbox')) {
                            value = [];
                            $field.find('input[type="checkbox"]:checked').each(function() {
                                value.push($(this).val());
                            });
                        } else if ($input.is(':radio')) {
                            value = $field.find('input[type="radio"]:checked').val() || '';
                        } else {
                            value = $input.val();
                        }
                        itemData[fieldName] = value;
                    });
                    repeaterData.push(itemData);
                });
                $repeater.find('.ccc-repeater-main-input').val(JSON.stringify(repeaterData));
            }
            
            // Serialize on change
            $repeater.on('change', '.ccc-nested-field-input', function() {
                serializeRepeater();
            });
            
            // Serialize on page load
            serializeRepeater();
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    private function renderRepeaterItem($index, $values = [], $field_name = '', $is_template = false) {
        $index_placeholder = $is_template ? '{{INDEX}}' : $index;
        
        echo '<div class="ccc-repeater-item">';
        echo '<div class="ccc-repeater-item-header">';
        echo '<span class="ccc-repeater-handle">≡</span>';
        echo '<span class="ccc-repeater-item-title">Item ' . ($is_template ? '{{INDEX_DISPLAY}}' : ($index + 1)) . '</span>';
        echo '<button type="button" class="ccc-remove-repeater-item">&times;</button>';
        echo '</div>';
        
        echo '<div class="ccc-repeater-item-content">';
        
        foreach ($this->nested_fields as $nested_field) {
            $nested_field_name = $field_name . '[' . $index_placeholder . '][' . $nested_field['name'] . ']';
            $nested_field_id = 'ccc_nested_' . $nested_field['name'] . '_' . $index_placeholder;
            $nested_value = isset($values[$nested_field['name']]) ? $values[$nested_field['name']] : '';
            
            $this->renderNestedField($nested_field, $nested_field_name, $nested_field_id, $nested_value);
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    private function renderNestedField($field_config, $field_name, $field_id, $value = '') {
        $type = $field_config['type'];
        $label = $field_config['label'];
        $required = isset($field_config['required']) ? $field_config['required'] : false;
        $placeholder = isset($field_config['placeholder']) ? $field_config['placeholder'] : '';
        $config = isset($field_config['config']) ? $field_config['config'] : [];
        
        echo '<div class="ccc-nested-field ccc-nested-field-' . esc_attr($type) . '">';
        
        switch ($type) {
            case 'text':
                $this->renderTextField($field_id, $field_name, $label, $value, $required, $placeholder);
                break;
            case 'textarea':
                $this->renderTextareaField($field_id, $field_name, $label, $value, $required, $placeholder);
                break;
            case 'select':
                $this->renderSelectField($field_id, $field_name, $label, $value, $required, $placeholder, $config);
                break;
            case 'checkbox':
                $this->renderCheckboxField($field_id, $field_name, $label, $value, $required, $config);
                break;
            case 'radio':
                $this->renderRadioField($field_id, $field_name, $label, $value, $required, $config);
                break;
            case 'color':
                $this->renderColorField($field_id, $field_name, $label, $value, $required);
                break;
            case 'image':
                $this->renderImageField($field_id, $field_name, $label, $value, $required, $config);
                break;
            case 'toggle':
                $this->renderToggleField($field_id, $field_name, $label, $value, $required);
                break;
            default:
                $this->renderTextField($field_id, $field_name, $label, $value, $required, $placeholder);
                break;
        }
        
        echo '</div>';
    }
    
    private function renderTextField($field_id, $field_name, $label, $value, $required, $placeholder) {
        ?>
        <label for="<?php echo esc_attr($field_id); ?>" class="ccc-nested-field-label">
            <?php echo esc_html($label); ?>
            <?php if ($required): ?><span class="ccc-required">*</span><?php endif; ?>
        </label>
        <input 
            type="text" 
            id="<?php echo esc_attr($field_id); ?>" 
            name="<?php echo esc_attr($field_name); ?>" 
            value="<?php echo esc_attr($value); ?>"
            placeholder="<?php echo esc_attr($placeholder); ?>"
            <?php echo $required ? 'required' : ''; ?>
        >
        <?php
    }
    
    private function renderTextareaField($field_id, $field_name, $label, $value, $required, $placeholder) {
        ?>
        <label for="<?php echo esc_attr($field_id); ?>" class="ccc-nested-field-label">
            <?php echo esc_html($label); ?>
            <?php if ($required): ?><span class="ccc-required">*</span><?php endif; ?>
        </label>
        <textarea 
            id="<?php echo esc_attr($field_id); ?>" 
            name="<?php echo esc_attr($field_name); ?>" 
            placeholder="<?php echo esc_attr($placeholder); ?>"
            rows="3"
            <?php echo $required ? 'required' : ''; ?>
        ><?php echo esc_textarea($value); ?></textarea>
        <?php
    }
    
    private function renderSelectField($field_id, $field_name, $label, $value, $required, $placeholder, $config) {
        $options = isset($config['options']) ? $config['options'] : [];
        ?>
        <label for="<?php echo esc_attr($field_id); ?>" class="ccc-nested-field-label">
            <?php echo esc_html($label); ?>
            <?php if ($required): ?><span class="ccc-required">*</span><?php endif; ?>
        </label>
        <select 
            id="<?php echo esc_attr($field_id); ?>" 
            name="<?php echo esc_attr($field_name); ?>"
            <?php echo $required ? 'required' : ''; ?>
        >
            <?php if (!$required): ?>
                <option value=""><?php echo esc_html($placeholder ?: '— Select —'); ?></option>
            <?php endif; ?>
            <?php foreach ($options as $option_value => $option_label): ?>
                <option value="<?php echo esc_attr($option_value); ?>" <?php selected($value, $option_value); ?>>
                    <?php echo esc_html($option_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
    
    private function renderCheckboxField($field_id, $field_name, $label, $value, $required, $config) {
        $options = isset($config['options']) ? $config['options'] : [];
        $selected_values = is_array($value) ? $value : explode(',', $value);
        ?>
        <label class="ccc-nested-field-label">
            <?php echo esc_html($label); ?>
            <?php if ($required): ?><span class="ccc-required">*</span><?php endif; ?>
        </label>
        <div class="ccc-checkbox-options">
            <?php foreach ($options as $option_value => $option_label): ?>
                <label>
                    <input 
                        type="checkbox" 
                        name="<?php echo esc_attr($field_name); ?>[]" 
                        value="<?php echo esc_attr($option_value); ?>"
                        <?php echo in_array($option_value, $selected_values) ? 'checked' : ''; ?>
                    >
                    <?php echo esc_html($option_label); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    private function renderRadioField($field_id, $field_name, $label, $value, $required, $config) {
        $options = isset($config['options']) ? $config['options'] : [];
        ?>
        <label class="ccc-nested-field-label">
            <?php echo esc_html($label); ?>
            <?php if ($required): ?><span class="ccc-required">*</span><?php endif; ?>
        </label>
        <div class="ccc-radio-options">
            <?php foreach ($options as $option_value => $option_label): ?>
                <label>
                    <input 
                        type="radio" 
                        name="<?php echo esc_attr($field_name); ?>" 
                        value="<?php echo esc_attr($option_value); ?>"
                        <?php checked($value, $option_value); ?>
                        <?php echo $required ? 'required' : ''; ?>
                    >
                    <?php echo esc_html($option_label); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    private function renderColorField($field_id, $field_name, $label, $value, $required) {
        ?>
        <label for="<?php echo esc_attr($field_id); ?>" class="ccc-nested-field-label">
            <?php echo esc_html($label); ?>
            <?php if ($required): ?><span class="ccc-required">*</span><?php endif; ?>
        </label>
        <input 
            type="text" 
            id="<?php echo esc_attr($field_id); ?>" 
            name="<?php echo esc_attr($field_name); ?>" 
            class="ccc-color-picker"
            value="<?php echo esc_attr($value); ?>"
            <?php echo $required ? 'required' : ''; ?>
        >
        <?php
    }
    
    private function renderImageField($field_id, $field_name, $label, $value, $required, $config) {
        $return_type = isset($config['return_type']) ? $config['return_type'] : 'url';
        
        // Handle image data
        $image_url = '';
        if (!empty($value)) {
            if ($return_type === 'array' && is_string($value)) {
                $image_data = json_decode($value, true);
                $image_url = isset($image_data['url']) ? $image_data['url'] : '';
            } else {
                $image_url = $value;
            }
        }
        ?>
        <label class="ccc-nested-field-label">
            <?php echo esc_html($label); ?>
            <?php if ($required): ?><span class="ccc-required">*</span><?php endif; ?>
        </label>
        <div class="ccc-nested-image-field" data-return-type="<?php echo esc_attr($return_type); ?>">
            <div class="ccc-image-preview-wrapper">
                <?php if (!empty($image_url)): ?>
                    <div class="ccc-image-preview">
                        <img src="<?php echo esc_url($image_url); ?>" alt="" style="max-width: 150px; height: auto;">
                        <button type="button" class="ccc-remove-nested-image">&times;</button>
                    </div>
                <?php else: ?>
                    <div class="ccc-image-placeholder">No image</div>
                <?php endif; ?>
            </div>
            <button type="button" class="button ccc-upload-nested-image">Select Image</button>
            <input 
                type="hidden" 
                name="<?php echo esc_attr($field_name); ?>" 
                value="<?php echo esc_attr($value); ?>"
                <?php echo $required ? 'required' : ''; ?>
            >
        </div>
        <?php
    }
    
    private function renderToggleField($field_id, $field_name, $label, $value, $required) {
        $checked = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        ?>
        <label class="ccc-nested-field-label">
            <?php echo esc_html($label); ?>
            <?php if ($required): ?><span class="ccc-required">*</span><?php endif; ?>
        </label>
        <div class="ccc-toggle-switch">
            <input 
                type="checkbox" 
                id="<?php echo esc_attr($field_id); ?>" 
                class="ccc-toggle-input" 
                <?php echo $checked ? 'checked' : ''; ?>
            >
            <label for="<?php echo esc_attr($field_id); ?>" class="ccc-toggle-label"></label>
            <input 
                type="hidden" 
                name="<?php echo esc_attr($field_name); ?>" 
                value="<?php echo $checked ? '1' : '0'; ?>"
            >
        </div>
        <?php
    }
    
    public function save() {
        // Implementation for saving the field
        return true;
    }
}
