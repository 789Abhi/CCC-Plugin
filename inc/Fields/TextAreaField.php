<?php
namespace CCC\Fields;

use CCC\Models\Field;

defined('ABSPATH') || exit;

class TextAreaField extends BaseField {
    public function save() {
        $field = new Field([
            'component_id' => $this->component_id,
            'label' => $this->label,
            'name' => $this->name,
            'type' => 'text-area',
            'config' => '{}',
            'field_order' => 0
        ]);

        return $field->save();
    }
}
