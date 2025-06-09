<?php
namespace CCC\Fields;

defined('ABSPATH') || exit;

class TaxonomyTermField extends BaseField {
    private $taxonomy = 'category';
    private $allow_multiple = true;
    
    public function __construct($label, $name, $component_id, $required = false, $placeholder = '', $config = []) {
        parent::__construct($label, $name, $component_id, $required, $placeholder);
        
        if (is_string($config)) {
            $config = json_decode($config, true);
        }
        
        $this->taxonomy = isset($config['taxonomy']) ? $config['taxonomy'] : 'category';
        $this->allow_multiple = isset($config['allow_multiple']) ? (bool)$config['allow_multiple'] : true;
    }
    
    public function render($post_id, $instance_id, $value = '') {
        $field_id = "ccc_field_{$this->name}_{$instance_id}";
        $field_name = "ccc_field_values[{$this->component_id}][{$instance_id}][{$this->name}]";
        
        // Handle array values
        $selected_terms = [];
        if (!empty($value)) {
            if (is_string($value) && strpos($value, ',') !== false) {
                $selected_terms = explode(',', $value);
            } elseif (is_array($value)) {
                $selected_terms = $value;
            } else {
                $selected_terms = [$value];
            }
        }
        
        $required = $this->required ? 'required' : '';
        $multiple = $this->allow_multiple ? 'multiple' : '';
        $field_name = $this->allow_multiple ? $field_name . '[]' : $field_name;
        
        // Get all terms for the specified taxonomy
        $terms = get_terms([
            'taxonomy' => $this->taxonomy,
            'hide_empty' => false,
        ]);
        
        ob_start();
        ?>
        <div class="ccc-field ccc-field-taxonomy-term">
            <label for="<?php echo esc_attr($field_id); ?>" class="ccc-field-label">
                <?php echo esc_html($this->label); ?>
                <?php if ($this->required): ?>
                    <span class="ccc-required">*</span>
                <?php endif; ?>
            </label>
            
            <?php if (is_wp_error($terms)): ?>
                <p class="ccc-error">Error: <?php echo esc_html($terms->get_error_message()); ?></p>
            <?php else: ?>
                <select 
                    id="<?php echo esc_attr($field_id); ?>" 
                    name="<?php echo esc_attr($field_name); ?>" 
                    class="ccc-taxonomy-term-select" 
                    <?php echo $required; ?> 
                    <?php echo $multiple; ?>
                >
                    <?php if (!$this->allow_multiple && !$this->required): ?>
                        <option value=""><?php echo esc_html($this->placeholder ?: '— Select —'); ?></option>
                    <?php endif; ?>
                    
                    <?php foreach ($terms as $term): ?>
                        <option 
                            value="<?php echo esc_attr($term->term_id); ?>"
                            <?php echo in_array($term->term_id, $selected_terms) ? 'selected' : ''; ?>
                        >
                            <?php echo esc_html($term->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <?php if ($this->allow_multiple): ?>
                    <p class="ccc-field-description">Hold Ctrl/Cmd to select multiple items.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function save() {
        // Implementation for saving the field
        return true;
    }
}
