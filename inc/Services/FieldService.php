<?php
namespace CCC\Services;

use CCC\Models\Field;
use CCC\Models\Component;

defined('ABSPATH') || exit;

class FieldService {
    public function createField($component_id, $label, $name, $type) {
        // Validate component exists
        $component = Component::find($component_id);
        if (!$component) {
            throw new \Exception('Invalid component ID.');
        }

        $field = new Field([
            'component_id' => $component_id,
            'label' => sanitize_text_field($label),
            'name' => sanitize_text_field($name),
            'type' => sanitize_text_field($type),
            'config' => '{}',
            'field_order' => 0
        ]);

        if (!$field->save()) {
            throw new \Exception('Failed to save field: Database error.');
        }

        return $field;
    }

    public function deleteField($field_id) {
        $field = new Field(['id' => $field_id]);
        return $field->delete();
    }
}
