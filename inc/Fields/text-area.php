<?php
namespace CCC\Fields;

require_once 'BaseField.php';

class Text_Area extends BaseField {
    public function save() {
        global $wpdb;
        
        $result = $wpdb->insert($wpdb->prefix . 'cc_fields', [
            'component_id' => $this->component_id,
            'label' => $this->label,
            'name' => $this->name,
            'type' => 'text-area',
            'created_at' => current_time('mysql')
        ]);
        
        if ($result === false) {
            return false;
        }
        
        return true;
    }
}