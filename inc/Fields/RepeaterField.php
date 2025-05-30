<?php
namespace CCC\Fields;

use CCC\Models\Field;

defined('ABSPATH') || exit;

class RepeaterField extends BaseField {
    private $max_sets;
    
    public function __construct($label, $name, $component_id, $max_sets = 0) {
        parent::__construct($label, $name, $component_id);
        $this->max_sets = $max_sets;
    }
    
    public function save() {
        $config = [
            'max_sets' => $this->max_sets
        ];
        
        $field = new Field([
            'component_id' => $this->component_id,
            'label' => $this->label,
            'name' => $this->name,
            'type' => 'repeater',
            'config' => json_encode($config),
            'field_order' => 0
        ]);

        return $field->save();
    }
}
