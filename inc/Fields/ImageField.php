<?php
namespace CCC\Fields;

use CCC\Models\Field;

defined('ABSPATH') || exit;

class ImageField extends BaseField {
    private $return_type;
    
    public function __construct($label, $name, $component_id, $return_type = 'url') {
        parent::__construct($label, $name, $component_id);
        $this->return_type = $return_type; // 'url' or 'array'
    }
    
    public function save() {
        $config = [
            'return_type' => $this->return_type
        ];
        
        $field = new Field([
            'component_id' => $this->component_id,
            'label' => $this->label,
            'name' => $this->name,
            'type' => 'image',
            'config' => json_encode($config),
            'field_order' => 0
        ]);

        return $field->save();
    }
}
