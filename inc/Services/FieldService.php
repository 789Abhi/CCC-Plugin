<?php

namespace CCC\Services;

use CCC\Models\Field;
use CCC\Models\Component;
use CCC\Fields\TextField;
use CCC\Fields\TextAreaField;
use CCC\Fields\ImageField;
use CCC\Fields\RepeaterField;
use CCC\Fields\ColorField;
use CCC\Fields\SelectField;
use CCC\Fields\CheckboxField;
use CCC\Fields\RadioField;
use CCC\Fields\WysiwygField;

defined('ABSPATH') || exit;

class FieldService {
    public function createField($component_id, $label, $name, $type, $config = '') {
        // Validate component exists
        $component = Component::find($component_id);
        if (!$component) {
            throw new \Exception('Invalid component ID.');
        }

        switch ($type) {
            case 'text':
                $field = new TextField($label, $name, $component_id);
                break;
            case 'textarea':
                $field = new TextAreaField($label, $name, $component_id);
                break;
            case 'image':
                $field = new ImageField($label, $name, $component_id, false, '', $config);
                break;
            case 'repeater':
                $field = new RepeaterField($label, $name, $component_id, false, '', $config);
                break;
            case 'color':
                $field = new ColorField($label, $name, $component_id);
                break;
            case 'select':
                $field = new SelectField($label, $name, $component_id, false, '', $config);
                break;
            case 'checkbox':
                $field = new CheckboxField($label, $name, $component_id, false, '', $config);
                break;
            case 'radio':
                $field = new RadioField($label, $name, $component_id, false, '', $config);
                break;
            case 'wysiwyg':
                $field = new WysiwygField($label, $name, $component_id, false, '', $config);
                break;
            default:
                throw new \Exception('Invalid field type.');
        }

        if (!$field->save()) {
            throw new \Exception('Failed to save field: Database error.');
        }

        return $field;
    }

    public function deleteField($field_id) {
        $field = new Field(['id' => $field_id]);
        return $field->delete();
    }

    public function updateField($field_id, $data) {
        $field = Field::find($field_id);
        if (!$field) {
            throw new \Exception('Field not found.');
        }

        // Update field properties
        if (isset($data['label'])) {
            $field->setLabel($data['label']);
        }
        if (isset($data['name'])) {
            $field->setName($data['name']);
            $field->setHandle(sanitize_title($data['name'])); // Always sync handle to name
        }
        if (isset($data['type'])) {
            $field->setType($data['type']);
        }
        if (isset($data['required'])) {
            $field->setRequired($data['required']);
        }
        if (isset($data['placeholder'])) {
            $field->setPlaceholder($data['placeholder']);
        }
        if (isset($data['config'])) {
            $field->setConfig($data['config']);
        }

        if (!$field->save()) {
            throw new \Exception('Failed to update field.');
        }

        return $field;
    }

    public function getFieldsByComponent($component_id) {
        return Field::findByComponent($component_id);
    }

    public function validateFieldConfig($type, $config) {
        switch ($type) {
            case 'select':
            case 'checkbox':
            case 'radio':
                if (empty($config['options'])) {
                    throw new \Exception('Options are required for ' . $type . ' fields.');
                }
                break;
            case 'repeater':
                if (empty($config['nested_fields'])) {
                    throw new \Exception('Nested fields are required for repeater fields.');
                }
                break;
            case 'image':
                if (!in_array($config['return_type'] ?? 'url', ['url', 'array'])) {
                    throw new \Exception('Invalid return type for image field.');
                }
                break;
            case 'wysiwyg':
                // Validate editor settings if provided
                if (isset($config['editor_settings']) && !is_array($config['editor_settings'])) {
                    throw new \Exception('Editor settings must be an array.');
                }
                break;
        }
        return true;
    }
}
