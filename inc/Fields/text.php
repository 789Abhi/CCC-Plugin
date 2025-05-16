<?php
namespace CCC\Fields;

require_once __DIR__ . '/base-field.php';

class Text extends BaseField {
    public function save() {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'cc_fields', [
            'component_id' => $this->component_id,
            'label' => $this->label,
            'name' => $this->name,
            'type' => 'text',
            'created_at' => current_time('mysql')
        ]);
    }
}
