<?php
namespace CCC\Ajax;

use CCC\Services\ComponentService;
use CCC\Services\FieldService;
use CCC\Models\Component;
use CCC\Models\FieldValue;
use CCC\Models\Field;

defined('ABSPATH') || exit;

class AjaxHandler {
  private $component_service;
  private $field_service;

  public function __construct() {
      $this->component_service = new ComponentService();
      $this->field_service = new FieldService();
  }

  public function init() {
      add_action('wp_ajax_ccc_create_component', [$this, 'handleCreateComponent']);
      add_action('wp_ajax_ccc_get_components', [$this, 'getComponents']);
      add_action('wp_ajax_ccc_get_component_fields', [$this, 'getComponentFields']);
      add_action('wp_ajax_ccc_add_field', [$this, 'addFieldCallback']);
      add_action('wp_ajax_ccc_update_field', [$this, 'updateFieldCallback']);
      add_action('wp_ajax_ccc_get_posts', [$this, 'getPosts']);
      add_action('wp_ajax_ccc_get_posts_with_components', [$this, 'getPostsWithComponents']);
      add_action('wp_ajax_ccc_save_component_assignments', [$this, 'saveComponentAssignments']);
      add_action('wp_ajax_ccc_delete_component', [$this, 'deleteComponent']);
      add_action('wp_ajax_ccc_delete_field', [$this, 'deleteField']);
      add_action('wp_ajax_ccc_save_assignments', [$this, 'saveAssignments']);
      add_action('wp_ajax_ccc_save_field_values', [$this, 'saveFieldValues']);
      add_action('wp_ajax_nopriv_ccc_save_field_values', [$this, 'saveFieldValues']);
      add_action('wp_ajax_ccc_update_component_name', [$this, 'updateComponentName']);
      add_action('wp_ajax_ccc_update_field_order', [$this, 'updateFieldOrder']);
      add_action('wp_ajax_ccc_update_component_fields', [$this, 'updateComponentFields']);
      add_action('wp_ajax_ccc_update_field_from_hierarchy', [$this, 'updateFieldFromHierarchy']);
  }

  public function handleCreateComponent() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');

          $name = sanitize_text_field($_POST['name'] ?? '');
          $handle = sanitize_title($_POST['handle'] ?? '');

          if (empty($name) || empty($handle)) {
              wp_send_json_error(['message' => 'Missing required fields']);
              return;
          }

          $this->component_service->createComponent($name, $handle);
          wp_send_json_success(['message' => 'Component created successfully']);

      } catch (\Exception $e) {
          error_log("Exception in handleCreateComponent: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }

  public function updateComponentName() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');

          $component_id = intval($_POST['component_id'] ?? 0);
          $name = sanitize_text_field($_POST['name'] ?? '');
          $handle = sanitize_title($_POST['handle'] ?? '');

          if (!$component_id || empty($name) || empty($handle)) {
              wp_send_json_error(['message' => 'Missing required fields']);
              return;
          }

          $this->component_service->updateComponent($component_id, $name, $handle);
          wp_send_json_success(['message' => 'Component updated successfully']);

      } catch (\Exception $e) {
          error_log("Exception in updateComponentName: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }

  public function getComponents() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');
          
          $components = $this->component_service->getComponentsWithFields();
          
          error_log("CCC AjaxHandler: getComponents - Fetched " . count($components) . " components");
          foreach ($components as $index => $comp) {
              error_log("  Component {$index}: {$comp['name']} (ID: {$comp['id']}) with " . count($comp['fields']) . " fields");
              foreach ($comp['fields'] as $field_index => $field) {
                  $config_info = is_array($field['config']) ? 'ARRAY' : (is_string($field['config']) ? 'STRING' : 'OTHER');
                  error_log("    Field {$field_index}: {$field['name']} (Type: {$field['type']}, Config: {$config_info})");
                  
                  if ($field['type'] === 'repeater' && is_array($field['config'])) {
                      $nested_count = isset($field['config']['nested_fields']) ? count($field['config']['nested_fields']) : 0;
                      error_log("      Repeater has {$nested_count} nested fields");
                      if ($nested_count > 0) {
                          foreach ($field['config']['nested_fields'] as $nf_index => $nested_field) {
                              error_log("        Nested field {$nf_index}: {$nested_field['name']} ({$nested_field['type']})");
                          }
                      }
                  }
              }
          }

          wp_send_json_success(['components' => $components]);

      } catch (\Exception $e) {
          error_log("Exception in getComponents: " . $e->getMessage());
          wp_send_json_error(['message' => 'Failed to fetch components: ' . $e->getMessage()]);
      }
  }

  private function sanitizeNestedFieldDefinitions(array $nested_fields): array {
      error_log("CCC AjaxHandler: sanitizeNestedFieldDefinitions called with " . count($nested_fields) . " fields");
      $sanitized_fields = [];
      foreach ($nested_fields as $nf) {
          error_log("CCC AjaxHandler: Processing nested field: " . ($nf['label'] ?? 'unknown') . " (type: " . ($nf['type'] ?? 'unknown') . ")");
          
          $sanitized_nf = [
              'label' => sanitize_text_field($nf['label'] ?? ''),
              'name' => sanitize_title($nf['name'] ?? ''),
              'type' => sanitize_text_field($nf['type'] ?? ''),
          ];

          if (isset($nf['config'])) {
              $config = $nf['config'];
              $sanitized_config = [];
              
              // Handle different field type configurations
              if ($sanitized_nf['type'] === 'repeater') {
                  $nested_nested_fields = $config['nested_fields'] ?? [];
                  error_log("CCC AjaxHandler: Found nested repeater with " . count($nested_nested_fields) . " nested fields");
                  
                  $sanitized_config = [
                      'max_sets' => intval($config['max_sets'] ?? 0),
                      'nested_fields' => $this->sanitizeNestedFieldDefinitions($nested_nested_fields)
                  ];
                  
                  error_log("CCC AjaxHandler: Processed nested repeater config: " . json_encode($sanitized_config));
              } elseif (in_array($sanitized_nf['type'], ['checkbox', 'select', 'radio', 'button_group'])) {
                  $sanitized_config['options'] = $config['options'] ?? [];
                  $sanitized_config['multiple'] = (bool)($config['multiple'] ?? false);
              } elseif ($sanitized_nf['type'] === 'image') {
                  $sanitized_config['return_type'] = sanitize_text_field($config['return_type'] ?? 'url');
              } elseif ($sanitized_nf['type'] === 'taxonomy_term') {
                  $sanitized_config['taxonomy'] = sanitize_text_field($config['taxonomy'] ?? 'category');
              }
              
              $sanitized_nf['config'] = $sanitized_config;
          }
          
          $sanitized_fields[] = $sanitized_nf;
      }
      
      error_log("CCC AjaxHandler: sanitizeNestedFieldDefinitions returning " . count($sanitized_fields) . " sanitized fields");
      return $sanitized_fields;
  }

  public function addFieldCallback() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');

          $label = sanitize_text_field($_POST['label'] ?? '');
          $name = sanitize_text_field($_POST['name'] ?? '');
          $type = sanitize_text_field($_POST['type'] ?? '');
          $component_id = intval($_POST['component_id'] ?? 0);
          $required = isset($_POST['required']) ? (bool) $_POST['required'] : false;
          $placeholder = sanitize_text_field($_POST['placeholder'] ?? '');

          if (empty($label) || empty($name) || empty($type) || empty($component_id)) {
              wp_send_json_error(['message' => 'Missing required fields.']);
              return;
          }

          $config = [];
          $nested_field_definitions = [];
          if ($type === 'repeater') {
              $max_sets = intval($_POST['max_sets'] ?? 0);
              $nested_field_definitions = json_decode(wp_unslash($_POST['nested_field_definitions'] ?? '[]'), true);
              error_log("CCC AjaxHandler: Adding repeater field with " . count($nested_field_definitions) . " nested fields");
              $config = [
                  'max_sets' => $max_sets
              ];
          } elseif (in_array($type, ['checkbox', 'select', 'radio', 'button_group'])) {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              $config = [
                  'options' => $field_config['options'] ?? [],
                  'multiple' => (bool)($field_config['multiple'] ?? false)
              ];
          } elseif ($type === 'image') {
              $return_type = sanitize_text_field($_POST['return_type'] ?? 'url');
              $config = [
                  'return_type' => $return_type
              ];
          } elseif ($type === 'taxonomy_term') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              $config = [
                  'taxonomy' => sanitize_text_field($field_config['taxonomy'] ?? 'category')
              ];
          } elseif ($type === 'color') {
              $config = [];
          }

          $field = new \CCC\Models\Field([
              'component_id' => $component_id,
              'label' => $label,
              'name' => $name,
              'type' => $type,
              'config' => json_encode($config),
              'field_order' => 0,
              'required' => $required,
              'placeholder' => $placeholder
          ]);

          if ($field->save()) {
              // If repeater, save nested fields as real DB rows
              if ($type === 'repeater' && !empty($nested_field_definitions)) {
                  \CCC\Core\Database::migrateNestedFieldsToRowsRecursive($component_id, $field->getId(), $nested_field_definitions);
              }
              error_log("CCC AjaxHandler: Successfully saved field {$name} with config: " . json_encode($config));
              wp_send_json_success(['message' => 'Field added successfully.']);
          } else {
              wp_send_json_error(['message' => 'Failed to save field.']);
          }

      } catch (\Exception $e) {
          error_log("Exception in addFieldCallback: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }

  public function updateFieldCallback() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');

          $field_id = intval($_POST['field_id'] ?? 0);
          $label = sanitize_text_field($_POST['label'] ?? '');
          $name = sanitize_text_field($_POST['name'] ?? '');
          $type = sanitize_text_field($_POST['type'] ?? '');
          $required = isset($_POST['required']) ? (bool) $_POST['required'] : false;
          $placeholder = sanitize_text_field($_POST['placeholder'] ?? '');

          if (empty($field_id) || empty($label)) {
              wp_send_json_error(['message' => 'Missing required fields.']);
              return;
          }

          $field = Field::find($field_id);
          if (!$field) {
              wp_send_json_error(['message' => 'Field not found.']);
              return;
          }

          $config = json_decode($field->getConfig(), true) ?: [];
          $nested_field_definitions = [];
          if ($type === 'repeater') {
              $max_sets = intval($_POST['max_sets'] ?? 0);
              $nested_field_definitions = json_decode(wp_unslash($_POST['nested_field_definitions'] ?? '[]'), true);
              $config = [
                  'max_sets' => $max_sets
              ];
          } elseif (in_array($type, ['checkbox', 'select', 'radio', 'button_group'])) {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              $config = [
                  'options' => $field_config['options'] ?? [],
                  'multiple' => (bool)($field_config['multiple'] ?? false)
              ];
          } elseif ($type === 'image') {
              if (!isset($config['return_type'])) {
                  $config['return_type'] = 'url';
              }
          } elseif ($type === 'taxonomy_term') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              $config['taxonomy'] = sanitize_text_field($field_config['taxonomy'] ?? 'category');
          } elseif ($type === 'color') {
              $config = [];
          }

          $data = [
              'label' => $label,
              'name' => $name,
              'type' => $type,
              'required' => $required,
              'placeholder' => $placeholder,
              'config' => json_encode($config)
          ];

          $this->field_service->updateField($field_id, $data);

          // If repeater, delete all existing children and re-insert nested fields as real DB rows
          if ($type === 'repeater') {
              global $wpdb;
              $fields_table = $wpdb->prefix . 'cc_fields';
              $wpdb->delete($fields_table, ['parent_field_id' => $field_id]);
              if (!empty($nested_field_definitions)) {
                  \CCC\Core\Database::migrateNestedFieldsToRowsRecursive($field->getComponentId(), $field_id, $nested_field_definitions);
              }
          }

          wp_send_json_success(['message' => 'Field updated successfully.']);

      } catch (\Exception $e) {
          error_log("Exception in updateFieldCallback: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }

  public function getPosts() {
      check_ajax_referer('ccc_nonce', 'nonce');

      $post_type = sanitize_text_field($_POST['post_type'] ?? 'page');
      $posts = get_posts([
          'post_type' => $post_type,
          'post_status' => 'publish',
          'numberposts' => -1,
          'orderby' => 'title',
          'order' => 'ASC'
      ]);

      $post_list = array_map(function ($post) {
          $components = get_post_meta($post->ID, '_ccc_components', true);
          return [
              'id' => $post->ID,
              'title' => $post->post_title,
              'has_components' => !empty($components)
          ];
      }, $posts);

      wp_send_json_success(['posts' => $post_list]);
  }

  public function deleteComponent() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');

          $component_id = intval($_POST['component_id'] ?? 0);
          if (!$component_id) {
              wp_send_json_error(['message' => 'Invalid component ID.']);
              return;
          }

          $component = Component::find($component_id);
          if (!$component || !$component->delete()) {
              wp_send_json_error(['message' => 'Failed to delete component.']);
              return;
          }

          wp_send_json_success(['message' => 'Component deleted successfully.']);

      } catch (\Exception $e) {
          error_log("Exception in deleteComponent: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }

  public function deleteField() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');

          $field_id = intval($_POST['field_id'] ?? 0);
          if (!$field_id) {
              wp_send_json_error(['message' => 'Invalid field ID.']);
              return;
          }

          if (!$this->field_service->deleteField($field_id)) {
              wp_send_json_error(['message' => 'Failed to delete field.']);
              return;
          }

          wp_send_json_success(['message' => 'Field deleted successfully.']);

      } catch (\Exception $e) {
          error_log("Exception in deleteField: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }

  public function saveAssignments() {
      check_ajax_referer('ccc_nonce', 'nonce');

      $post_ids = isset($_POST['post_ids']) ? array_map('intval', (array)$_POST['post_ids']) : [];
      $components = isset($_POST['components']) ? json_decode(wp_unslash($_POST['components']), true) : [];

      foreach ($post_ids as $post_id) {
          if ($post_id === 0) {
              $post_type = sanitize_text_field($_POST['post_type'] ?? 'page');
              $posts = get_posts([
                  'post_type' => $post_type,
                  'post_status' => 'publish',
                  'numberposts' => -1
              ]);
              foreach ($posts as $post) {
                  update_post_meta($post->ID, '_ccc_components', $components);
              }
          } else {
              update_post_meta($post_id, '_ccc_components', $components);
          }
      }

      wp_send_json_success(['message' => 'Assignments saved successfully']);
  }

  public function saveFieldValues() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');

          $post_id = intval($_POST['post_id'] ?? 0);
          $field_values = $_POST['ccc_field_values'] ?? [];

          if (!$post_id) {
              wp_send_json_error(['message' => 'Invalid post ID.']);
              return;
          }

          FieldValue::saveMultiple($post_id, $field_values);
          wp_send_json_success(['message' => 'Field values saved successfully']);

      } catch (\Exception $e) {
          error_log("Exception in saveFieldValues: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }

  public function getComponentFields() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');

          $component_id = intval($_POST['component_id'] ?? 0);
          $post_id = intval($_POST['post_id'] ?? 0);
          $instance_id = sanitize_text_field($_POST['instance_id'] ?? '');

          error_log("CCC AjaxHandler: getComponentFields - component_id: $component_id, post_id: $post_id, instance_id: $instance_id");

          if (!$component_id) {
              wp_send_json_error(['message' => 'Invalid component ID.']);
              return;
          }

          // Use the recursive tree loader instead of flat loader
          $fields = Field::findFieldsTree($component_id);
          
          // Helper to recursively convert Field objects to arrays
          function ccc_field_to_array($field) {
              $config_json = $field->getConfig();
              $decoded_config = [];
              if (!empty($config_json)) {
                  $decoded_config = json_decode($config_json, true);
                  if (json_last_error() !== JSON_ERROR_NONE) {
                      error_log("CCC AjaxHandler: JSON decode error for field {$field->getId()}: " . json_last_error_msg());
                      $decoded_config = [];
                  }
              }
              $arr = [
                  'id' => $field->getId(),
                  'label' => $field->getLabel(),
                  'name' => $field->getName(),
                  'type' => $field->getType(),
                  'value' => '', // Value loading for nested fields can be added if needed
                  'config' => $decoded_config,
                  'required' => $field->getRequired(),
                  'placeholder' => $field->getPlaceholder(),
                  'children' => []
              ];
              if ($field->getType() === 'repeater' && is_array($field->getChildren()) && count($field->getChildren()) > 0) {
                  $arr['children'] = array_map('ccc_field_to_array', $field->getChildren());
              }
              return $arr;
          }

          $field_data = array_map('ccc_field_to_array', $fields);

          // Render the accordion HTML for this component instance
          ob_start();
          $component = Component::find($component_id);
          if ($component) {
              // Use the same render logic as renderComponentAccordion
              $manager = new \CCC\Admin\MetaBoxManager();
              $comp = [
                  'id' => $component->getId(),
                  'name' => $component->getName(),
                  'handle_name' => $component->getHandleName(),
                  'order' => 0, // Will be set by JS
                  'instance_id' => $instance_id
              ];
              $field_values = [];
              foreach ($fields as $field) {
                  $field_values[$field->getId()] = '';
              }
              $manager->renderComponentAccordion($comp, 0, [$instance_id => $field_values]);
          }
          $accordion_html = ob_get_clean();

          wp_send_json_success(['fields' => $field_data, 'html' => $accordion_html]);

      } catch (\Exception $e) {
          error_log("Exception in getComponentFields: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }

  public function getPostsWithComponents() {
      check_ajax_referer('ccc_nonce', 'nonce');

      $post_type = sanitize_text_field($_POST['post_type'] ?? 'page');
      $posts = get_posts([
          'post_type' => $post_type,
          'post_status' => 'publish',
          'numberposts' => -1,
          'orderby' => 'title',
          'order' => 'ASC'
      ]);

      $post_list = array_map(function ($post) {
          $components = get_post_meta($post->ID, '_ccc_components', true);
          $assigned_components = [];
          
          if (is_array($components)) {
              $assigned_components = array_map(function($comp) {
                  return intval($comp['id']);
              }, $components);
          }
          
          return [
              'id' => $post->ID,
              'title' => $post->post_title,
              'status' => $post->post_status,
              'has_components' => !empty($components),
              'assigned_components' => array_unique($assigned_components)
          ];
      }, $posts);

      wp_send_json_success(['posts' => $post_list]);
  }

  public function saveComponentAssignments() {
      check_ajax_referer('ccc_nonce', 'nonce');

      $assignments = json_decode(wp_unslash($_POST['assignments'] ?? '{}'), true);
      
      if (!is_array($assignments)) {
          wp_send_json_error(['message' => 'Invalid assignments data']);
          return;
      }

      foreach ($assignments as $post_id => $component_data_array) {
          $post_id = intval($post_id);
      
          if (!$post_id) continue;
     
          $existing_components = get_post_meta($post_id, '_ccc_components', true);
          if (!is_array($existing_components)) {
              $existing_components = [];
          }

          $new_components_data = [];
          $current_order = 0;

          foreach ($component_data_array as $incoming_comp) {
              $component_id = intval($incoming_comp['id']);
              
              $existing_instance = null;
              foreach ($existing_components as $ec) {
                  if ($ec['id'] === $component_id) {
                      $existing_instance = $ec;
                      break;
                  }
              }

              $instance_id = $existing_instance['instance_id'] ?? (time() . '_' . $component_id . '_' . uniqid());

              $new_components_data[] = [
                  'id' => $component_id,
                  'name' => sanitize_text_field($incoming_comp['name']),
                  'handle_name' => sanitize_text_field($incoming_comp['handle_name']),
                  'order' => $current_order++,
                  'instance_id' => $instance_id
              ];
          }
          
          update_post_meta($post_id, '_ccc_components', $new_components_data);
      }

      wp_send_json_success(['message' => 'Component assignments saved successfully']);
  }

  public function updateFieldOrder() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');

          $component_id = intval($_POST['component_id'] ?? 0);
          $field_order = json_decode(wp_unslash($_POST['field_order'] ?? '[]'), true);

          if (!$component_id || !is_array($field_order)) {
              wp_send_json_error(['message' => 'Invalid component ID or field order.']);
              return;
          }

          // Update the order of fields in the database
          global $wpdb;
          $fields_table = $wpdb->prefix . 'cc_fields';
          
          foreach ($field_order as $index => $field_id) {
              $wpdb->update(
                  $fields_table,
                  ['field_order' => $index],
                  ['id' => intval($field_id), 'component_id' => $component_id],
                  ['%d'],
                  ['%d', '%d']
              );
          }

          wp_send_json_success(['message' => 'Field order updated successfully']);

      } catch (\Exception $e) {
          error_log("Exception in updateFieldOrder: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }

  public function updateComponentFields() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');

          $component_id = intval($_POST['component_id'] ?? 0);
          $fields_data = json_decode(wp_unslash($_POST['fields'] ?? '[]'), true);

          if (!$component_id || !is_array($fields_data)) {
              wp_send_json_error(['message' => 'Invalid component ID or fields data.']);
              return;
          }

          error_log("CCC AjaxHandler: updateComponentFields - Updating component $component_id with " . count($fields_data) . " fields");

          // Get existing fields for this component
          $existing_fields = Field::findByComponent($component_id);
          $existing_field_ids = array_map(function($field) {
              return $field->getId();
          }, $existing_fields);

          // Update each field
          foreach ($fields_data as $field_data) {
              $field_id = intval($field_data['id'] ?? 0);
              
              if (!$field_id || !in_array($field_id, $existing_field_ids)) {
                  error_log("CCC AjaxHandler: Skipping invalid field ID: $field_id");
                  continue;
              }

              $field = Field::find($field_id);
              if (!$field) {
                  error_log("CCC AjaxHandler: Field not found: $field_id");
                  continue;
              }

              // Update field properties
              $field->setLabel(sanitize_text_field($field_data['label'] ?? ''));
              $field->setName(sanitize_title($field_data['name'] ?? ''));
              $field->setType(sanitize_text_field($field_data['type'] ?? ''));
              $field->setRequired(isset($field_data['required']) ? (bool) $field_data['required'] : false);
              $field->setPlaceholder(sanitize_text_field($field_data['placeholder'] ?? ''));

              // Handle field configuration
              if (isset($field_data['config']) && is_array($field_data['config'])) {
                  $config = $field_data['config'];
                  
                  if ($field->getType() === 'repeater') {
                      $nested_fields = $config['nested_fields'] ?? [];
                      $sanitized_nested_fields = $this->sanitizeNestedFieldDefinitions($nested_fields);
                      
                      $config = [
                          'max_sets' => intval($config['max_sets'] ?? 0),
                          'nested_fields' => $sanitized_nested_fields
                      ];
                  }
                  
                  $field->setConfig(json_encode($config));
              }

              $field->save();
              error_log("CCC AjaxHandler: Updated field: " . $field->getName());
          }

          wp_send_json_success(['message' => 'Component fields updated successfully']);

      } catch (\Exception $e) {
          error_log("Exception in updateComponentFields: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }

  public function updateFieldFromHierarchy() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');

          $field_id = intval($_POST['field_id'] ?? 0);
          $label = sanitize_text_field($_POST['label'] ?? '');
          $name = sanitize_text_field($_POST['name'] ?? '');
          $type = sanitize_text_field($_POST['type'] ?? '');
          $required = isset($_POST['required']) ? (bool) $_POST['required'] : false;
          $placeholder = sanitize_text_field($_POST['placeholder'] ?? '');

          if (empty($field_id) || empty($label)) {
              wp_send_json_error(['message' => 'Missing required fields.']);
              return;
          }

          $field = Field::find($field_id);
          if (!$field) {
              wp_send_json_error(['message' => 'Field not found.']);
              return;
          }

          $field->setLabel($label);
          $field->setRequired($required);
          $field->setPlaceholder($placeholder);
          $field->setType($type);

          $config = json_decode($field->getConfig(), true) ?: [];
          
          // Handle field configuration based on type
          if ($type === 'repeater') {
              $field_config = json_decode(wp_unslash($_POST['config'] ?? '{}'), true);
              $nested_fields = $field_config['nested_fields'] ?? null;
              if (is_array($nested_fields)) {
                  $sanitized_nested_fields = $this->sanitizeNestedFieldDefinitions($nested_fields);
                  $config = [
                      'max_sets' => intval($field_config['max_sets'] ?? 0),
                      'nested_fields' => $sanitized_nested_fields
                  ];
                  error_log("CCC AjaxHandler: updateFieldFromHierarchy - Updated repeater config: " . json_encode($config));
                  // Only delete/re-insert if we have a valid array
                  global $wpdb;
                  $fields_table = $wpdb->prefix . 'cc_fields';
                  $wpdb->delete($fields_table, ['parent_field_id' => $field_id]);
                  if (!empty($sanitized_nested_fields)) {
                      \CCC\Core\Database::migrateNestedFieldsToRowsRecursive($field->getComponentId(), $field_id, $sanitized_nested_fields);
                  }
              } else {
                  // Do not delete anything if nested_fields is not a valid array
                  $config = [
                      'max_sets' => intval($field_config['max_sets'] ?? 0),
                      'nested_fields' => []
                  ];
                  error_log("CCC AjaxHandler: updateFieldFromHierarchy - nested_fields not a valid array, skipping delete/re-insert");
              }
          } elseif (in_array($type, ['checkbox', 'select', 'radio', 'button_group'])) {
              $field_config = json_decode(wp_unslash($_POST['config'] ?? '{}'), true);
              $config = [
                  'options' => $field_config['options'] ?? [],
                  'multiple' => (bool)($field_config['multiple'] ?? false)
              ];
          } elseif ($type === 'image') {
              $field_config = json_decode(wp_unslash($_POST['config'] ?? '{}'), true);
              $config = [
                  'return_type' => sanitize_text_field($field_config['return_type'] ?? 'url')
              ];
          } elseif ($type === 'taxonomy_term') {
              $field_config = json_decode(wp_unslash($_POST['config'] ?? '{}'), true);
              $config = [
                  'taxonomy' => sanitize_text_field($field_config['taxonomy'] ?? 'category')
              ];
          } elseif ($type === 'color') {
              $config = [];
          }
          
          $data = [
              'label' => $label,
              'name' => $name,
              'type' => $type,
              'required' => $required,
              'placeholder' => $placeholder,
              'config' => json_encode($config)
          ];

          $this->field_service->updateField($field_id, $data);

          wp_send_json_success(['message' => 'Field updated successfully.']);

      } catch (\Exception $e) {
          error_log("Exception in updateFieldFromHierarchy: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }
}
