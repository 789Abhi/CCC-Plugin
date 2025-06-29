<?php
namespace CCC\Services;

use CCC\Models\Field;
use CCC\Models\Component;
use CCC\Fields\TextField;
use CCC\Fields\TextAreaField;
use CCC\Fields\ImageField;
use CCC\Fields\RepeaterField;
use CCC\Fields\ColorField; // NEW: Import ColorField
use CCC\Fields\SelectField;
use CCC\Fields\CheckboxField;
use CCC\Fields\RadioField;

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
          case 'textarea': // Changed from text-area to textarea for consistency
              $field = new TextAreaField($label, $name, $component_id);
              break;
          case 'image':
              $field = new ImageField($label, $name, $component_id, false, '', $config); // Pass config
              break;
          case 'repeater':
              $field = new RepeaterField($label, $name, $component_id, false, '', $config); // Pass config
              break;
          case 'color': // NEW: Add ColorField
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
}
