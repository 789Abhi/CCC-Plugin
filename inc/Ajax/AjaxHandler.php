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
      add_action('wp_ajax_ccc_save_metabox_components', [$this, 'saveMetaboxComponents']);
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

          wp_send_json_success($components);

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
          $parent_field_id = isset($_POST['parent_field_id']) ? intval($_POST['parent_field_id']) : null;

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
          } elseif ($type === 'video') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              $config = [
                  'return_type' => sanitize_text_field($field_config['return_type'] ?? 'url'),
                  'sources' => is_array($field_config['sources']) ? $field_config['sources'] : ['file', 'youtube', 'vimeo', 'url'],
                  'player_options' => is_array($field_config['player_options']) ? $field_config['player_options'] : [
                      'controls' => true,
                      'autoplay' => false,
                      'muted' => false,
                      'loop' => false,
                      'download' => true,
                      'fullscreen' => true,
                      'pictureInPicture' => true
                  ]
              ];
          } elseif ($type === 'taxonomy_term') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              $config = [
                  'taxonomy' => sanitize_text_field($field_config['taxonomy'] ?? 'category')
              ];
          } elseif ($type === 'color') {
              $config = [];
          }

          // Calculate the next field order
          global $wpdb;
          $fields_table = $wpdb->prefix . 'cc_fields';
          $max_order = $wpdb->get_var($wpdb->prepare(
              "SELECT MAX(field_order) FROM $fields_table WHERE component_id = %d",
              $component_id
          ));
          $next_order = ($max_order !== null) ? intval($max_order) + 1 : 0;

          $field = new \CCC\Models\Field([
              'component_id' => $component_id,
              'parent_field_id' => $parent_field_id,
              'label' => $label,
              'name' => $name,
              'type' => $type,
              'config' => json_encode($config),
              'field_order' => $next_order,
              'required' => $required,
              'placeholder' => $placeholder
          ]);

          error_log('CCC AjaxHandler: addFieldCallback POST data: ' . json_encode($_POST));

          if ($field->save()) {
              error_log('CCC AjaxHandler: Field saved, ID: ' . $field->getId());
              // If repeater, save nested fields as real DB rows
              if ($type === 'repeater' && !empty($nested_field_definitions)) {
                  \CCC\Core\Database::migrateNestedFieldsToRowsRecursive($component_id, $field->getId(), $nested_field_definitions);
              }
              error_log("CCC AjaxHandler: Successfully saved field {$name} with config: " . json_encode($config));
              wp_send_json_success(['message' => 'Field added successfully.']);
          } else {
              global $wpdb;
              error_log('CCC AjaxHandler: Failed to save field. DB error: ' . $wpdb->last_error);
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
          } elseif ($type === 'video') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              $config = [
                  'return_type' => sanitize_text_field($field_config['return_type'] ?? 'url'),
                  'sources' => is_array($field_config['sources']) ? $field_config['sources'] : ['file', 'youtube', 'vimeo', 'url'],
                  'player_options' => is_array($field_config['player_options']) ? $field_config['player_options'] : [
                      'controls' => true,
                      'autoplay' => false,
                      'muted' => false,
                      'loop' => false,
                      'download' => true,
                      'fullscreen' => true,
                      'pictureInPicture' => true
                  ]
              ];
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
                  if (!empty($components)) {
                      // Page is being checked - restore previous components if available
                      $previous_components = get_post_meta($post->ID, '_ccc_previous_components', true);
                      if (!empty($previous_components) && is_array($previous_components)) {
                          update_post_meta($post->ID, '_ccc_components', $previous_components);
                          error_log("CCC AjaxHandler: Post {$post->ID} rechecked, restoring previous components: " . json_encode($previous_components));
                      } else {
                  update_post_meta($post->ID, '_ccc_components', $components);
              }
                      update_post_meta($post->ID, '_ccc_had_components', '1');
                      update_post_meta($post->ID, '_ccc_assigned_via_main_interface', '1');
                  } else {
                      // Page is being unchecked - store current components for later restoration
                      $current_components = get_post_meta($post->ID, '_ccc_components', true);
                      if (!empty($current_components) && is_array($current_components)) {
                          update_post_meta($post->ID, '_ccc_previous_components', $current_components);
                          error_log("CCC AjaxHandler: Post {$post->ID} unchecked, storing previous components: " . json_encode($current_components));
                      }
                      update_post_meta($post->ID, '_ccc_components', []);
                      delete_post_meta($post->ID, '_ccc_had_components'); // Hide metabox
                      delete_post_meta($post->ID, '_ccc_assigned_via_main_interface'); // Remove main interface flag
                      // DO NOT DELETE FIELD VALUES - they remain in database for when page is rechecked
                      error_log("CCC AjaxHandler: Post {$post->ID} unchecked from main interface, hiding metabox but preserving field values in database");
                  }
              }
          } else {
              if (!empty($components)) {
                  // Page is being checked - restore previous components if available
                  $previous_components = get_post_meta($post_id, '_ccc_previous_components', true);
                  if (!empty($previous_components) && is_array($previous_components)) {
                      update_post_meta($post_id, '_ccc_components', $previous_components);
                      error_log("CCC AjaxHandler: Post {$post_id} rechecked, restoring previous components: " . json_encode($previous_components));
          } else {
              update_post_meta($post_id, '_ccc_components', $components);
                  }
                  update_post_meta($post_id, '_ccc_had_components', '1');
                  update_post_meta($post_id, '_ccc_assigned_via_main_interface', '1');
              } else {
                  // Page is being unchecked - store current components for later restoration
                  $current_components = get_post_meta($post_id, '_ccc_components', true);
                  if (!empty($current_components) && is_array($current_components)) {
                      update_post_meta($post_id, '_ccc_previous_components', $current_components);
                      error_log("CCC AjaxHandler: Post {$post_id} unchecked, storing previous components: " . json_encode($current_components));
                  }
                  update_post_meta($post_id, '_ccc_components', []);
                  delete_post_meta($post_id, '_ccc_had_components'); // Hide metabox
                  delete_post_meta($post_id, '_ccc_assigned_via_main_interface'); // Remove main interface flag
                  // DO NOT DELETE FIELD VALUES - they remain in database for when page is rechecked
                  error_log("CCC AjaxHandler: Post {$post_id} unchecked from main interface, hiding metabox but preserving field values in database");
              }
          }
      }

      wp_send_json_success(['message' => 'Assignments saved successfully']);
  }

  public function saveFieldValues() {
      error_log('CCC DEBUG: saveFieldValues payload: ' . print_r($_POST, true));
      try {
          check_ajax_referer('ccc_nonce', 'nonce');

          $post_id = intval($_POST['post_id'] ?? 0);
          $field_values = $_POST['field_values'] ?? $_POST['ccc_field_values'] ?? [];

          if (!$post_id) {
              wp_send_json_error(['message' => 'Invalid post ID.']);
              return;
          }

          // Decode JSON if needed
          if (is_string($field_values)) {
              $field_values = stripslashes($field_values); // Remove extra slashes
              $field_values = json_decode($field_values, true);
          }
          error_log('CCC DEBUG: Decoded field_values: ' . print_r($field_values, true));

          $success = FieldValue::saveMultiple($post_id, $field_values);
          if ($success) {
              error_log("CCC DEBUG saveFieldValues - SUCCESS: Saved " . count($field_values) . " field values for post $post_id");
              
          wp_send_json_success(['message' => 'Field values saved successfully']);
          } else {
              error_log("CCC DEBUG saveFieldValues - FAILED: Could not save field values for post $post_id");
              wp_send_json_error(['message' => 'Failed to save field values']);
          }

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
          error_log("CCC AjaxHandler: getComponentFields - Found " . count($fields) . " fields for component $component_id");
          
          // Helper to recursively convert Field objects to arrays
          $fieldToArray = function($field) use (&$fieldToArray, $post_id, $instance_id) {
              $config_json = $field->getConfig();
              $decoded_config = [];
              if (!empty($config_json)) {
                  $decoded_config = json_decode($config_json, true);
                  if (json_last_error() !== JSON_ERROR_NONE) {
                      error_log("CCC AjaxHandler: JSON decode error for field {$field->getId()}: " . json_last_error_msg());
                      $decoded_config = [];
                  }
              }
              // Fetch the saved value for this field, post, and instance
              $value = '';
              if ($post_id && $instance_id) {
                  $value = \CCC\Models\FieldValue::getValue($field->getId(), $post_id, $instance_id);
                  
                  // Handle select multiple fields
                  if ($field->getType() === 'select' && isset($decoded_config['multiple']) && $decoded_config['multiple']) {
                      if (is_string($value)) {
                          $value = $value ? explode(',', $value) : [];
                      }
                  }
                  // Handle checkbox fields (always multiple)
                  if ($field->getType() === 'checkbox') {
                      if (is_string($value)) {
                          $value = $value ? explode(',', $value) : [];
                      }
                  }
              }
              $arr = [
                  'id' => $field->getId(),
                  'label' => $field->getLabel(),
                  'name' => $field->getName(),
                  'type' => $field->getType(),
                  'value' => $value,
                  'config' => $decoded_config,
                  'required' => $field->getRequired(),
                  'placeholder' => $field->getPlaceholder(),
                  'children' => []
              ];
              // Handle repeater fields - check both database children and config nested_fields
              if ($field->getType() === 'repeater') {
                  error_log("CCC AjaxHandler: Processing repeater field {$field->getId()} ({$field->getName()})");
                  error_log("CCC AjaxHandler: Field config: " . json_encode($decoded_config));
                  
                  $children = $field->getChildren();
                  error_log("CCC AjaxHandler: Database children count: " . (is_array($children) ? count($children) : 'not array'));
                  
                  if (is_array($children) && count($children) > 0) {
                      // Traditional nested fields stored as database records
                      $arr['children'] = array_map(function($child) use (&$fieldToArray, $post_id, $instance_id) {
                          return $fieldToArray($child);
                      }, $children);
                      error_log("CCC AjaxHandler: Using database children for field {$field->getId()}: " . count($children) . " fields");
                  } elseif ($decoded_config && isset($decoded_config['nested_fields'])) {
                      // ChatGPT-created nested fields stored in config
                      $arr['children'] = $decoded_config['nested_fields'];
                      error_log("CCC AjaxHandler: Using nested_fields from config for field {$field->getId()}: " . count($decoded_config['nested_fields']) . " fields");
                  } else {
                      // No children found
                      $arr['children'] = [];
                      error_log("CCC AjaxHandler: No nested fields found for repeater field {$field->getId()}");
                  }
              }
              return $arr;
          };
          $field_data = array_map(function($field) use ($fieldToArray) { return $fieldToArray($field); }, $fields);

          wp_send_json_success(['fields' => $field_data]);

      } catch (\Exception $e) {
          error_log("Exception in getComponentFields: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }

  private function getFieldValuesForPost($post_id) {
      global $wpdb;
      $field_values_table = $wpdb->prefix . 'cc_field_values';
      $fields_table = $wpdb->prefix . 'cc_fields';
      
      $values = $wpdb->get_results(
          $wpdb->prepare(
              "SELECT field_id, instance_id, value FROM $field_values_table WHERE post_id = %d",
              $post_id
          ),
          ARRAY_A
      );
      
      $field_values = [];
      foreach ($values as $value) {
          $instance_id = $value['instance_id'] ?: 'default';
          $field_id = $value['field_id'];
          $raw_value = $value['value'];
          
          // Fetch field type and config
          $field = \CCC\Models\Field::find($field_id);
          if ($field) {
              $field_type = $field->getType();
              if ($field_type === 'select') {
                  $config = $field->getConfig();
                  if (is_string($config)) {
                      $config = json_decode($config, true);
                  }
                  $multiple = isset($config['multiple']) && $config['multiple'];
                  if ($multiple) {
                      $field_values[$instance_id][$field_id] = $raw_value ? explode(',', $raw_value) : [];
                  } else {
                      $field_values[$instance_id][$field_id] = $raw_value;
                  }
              } elseif ($field_type === 'checkbox') {
                  // Checkbox fields are always multiple by default
                  $field_values[$instance_id][$field_id] = $raw_value ? explode(',', $raw_value) : [];
              } else {
                  $field_values[$instance_id][$field_id] = $raw_value;
              }
          } else {
              $field_values[$instance_id][$field_id] = $raw_value;
          }
      }
      
      return $field_values;
  }

  public function getPostsWithComponents() {
      check_ajax_referer('ccc_nonce', 'nonce');

      // Check if we're requesting a specific post
      $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
      
      if ($post_id) {
          // Return components for a specific post
          $post = get_post($post_id);
          if (!$post) {
              wp_send_json_error(['message' => 'Post not found']);
              return;
          }
          $components = get_post_meta($post_id, '_ccc_components', true);
          if (!is_array($components)) {
              $components = [];
          }
          
          // Get field values for this post with proper instance_id handling
          $field_values = $this->getFieldValuesForPost($post_id);
          
          // Sort components by 'order' property before returning
          usort($components, function($a, $b) {
              return ($a['order'] ?? 0) - ($b['order'] ?? 0);
          });
          
          wp_send_json_success([
              'components' => $components,
              'field_values' => $field_values
          ]);
          return;
      }

      // Return list of posts with components (original functionality)
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
          $assigned_via_main_interface = get_post_meta($post->ID, '_ccc_assigned_via_main_interface', true);
          
          if (is_array($components)) {
              $assigned_components = array_map(function($comp) {
                  return intval($comp['id']);
              }, $components);
          }
          
          $result = [
              'id' => $post->ID,
              'title' => $post->post_title,
              'status' => $post->post_status,
              'has_components' => !empty($components),
              'assigned_components' => array_unique($assigned_components),
              'assigned_via_main_interface' => !empty($assigned_via_main_interface)
          ];
          
          // DEBUG: Log the decision making for each post
          error_log("CCC DEBUG getPostsWithComponents - Post {$post->ID} ({$post->post_title}):");
          error_log("  - Has components: " . ($result['has_components'] ? 'YES' : 'NO'));
          error_log("  - Assigned via main interface: " . ($result['assigned_via_main_interface'] ? 'YES' : 'NO'));
          error_log("  - Will be selected in main interface: " . ($result['assigned_via_main_interface'] ? 'YES' : 'NO')); // Changed: only depends on main interface flag
          error_log("  - Component count: " . count($components));
          
          return $result;
      }, $posts);

      wp_send_json_success(['posts' => $post_list]);
  }

  public function saveComponentAssignments() {
      check_ajax_referer('ccc_nonce', 'nonce');
      error_log("CCC DEBUG saveComponentAssignments - START");
      error_log("  - POST data: " . json_encode($_POST));

      // Check if we're saving for a specific post (React metabox format)
      $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
      $components = isset($_POST['components']) ? json_decode(wp_unslash($_POST['components']), true) : null;
      
      if ($post_id && is_array($components)) {
          // Metabox is now read-only - prevent saving from metabox
          wp_send_json_error(['message' => 'Component management is disabled from the page metabox. Please use the main plugin interface to manage components.']);
          return;
      }

      // Original format for bulk assignments (main plugin interface)
      $assignments = json_decode(wp_unslash($_POST['assignments'] ?? '{}'), true);
      
      if (!is_array($assignments)) {
          wp_send_json_error(['message' => 'Invalid assignments data']);
          return;
      }

      foreach ($assignments as $post_id => $component_data_array) {
          $post_id = intval($post_id);
          if (!$post_id) continue;
          error_log("CCC DEBUG saveComponentAssignments - Processing post: $post_id");
          
          if (is_null($component_data_array)) {
              // Page is being checked - restore previous components if available
              $previous_components = get_post_meta($post_id, '_ccc_previous_components', true);
              if (!empty($previous_components) && is_array($previous_components)) {
                  update_post_meta($post_id, '_ccc_components', $previous_components);
                  error_log("CCC DEBUG saveComponentAssignments - Post $post_id rechecked, restoring previous components: " . json_encode($previous_components));
              } else {
                  // No previous components, assign empty array (will be populated later)
                  update_post_meta($post_id, '_ccc_components', []);
                  error_log("CCC DEBUG saveComponentAssignments - Post $post_id rechecked, no previous components to restore");
              }
              update_post_meta($post_id, '_ccc_had_components', '1');
              update_post_meta($post_id, '_ccc_assigned_via_main_interface', '1');
              error_log("CCC DEBUG saveComponentAssignments - Post $post_id: checked from main interface, showing metabox");
          } else if (is_array($component_data_array)) {
              // Page is being unchecked - store current components for later restoration
              $current_components = get_post_meta($post_id, '_ccc_components', true);
              if (!empty($current_components) && is_array($current_components)) {
                  update_post_meta($post_id, '_ccc_previous_components', $current_components);
                  error_log("CCC DEBUG saveComponentAssignments - Post $post_id unchecked, storing previous components: " . json_encode($current_components));
              }
              update_post_meta($post_id, '_ccc_components', []);
              delete_post_meta($post_id, '_ccc_had_components'); // Hide metabox
              delete_post_meta($post_id, '_ccc_assigned_via_main_interface'); // Remove main interface flag
              // DO NOT DELETE FIELD VALUES - they remain in database for when page is rechecked
              error_log("CCC DEBUG saveComponentAssignments - Post $post_id: unchecked from main interface, hiding metabox but preserving field values in database");
          }
          
          $final_components = get_post_meta($post_id, '_ccc_components', true);
          $final_assigned_via_main = get_post_meta($post_id, '_ccc_assigned_via_main_interface', true);
          $final_had_components = get_post_meta($post_id, '_ccc_had_components', true);
          error_log("CCC DEBUG saveComponentAssignments - Post $post_id FINAL STATE:");
          error_log("  - Final components: " . json_encode($final_components));
          error_log("  - Final _ccc_assigned_via_main_interface: " . ($final_assigned_via_main ? 'YES' : 'NO'));
          error_log("  - Final _ccc_had_components: " . ($final_had_components ? 'YES' : 'NO'));
                  }

      error_log("CCC DEBUG saveComponentAssignments - END");
      wp_send_json_success(['message' => 'Component assignments saved successfully']);
  }

  public function saveMetaboxComponents() {
      check_ajax_referer('ccc_nonce', 'nonce');
      error_log("CCC DEBUG saveMetaboxComponents - START");
      $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
      $components = isset($_POST['components']) ? json_decode(wp_unslash($_POST['components']), true) : null;
      error_log("  - Post ID: $post_id");
      error_log("  - Components data: " . json_encode($components));
      error_log("  - Components count: " . (is_array($components) ? count($components) : 'NOT_ARRAY'));
      
      if (!$post_id || !is_array($components)) {
          wp_send_json_error(['message' => 'Invalid post ID or components data']);
          return;
      }

      // Validate post exists
      $post = get_post($post_id);
      if (!$post) {
          wp_send_json_error(['message' => 'Post not found']);
          return;
      }

      // Check user permissions
      if (!current_user_can('edit_post', $post_id)) {
          wp_send_json_error(['message' => 'Insufficient permissions']);
          return;
      }

      // Process components data
      $processed_components = [];
      $current_order = 0;

      foreach ($components as $comp) {
          $component_id = intval($comp['id']);
          
          // Validate component exists
          $component = Component::find($component_id);
          if (!$component) {
              error_log("CCC AjaxHandler: Component not found for ID: $component_id");
              continue;
          }

          $processed_components[] = [
                  'id' => $component_id,
              'name' => sanitize_text_field($comp['name']),
              'handle_name' => sanitize_text_field($comp['handle_name']),
              'order' => isset($comp['order']) ? intval($comp['order']) : 0,
              'instance_id' => sanitize_text_field($comp['instance_id'] ?? ''),
              'isHidden' => isset($comp['isHidden']) ? (bool)$comp['isHidden'] : false
              ];
          }
          
      // Sort by order
      usort($processed_components, function($a, $b) {
          return $a['order'] - $b['order'];
      });
      
      // Save components
      update_post_meta($post_id, '_ccc_components', $processed_components);
          
      // IMPORTANT: Metabox saves should NOT affect main interface selection
      // We preserve the _ccc_assigned_via_main_interface flag to maintain main interface state
      // This ensures metabox changes don't affect main interface selection
      
      // Update the had_components flag based on whether components are currently assigned
      if (!empty($processed_components)) {
          update_post_meta($post_id, '_ccc_had_components', '1');
          error_log("CCC AjaxHandler: Metabox save - Post {$post_id} has components, setting _ccc_had_components flag");
      } else {
          // For metabox saves, preserve the flag to keep metabox visible
          // This ensures the metabox stays visible even when all components are removed
          error_log("CCC AjaxHandler: Metabox save - Post {$post_id} has no components, preserving _ccc_had_components flag");
      }

      // DEBUG: Log the final state after metabox save
      $final_components = get_post_meta($post_id, '_ccc_components', true);
      $final_assigned_via_main = get_post_meta($post_id, '_ccc_assigned_via_main_interface', true);
      $final_had_components = get_post_meta($post_id, '_ccc_had_components', true);
      error_log("CCC DEBUG saveMetaboxComponents - FINAL STATE:");
      error_log("  - Final components: " . json_encode($final_components));
      error_log("  - Final _ccc_assigned_via_main_interface: " . ($final_assigned_via_main ? 'YES' : 'NO'));
      error_log("  - Final _ccc_had_components: " . ($final_had_components ? 'YES' : 'NO'));
      error_log("  - Components count: " . (is_array($final_components) ? count($final_components) : 'NOT_ARRAY'));
      error_log("CCC DEBUG saveMetaboxComponents - END");

      wp_send_json_success([
          'message' => 'Components saved successfully',
          'components' => $processed_components
      ]);
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
              error_log('CCC AjaxHandler: updateFieldFromHierarchy ENTRY');
              error_log('CCC AjaxHandler: updateFieldFromHierarchy RAW POST: ' . json_encode($_POST));
              $field_config = json_decode(wp_unslash($_POST['config'] ?? '{}'), true);
              error_log('CCC AjaxHandler: updateFieldFromHierarchy DECODED CONFIG: ' . json_encode($field_config));
              $nested_fields = $field_config['nested_fields'] ?? null;
              error_log('CCC AjaxHandler: updateFieldFromHierarchy nested_fields type: ' . gettype($nested_fields) . ' value: ' . json_encode($nested_fields));
              if (is_array($nested_fields)) {
                  error_log('CCC AjaxHandler: updateFieldFromHierarchy - nested_fields is a valid array, proceeding to sanitize and update');
                  $sanitized_nested_fields = $this->sanitizeNestedFieldDefinitions($nested_fields);
                  $config = [
                      'max_sets' => intval($field_config['max_sets'] ?? 0),
                      'nested_fields' => $sanitized_nested_fields
                  ];
                  error_log("CCC AjaxHandler: updateFieldFromHierarchy - Sanitized nested fields: " . json_encode($sanitized_nested_fields));
                  global $wpdb;
                  $table = $wpdb->prefix . 'cc_fields';
                  $delete_result = $wpdb->delete($table, ['parent_field_id' => intval($_POST['field_id'])]);
                  error_log('CCC AjaxHandler: updateFieldFromHierarchy - Deleted children result: ' . json_encode($delete_result));
                  \CCC\Core\Database::migrateNestedFieldsToRowsRecursive($field->getComponentId(), intval($_POST['field_id']), $sanitized_nested_fields);
                  error_log('CCC AjaxHandler: updateFieldFromHierarchy - Finished migrateNestedFieldsToRowsRecursive');
              } else {
                  error_log('CCC AjaxHandler: updateFieldFromHierarchy - nested_fields not a valid array, skipping delete/re-insert');
              }
              error_log('CCC AjaxHandler: updateFieldFromHierarchy EXIT');
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
