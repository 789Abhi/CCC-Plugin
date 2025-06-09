<?php
namespace CCC\Fields;

defined('ABSPATH') || exit;

class PageLinkField extends BaseField {
    private $post_type = 'page';
    private $allow_multiple = false;
    
    public function __construct($label, $name, $component_id, $required = false, $placeholder = '', $config = []) {
        parent::__construct($label, $name, $component_id, $required, $placeholder);
        
        if (is_string($config)) {
            $config = json_decode($config, true);
        }
        
        $this->post_type = isset($config['post_type']) ? $config['post_type'] : 'page';
        $this->allow_multiple = isset($config['allow_multiple']) ? (bool)$config['allow_multiple'] : false;
    }
    
    public function render($post_id, $instance_id, $value = '') {
        $field_id = "ccc_field_{$this->name}_{$instance_id}";
        $field_name = "ccc_field_values[{$this->component_id}][{$instance_id}][{$this->name}]";
        
        // Handle array values
        $selected_ids = [];
        if (!empty($value)) {
            if (is_string($value) && strpos($value, ',') !== false) {
                $selected_ids = explode(',', $value);
            } elseif (is_array($value)) {
                $selected_ids = $value;
            } else {
                $selected_ids = [$value];
            }
        }
        $selected_ids = array_map('intval', $selected_ids);
        
        $required = $this->required ? 'required' : '';
        $multiple = $this->allow_multiple ? 'multiple' : '';
        $field_name = $this->allow_multiple ? $field_name . '[]' : $field_name;
        
        // Get all pages/posts of the specified type
        $posts = get_posts([
            'post_type' => $this->post_type,
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish',
        ]);
        
        ob_start();
        ?>
        <div class="ccc-field ccc-field-page-link">
            <label for="<?php echo esc_attr($field_id); ?>" class="ccc-field-label">
                <?php echo esc_html($this->label); ?>
                <?php if ($this->required): ?>
                    <span class="ccc-required">*</span>
                <?php endif; ?>
            </label>
            
            <select 
                id="<?php echo esc_attr($field_id); ?>" 
                name="<?php echo esc_attr($field_name); ?>" 
                class="ccc-page-link-select" 
                <?php echo $required; ?> 
                <?php echo $multiple; ?>
            >
                <?php if (!$this->allow_multiple && !$this->required): ?>
                    <option value=""><?php echo esc_html($this->placeholder ?: '— Select —'); ?></option>
                <?php endif; ?>
                
                <?php foreach ($posts as $post_item): ?>
                    <option 
                        value="<?php echo esc_attr($post_item->ID); ?>"
                        <?php echo in_array($post_item->ID, $selected_ids) ? 'selected' : ''; ?>
                    >
                        <?php echo esc_html($post_item->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <?php if ($this->allow_multiple): ?>
                <p class="ccc-field-description">Hold Ctrl/Cmd to select multiple items.</p>
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
