<?php

namespace CCC\Services;

use CCC\Models\Field;
use CCC\Models\Component;
use CCC\Fields\BaseField;
use CCC\Fields\TextField;
use CCC\Fields\TextAreaField;
use CCC\Fields\ImageField;
use CCC\Fields\RepeaterField;
use CCC\Fields\ColorField;
use CCC\Fields\SelectField;
use CCC\Fields\CheckboxField;
use CCC\Fields\RadioField;
use CCC\Fields\WysiwygField;
use CCC\Fields\OembedField;
use CCC\Fields\RelationshipField;
use CCC\Fields\LinkField;
use CCC\Fields\EmailField;
use CCC\Fields\NumberField;
use CCC\Fields\RangeField;
use CCC\Fields\FileField;
use CCC\Fields\TaxonomyTermField;

defined('ABSPATH') || exit;

class FieldService {
    public function createField($component_id, $label, $name, $type, $config = '') {
        // Validate component exists
        $component = Component::find($component_id);
        if (!$component) {
            throw new \Exception('Invalid component ID.');
        }

        // Create field data array
        $field_data = [
            'component_id' => $component_id,
            'label' => $label,
            'name' => $name,
            'type' => $type,
            'required' => false,
            'placeholder' => '',
            'config' => $config,
            'field_order' => 0
        ];

        // Create field instance for validation and rendering
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
            case 'oembed':
                $field = new OembedField($label, $name, $component_id, false, '', $config);
                break;
            case 'relationship':
                $field = new RelationshipField($label, $name, $component_id, false, '', $config);
                break;
            case 'link':
                $field = new LinkField($label, $name, $component_id, false, '', $config);
                break;
            case 'email':
                $field = new EmailField($label, $name, $component_id, false, '', $config);
                break;
            case 'number':
                $field = new NumberField($label, $name, $component_id, false, '', $config);
                break;
            case 'range':
                $field = new RangeField($label, $name, $component_id, false, '', $config);
                break;
            case 'file':
                $field = new FileField($label, $name, $component_id, false, '', $config);
                break;
            case 'taxonomy_term':
                $field = new TaxonomyTermField($label, $name, $component_id, false, '', $config);
                break;
            default:
                throw new \Exception('Invalid field type.');
        }

        // Set the field ID after creation for future reference
        $field->setId($field->getId());

        // Create and save the field using the Field model
        $field_model = new Field($field_data);
        if (!$field_model->save()) {
            throw new \Exception('Failed to save field: Database error.');
        }

        // Set the ID from the saved model back to the field instance
        $field->setId($field_model->getId());

        return $field;
    }

    public function getField($field_id) {
        return Field::find($field_id);
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
