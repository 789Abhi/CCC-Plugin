<?php
namespace CCC\Fields;

defined('ABSPATH') || exit;

class TabField extends BaseField {
    private $tab_id;
    
    public function __construct($label, $name, $component_id, $required = false, $placeholder = '', $config = []) {
        parent::__construct($label, $name, $component_id, $required, $placeholder);
        
        if (is_string($config)) {
            $config = json_decode($config, true);
        }
        
        $this->tab_id = isset($config['tab_id']) ? $config['tab_id'] : sanitize_title($name);
    }
    
    public function render($post_id, $instance_id, $value = '') {
        $field_id = "ccc_field_{$this->name}_{$instance_id}";
        
        // This field doesn't store any value, it's just a UI element
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-tabs');
        
        ob_start();
        ?>
        <div class="ccc-field ccc-field-tab" data-tab-id="<?php echo esc_attr($this->tab_id); ?>">
            <h3 class="ccc-tab-label"><?php echo esc_html($this->label); ?></h3>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function save() {
        // Tab fields don't save any data
        return true;
    }
    
    public function getTabId() {
        return $this->tab_id;
    }
}
