<?php
namespace CCC\Ajax;

use CCC\Services\ComponentService;
use CCC\Services\FieldService;
use CCC\Services\PostTypeTemplateService;
use CCC\Models\Component;
use CCC\Models\FieldValue;
use CCC\Models\Field;

defined('ABSPATH') || exit;

class AjaxHandler {
  private $component_service;
  private $field_service;

  public function __construct() {
      error_log("CCC DEBUG: AjaxHandler constructor called");
      try {
          $this->component_service = new ComponentService();
          $this->field_service = new FieldService();
          error_log("CCC: AjaxHandler constructed successfully");
      } catch (\Exception $e) {
          error_log("CCC: Error constructing AjaxHandler: " . $e->getMessage());
          error_log("CCC: Stack trace: " . $e->getTraceAsString());
      }
  }
  
  /**
   * Simple test endpoint for debugging
   */
  public function testEndpoint() {
      error_log("CCC: Test endpoint called successfully");
      wp_send_json_success([
          'message' => 'Test endpoint working', 
          'timestamp' => current_time('mysql'),
          'test_data' => [
              'posts' => [
                  ['id' => 1, 'title' => 'Test Post 1'],
                  ['id' => 2, 'title' => 'Test Post 2']
              ]
          ]
      ]);
  }

  public function init() {
      error_log("CCC DEBUG: AjaxHandler init method called");
      error_log("CCC DEBUG: Registering AJAX actions");
      
      add_action('wp_ajax_ccc_create_component', [$this, 'handleCreateComponent']);
      add_action('wp_ajax_ccc_get_components', [$this, 'getComponents']);
      add_action('wp_ajax_ccc_get_component_fields', [$this, 'getComponentFields']);
      add_action('wp_ajax_ccc_add_field', [$this, 'addFieldCallback']);
      add_action('wp_ajax_ccc_create_field', [$this, 'createFieldCallback']);
      add_action('wp_ajax_ccc_update_field', [$this, 'updateFieldCallback']);
      add_action('wp_ajax_ccc_get_posts', [$this, 'getPosts']);
      add_action('wp_ajax_ccc_get_posts_with_components', [$this, 'getPostsWithComponents']);
      error_log("CCC DEBUG: Registered AJAX action: ccc_get_posts_with_components");
      add_action('wp_ajax_ccc_save_component_assignments', [$this, 'saveComponentAssignments']);
      add_action('wp_ajax_ccc_save_metabox_components', [$this, 'saveMetaboxComponents']);
      add_action('wp_ajax_ccc_delete_component', [$this, 'deleteComponent']);
      add_action('wp_ajax_ccc_delete_field', [$this, 'deleteField']);
      add_action('wp_ajax_ccc_save_assignments', [$this, 'saveAssignments']);
      add_action('wp_ajax_ccc_save_field_values', [$this, 'saveFieldValues']);
      add_action('wp_ajax_nopriv_ccc_save_field_values', [$this, 'saveFieldValues']);
      add_action('wp_ajax_ccc_update_component_name', [$this, 'updateComponentName']);
      add_action('wp_ajax_ccc_check_template_content', [$this, 'checkTemplateContent']);
      add_action('wp_ajax_ccc_refresh_metabox_data', [$this, 'refreshMetaboxData']);
      add_action('wp_ajax_ccc_update_field_order', [$this, 'updateFieldOrder']);
      add_action('wp_ajax_ccc_update_component_fields', [$this, 'updateComponentFields']);
      add_action('wp_ajax_ccc_update_field_from_hierarchy', [$this, 'updateFieldFromHierarchy']);
      add_action('wp_ajax_ccc_get_posts_by_ids', [$this, 'getPostsByIds']);
      add_action('wp_ajax_ccc_search_posts', [$this, 'searchPosts']);
      add_action('wp_ajax_ccc_get_available_post_types', [$this, 'getAvailablePostTypes']);
      add_action('wp_ajax_ccc_get_available_taxonomies', [$this, 'getAvailableTaxonomies']);
      add_action('wp_ajax_ccc_get_taxonomies_for_post_type', [$this, 'getTaxonomiesForPostType']);
      add_action('wp_ajax_ccc_check_number_uniqueness', [$this, 'checkNumberUniqueness']);
      // Add more AJAX endpoints here
      add_action('wp_ajax_ccc_get_taxonomy_terms', [$this, 'getTaxonomyTerms']);
      add_action('wp_ajax_nopriv_ccc_get_taxonomy_terms', [$this, 'getTaxonomyTerms']);
      add_action('wp_ajax_ccc_get_users', [$this, 'getUsers']);
      add_action('wp_ajax_nopriv_ccc_get_users', [$this, 'getUsers']);
      add_action('wp_ajax_ccc_get_relationship_posts', [$this, 'getRelationshipPosts']);
      add_action('wp_ajax_nopriv_ccc_get_relationship_posts', [$this, 'getRelationshipPosts']);
      error_log("CCC DEBUG: Registered AJAX action: ccc_get_relationship_posts");
      
      add_action('wp_ajax_ccc_get_gallery_media', [$this, 'getGalleryMedia']);
      add_action('wp_ajax_nopriv_ccc_get_gallery_media', [$this, 'getGalleryMedia']);
      error_log("CCC DEBUG: Registered AJAX action: ccc_get_gallery_media");
      
      // API Key management
      add_action('wp_ajax_ccc_save_api_key', [$this, 'saveApiKey']);
      add_action('wp_ajax_ccc_get_api_key', [$this, 'getApiKey']);
      add_action('wp_ajax_ccc_get_api_key_for_use', [$this, 'getApiKeyForUse']);
      
      // Add test endpoint for debugging
      add_action('wp_ajax_ccc_test', [$this, 'testEndpoint']);
      
      // Add new AJAX actions for proxy API key system
      add_action('wp_ajax_ccc_proxy_openai_request', [$this, 'proxyOpenAIRequest']);
      add_action('wp_ajax_ccc_generate_proxy_key', [$this, 'generateProxyKey']);
      add_action('wp_ajax_ccc_validate_proxy_key', [$this, 'validateProxyKey']);
      add_action('wp_ajax_ccc_revoke_proxy_key', [$this, 'revokeProxyKey']);
      
      error_log("CCC DEBUG: All AJAX actions registered");
  }

  public function handleCreateComponent() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');

          $name = sanitize_text_field($_POST['name'] ?? '');
          $handle = $this->component_service->sanitizeHandle($_POST['handle'] ?? '');

          if (empty($name) || empty($handle)) {
              wp_send_json_error(['message' => 'Missing required fields']);
              return;
          }

          $component = $this->component_service->createComponent($name, $handle);
          wp_send_json_success([
              'message' => 'Component created successfully',
              'data' => [
                  'id' => $component->getId(),
                  'name' => $component->getName(),
                  'handle' => $component->getHandleName()
              ]
          ]);

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
          $handle = $this->component_service->sanitizeHandle($_POST['handle'] ?? '');

          if (!$component_id || empty($name) || empty($handle)) {
              wp_send_json_error(['message' => 'Missing required fields']);
              return;
          }

          $this->component_service->updateComponent($component_id, $name, $handle);
          
          // Note: Component assignments are automatically updated by the ComponentService
          // No need to manually refresh metaboxes as this can interfere with the update process
          
          wp_send_json_success(['message' => 'Component updated successfully']);

      } catch (\Exception $e) {
          error_log("Exception in updateComponentName: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }

  /**
   * Check if a component template has custom content that would be affected by name/handle changes
   */
  public function checkTemplateContent() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');

          $component_id = intval($_POST['component_id'] ?? 0);
          $new_handle = $this->component_service->sanitizeHandle($_POST['new_handle'] ?? '');

          if (!$component_id || empty($new_handle)) {
              wp_send_json_error(['message' => 'Missing required fields']);
              return;
          }

          // Get the component to check its current handle
          $component = \CCC\Models\Component::find($component_id);
          if (!$component) {
              wp_send_json_error(['message' => 'Component not found']);
              return;
          }

          $current_handle = $component->getHandleName();
          
          // Only check if the handle is actually changing
          if ($current_handle === $new_handle) {
              wp_send_json_success([
                  'has_custom_content' => false,
                  'message' => 'No changes needed'
              ]);
              return;
          }

          // Check if the current template has custom content
          $has_custom_content = $this->component_service->hasCustomTemplateContent($current_handle);
          
          if ($has_custom_content) {
              // Get the existing template content to show in the warning
              $existing_content = $this->component_service->getTemplateContent($current_handle);
              
              wp_send_json_success([
                  'has_custom_content' => true,
                  'current_handle' => $current_handle,
                  'new_handle' => $new_handle,
                  'existing_content' => $existing_content,
                  'message' => 'Template file has custom content that will be preserved'
              ]);
          } else {
              wp_send_json_success([
                  'has_custom_content' => false,
                  'message' => 'Template file has no custom content'
              ]);
          }

      } catch (\Exception $e) {
          error_log("Exception in checkTemplateContent: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }

  /**
   * Refresh metabox data for a specific post when component names change
   */
  public function refreshMetaboxData() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');
          
          $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
          if (!$post_id) {
              wp_send_json_error(['message' => 'Post ID is required']);
              return;
          }

          // Get the current component assignments
          $components = get_post_meta($post_id, '_ccc_components', true);
          if (!is_array($components) || empty($components)) {
              wp_send_json_success(['message' => 'No components assigned to this post']);
              return;
          }

          error_log("CCC DEBUG: refreshMetaboxData - Processing post {$post_id}");
          error_log("CCC DEBUG: Current components data: " . print_r($components, true));

          // Get updated component data while preserving existing structure
          $updated_components = [];
          foreach ($components as $component) {
              if (is_array($component) && isset($component['id'])) {
                  // Component is already in new format, just verify it exists in database
                  $component_id = $component['id'];
                  $component_obj = \CCC\Models\Component::find($component_id);
                  if ($component_obj) {
                      // Component exists in database, update with fresh data but preserve metadata
                      $updated_components[] = [
                          'id' => $component_obj->getId(),
                          'name' => $component_obj->getName(),
                          'handle_name' => $component_obj->getHandleName(),
                          'order' => intval($component['order'] ?? 0),
                          'instance_id' => $component['instance_id'] ?? '',
                          'isHidden' => isset($component['isHidden']) ? (bool)$component['isHidden'] : false
                      ];
                      error_log("CCC DEBUG: Updated component {$component_id} with database data");
                                        } else {
                          // Component not found in database - check if we have stored data
                          if (isset($component['name']) && isset($component['handle_name'])) {
                              error_log("CCC DEBUG: Component {$component_id} not found in database, but has stored data - marking as deleted");
                              // We have stored component data, but since the component is not in the database,
                              // it could be either renamed or deleted. For now, we'll mark it as deleted
                              // to ensure it shows with the red background. If it's actually renamed,
                              // the updateComponentAssignments method should have updated the post meta.
                              $updated_components[] = [
                                  'id' => $component_id,
                                  'name' => $component['name'],
                                  'handle_name' => $component['handle_name'],
                                  'order' => intval($component['order'] ?? 0),
                                  'instance_id' => $component['instance_id'] ?? '',
                                  'isHidden' => isset($component['isHidden']) ? (bool)$component['isHidden'] : false,
                                  'isDeleted' => true
                              ];
                          } else {
                              error_log("CCC DEBUG: Component {$component_id} not found in database and no stored data - marking as deleted");
                              // No stored data, mark as deleted
                              $updated_components[] = [
                                  'id' => $component_id,
                                  'name' => $component['name'] ?? 'Deleted Component',
                                  'handle_name' => $component['handle_name'] ?? 'deleted_component',
                                  'instance_id' => $component['instance_id'] ?? '',
                                  'isHidden' => isset($component['isHidden']) ? (bool)$component['isHidden'] : false,
                                  'isDeleted' => true
                              ];
                          }
                      }
              } elseif (is_numeric($component)) {
                  // Legacy format: just the ID, convert to new format
                  $component_id = $component;
                  $component_obj = \CCC\Models\Component::find($component_id);
                  if ($component_obj) {
                      $updated_components[] = [
                          'id' => $component_obj->getId(),
                          'name' => $component_obj->getName(),
                          'handle_name' => $component_obj->getHandleName(),
                          'order' => 0,
                          'instance_id' => '',
                          'isHidden' => false
                      ];
                      error_log("CCC DEBUG: Converted legacy component {$component_id} to new format");
                  } else {
                      // Component not found, mark as deleted
                      $updated_components[] = [
                          'id' => $component_id,
                          'name' => 'Deleted Component',
                          'handle_name' => 'deleted_component',
                          'order' => 0,
                          'instance_id' => '',
                          'isHidden' => false,
                          'isDeleted' => true
                      ];
                      error_log("CCC DEBUG: Component {$component_id} not found, marking as deleted");
                  }
              } else {
                  // Unknown format, skip
                  error_log("CCC DEBUG: Unknown component format, skipping: " . print_r($component, true));
              }
          }

          error_log("CCC DEBUG: Final updated components: " . print_r($updated_components, true));

          // Update the post meta with the preserved component data structure
          update_post_meta($post_id, '_ccc_components', $updated_components);
          
          // Also store the component details for easy access
          update_post_meta($post_id, '_ccc_component_details', $updated_components);

          wp_send_json_success([
              'message' => 'Metabox data refreshed successfully',
              'components' => $updated_components
          ]);

      } catch (\Exception $e) {
          error_log("Exception in refreshMetaboxData: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }

      /**
       * Refresh all metaboxes that have a specific component assigned
       */
      private function refreshAllMetaboxesWithComponent($component_id) {
          try {
              error_log("CCC DEBUG: refreshAllMetaboxesWithComponent called for component {$component_id}");
              
              // Get all posts that have this component assigned
              $posts = get_posts([
                  'post_type' => ['post', 'page'],
                  'post_status' => 'any',
                  'numberposts' => -1,
                  'meta_query' => [
                      [
                          'key' => '_ccc_components',
                          'value' => $component_id,
                          'compare' => 'LIKE'
                      ]
                  ]
              ]);

              error_log("CCC DEBUG: Found " . count($posts) . " posts to refresh for component {$component_id}");
              
              $refreshed_count = 0;
              foreach ($posts as $post) {
                  error_log("CCC DEBUG: Refreshing metabox for post {$post->ID} ({$post->post_title})");
                  // Call the refresh method for each post
                  $this->refreshMetaboxDataForPost($post->ID);
                  $refreshed_count++;
              }

              error_log("CCC AjaxHandler: Refreshed {$refreshed_count} metaboxes for component {$component_id}");

          } catch (\Exception $e) {
              error_log("Exception in refreshAllMetaboxesWithComponent: " . $e->getMessage());
          }
      }

      /**
       * Refresh metabox data for a specific post
       */
      private function refreshMetaboxDataForPost($post_id) {
          try {
              // Get the current component assignments
              $components = get_post_meta($post_id, '_ccc_components', true);
              if (!is_array($components) || empty($components)) {
                  return;
              }

              error_log("CCC DEBUG: refreshMetaboxDataForPost - Processing post {$post_id}");
              error_log("CCC DEBUG: Current components data: " . print_r($components, true));

              // Get updated component data while preserving existing structure
              $updated_components = [];
              foreach ($components as $component) {
                  if (is_array($component) && isset($component['id'])) {
                      // Component is already in new format, just verify it exists in database
                      $component_id = $component['id'];
                      $component_obj = \CCC\Models\Component::find($component_id);
                      if ($component_obj) {
                          // Component exists in database, update with fresh data but preserve metadata
                          $updated_components[] = [
                              'id' => $component_obj->getId(),
                              'name' => $component_obj->getName(),
                              'handle_name' => $component_obj->getHandleName(),
                              'order' => intval($component['order'] ?? 0),
                              'instance_id' => $component['instance_id'] ?? '',
                              'isHidden' => isset($component['isHidden']) ? (bool)$component['isHidden'] : false
                          ];
                          error_log("CCC DEBUG: Updated component {$component_id} with database data");
                      } else {
                          // Component not found in database - check if we have stored data
                          if (isset($component['name']) && isset($component['handle_name'])) {
                              error_log("CCC DEBUG: Component {$component_id} not found in database, but has stored data");
                              
                              // Check if the component is marked as deleted in post meta
                              if (isset($component['isDeleted']) && $component['isDeleted']) {
                                  error_log("CCC DEBUG: Component {$component_id} is marked as deleted in post meta");
                                  $updated_components[] = [
                                      'id' => $component_id,
                                      'name' => $component['name'],
                                      'handle_name' => $component['handle_name'],
                                      'order' => intval($component['order'] ?? 0),
                                      'instance_id' => $component['instance_id'] ?? '',
                                      'isHidden' => isset($component['isHidden']) ? (bool)$component['isHidden'] : false,
                                      'isDeleted' => true
                                  ];
                              } else {
                                  error_log("CCC DEBUG: Component {$component_id} has stored data but not marked as deleted - treating as renamed");
                                  // We have stored component data, use it (component might be renamed)
                                  $updated_components[] = $component;
                              }
                          } else {
                              error_log("CCC DEBUG: Component {$component_id} not found in database and no stored data - marking as deleted");
                              // No stored data, mark as deleted
                              $updated_components[] = [
                                  'id' => $component_id,
                                  'name' => $component['name'] ?? 'Deleted Component',
                                  'handle_name' => $component['handle_name'] ?? 'deleted_component',
                                  'order' => intval($component['order'] ?? 0),
                                  'instance_id' => $component['instance_id'] ?? '',
                                  'isHidden' => isset($component['isHidden']) ? (bool)$component['isHidden'] : false,
                                  'isDeleted' => true
                              ];
                          }
                      }
                  } elseif (is_numeric($component)) {
                      // Legacy format: just the ID, convert to new format
                      $component_id = $component;
                      $component_obj = \CCC\Models\Component::find($component_id);
                      if ($component_obj) {
                          $updated_components[] = [
                              'id' => $component_obj->getId(),
                              'name' => $component_obj->getName(),
                              'handle_name' => $component_obj->getHandleName(),
                              'order' => 0,
                              'instance_id' => '',
                              'isHidden' => false
                          ];
                          error_log("CCC DEBUG: Converted legacy component {$component_id} to new format");
                      } else {
                          // Component not found, mark as deleted
                          $updated_components[] = [
                              'id' => $component_id,
                              'name' => 'Deleted Component',
                              'handle_name' => 'deleted_component',
                              'order' => 0,
                              'instance_id' => '',
                              'isHidden' => false,
                              'isDeleted' => true
                          ];
                          error_log("CCC DEBUG: Component {$component_id} not found, marking as deleted");
                      }
                  } else {
                      // Unknown format, skip
                      error_log("CCC DEBUG: Unknown component format, skipping: " . print_r($component, true));
                  }
              }

              error_log("CCC DEBUG: Final updated components: " . print_r($updated_components, true));

              // Update the post meta with the preserved component data structure
              update_post_meta($post_id, '_ccc_components', $updated_components);
              
              // Also store the component details for easy access
              update_post_meta($post_id, '_ccc_component_details', $updated_components);

              error_log("CCC DEBUG: Successfully updated metabox data for post {$post_id}");

          } catch (\Exception $e) {
              error_log("Exception in refreshMetaboxDataForPost: " . $e->getMessage());
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
              } elseif ($sanitized_nf['type'] === 'number') {
                  $sanitized_config = [
                      'unique' => (bool)($config['unique'] ?? false),
                      'min_value' => isset($config['min_value']) && $config['min_value'] !== '' ? floatval($config['min_value']) : null,
                      'max_value' => isset($config['max_value']) && $config['max_value'] !== '' ? floatval($config['max_value']) : null,
                      'prepend' => sanitize_text_field($config['prepend'] ?? ''),
                      'append' => sanitize_text_field($config['append'] ?? '')
                  ];
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
          } elseif ($type === 'number') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              if (!is_array($field_config)) {
                  $field_config = [];
              }
              $config = [
                  'number_type' => sanitize_text_field($field_config['number_type'] ?? 'normal'),
                  'unique' => (bool)($field_config['unique'] ?? false),
                  'min_value' => isset($field_config['min_value']) && $field_config['min_value'] !== '' ? floatval($field_config['min_value']) : null,
                  'max_value' => isset($field_config['max_value']) && $field_config['max_value'] !== '' ? floatval($field_config['max_value']) : null,
                  'min_length' => isset($field_config['min_length']) && $field_config['min_length'] !== '' ? intval($field_config['min_length']) : null,
                  'max_length' => isset($field_config['max_length']) && $field_config['max_length'] !== '' ? intval($field_config['max_length']) : null,
                  'prepend' => sanitize_text_field($field_config['prepend'] ?? ''),
                  'append' => sanitize_text_field($field_config['append'] ?? '')
              ];
          } elseif ($type === 'range') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              if (!is_array($field_config)) {
                  $field_config = [];
              }
              $config = [
                  'min_value' => isset($field_config['min_value']) && $field_config['min_value'] !== '' && $field_config['min_value'] !== null ? floatval($field_config['min_value']) : null,
                  'max_value' => isset($field_config['max_value']) && $field_config['max_value'] !== '' && $field_config['max_value'] !== null ? floatval($field_config['max_value']) : null,
                  'prepend' => sanitize_text_field($field_config['prepend'] ?? ''),
                  'append' => sanitize_text_field($field_config['append'] ?? '')
              ];
          } elseif ($type === 'color') {
              $config = [];
          } elseif ($type === 'link') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              if (!is_array($field_config)) {
                  $field_config = [];
              }
              $config = [
                  'link_types' => isset($field_config['link_types']) && is_array($field_config['link_types']) ? $field_config['link_types'] : ['internal', 'external'],
                  'default_type' => sanitize_text_field($field_config['default_type'] ?? 'internal'),
                  'post_types' => isset($field_config['post_types']) && is_array($field_config['post_types']) ? $field_config['post_types'] : ['post', 'page'],
                  'show_target' => (bool)($field_config['show_target'] ?? true),
                  'show_title' => (bool)($field_config['show_title'] ?? true)
              ];
          } elseif ($type === 'file') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              error_log("CCC DEBUG: AjaxHandler file field_config raw: " . ($_POST['field_config'] ?? '{}'));
              error_log("CCC DEBUG: AjaxHandler file field_config decoded: " . json_encode($field_config));
              if (!is_array($field_config)) {
                  $field_config = [];
              }
              $config = [
                  'allowed_types' => isset($field_config['allowed_types']) && is_array($field_config['allowed_types']) ? array_map('sanitize_text_field', $field_config['allowed_types']) : ['image', 'video', 'document', 'audio', 'archive'],
                  'max_file_size' => isset($field_config['max_file_size']) && $field_config['max_file_size'] !== null && $field_config['max_file_size'] !== '' ? intval($field_config['max_file_size']) : null,
                  'return_type' => sanitize_text_field($field_config['return_type'] ?? 'url'),
                  'show_preview' => (bool)($field_config['show_preview'] ?? true),
                  'show_download' => (bool)($field_config['show_download'] ?? true),
                  'show_delete' => (bool)($field_config['show_delete'] ?? true)
              ];
              error_log("CCC DEBUG: AjaxHandler file config final: " . json_encode($config));
          } elseif ($type === 'taxonomy_term') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              error_log("CCC DEBUG: AjaxHandler taxonomy_term field_config raw: " . ($_POST['field_config'] ?? '{}'));
              error_log("CCC DEBUG: AjaxHandler taxonomy_term field_config decoded: " . json_encode($field_config));
              if (!is_array($field_config)) {
                  $field_config = [];
              }
              $config = [
                  'taxonomy' => sanitize_text_field($field_config['taxonomy'] ?? 'category'),
                  'multiple' => (bool)($field_config['multiple'] ?? false),
                  'allow_empty' => (bool)($field_config['allow_empty'] ?? true),
                  'placeholder' => sanitize_text_field($field_config['placeholder'] ?? 'Select terms...'),
                  'searchable' => (bool)($field_config['searchable'] ?? false),
                  'hierarchical' => (bool)($field_config['hierarchical'] ?? false),
                  'show_count' => (bool)($field_config['show_count'] ?? false),
                  'orderby' => sanitize_text_field($field_config['orderby'] ?? 'name'),
                  'order' => sanitize_text_field($field_config['order'] ?? 'ASC')
              ];
              error_log("CCC DEBUG: AjaxHandler taxonomy_term config final: " . json_encode($config));
          } elseif ($type === 'user') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              if (!is_array($field_config)) {
                  $field_config = [];
              }
              $config = [
                  'role_filter' => isset($field_config['role_filter']) && is_array($field_config['role_filter']) ? array_map('sanitize_text_field', $field_config['role_filter']) : [],
                  'multiple' => (bool)($field_config['multiple'] ?? false),
                  'return_type' => sanitize_text_field($field_config['return_type'] ?? 'id'),
                  'searchable' => (bool)($field_config['searchable'] ?? true),
                  'orderby' => sanitize_text_field($field_config['orderby'] ?? 'display_name'),
                  'order' => sanitize_text_field($field_config['order'] ?? 'ASC')
              ];
              error_log("CCC DEBUG: AjaxHandler user config final: " . json_encode($config));
          } else {
              // For all other field types that don't have specific config handling above,
              // still process conditional logic if present
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              if (is_array($field_config)) {
                  $config = $field_config;
              }
          }

          // Add conditional logic to all field types (except toggle which handles it separately)
          if ($type !== 'toggle') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              if (is_array($field_config)) {
                  $config['field_condition'] = sanitize_text_field($field_config['field_condition'] ?? 'always_show');
                  $config['conditional_logic'] = isset($field_config['conditional_logic']) && is_array($field_config['conditional_logic']) ? $field_config['conditional_logic'] : [];
                  $config['logic_operator'] = sanitize_text_field($field_config['logic_operator'] ?? 'AND');
              }
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

  /**
   * Create field callback for import functionality
   */
  public function createFieldCallback() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');
          
          error_log('CCC createFieldCallback: POST data received: ' . json_encode($_POST));

          $label = sanitize_text_field($_POST['label'] ?? '');
          $name = sanitize_text_field($_POST['name'] ?? '');
          $type = sanitize_text_field($_POST['type'] ?? '');
          $component_id = intval($_POST['component_id'] ?? 0);
          $required = isset($_POST['required']) ? (bool) $_POST['required'] : false;
          $placeholder = sanitize_text_field($_POST['placeholder'] ?? '');
          $children = isset($_POST['children']) ? json_decode(wp_unslash($_POST['children']), true) : null;

          if (empty($label) || empty($name) || empty($type) || empty($component_id)) {
              wp_send_json_error(['message' => 'Missing required fields.']);
              return;
          }

          // Calculate the next field order
          global $wpdb;
          $fields_table = $wpdb->prefix . 'cc_fields';
          $max_order = $wpdb->get_var($wpdb->prepare(
              "SELECT MAX(field_order) FROM $fields_table WHERE component_id = %d",
              $component_id
          ));
          $next_order = ($max_order !== null) ? intval($max_order) + 1 : 0;

          $config = [];
          if ($type === 'repeater' && $children && is_array($children)) {
              $config = [
                  'max_sets' => 0,
                  'children' => $children
              ];
          } elseif (in_array($type, ['checkbox', 'select', 'radio', 'button_group'])) {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              $config = [
                  'options' => $field_config['options'] ?? [],
                  'multiple' => (bool)($field_config['multiple'] ?? false)
              ];
          } elseif ($type === 'image') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              $config = [
                  'return_type' => sanitize_text_field($field_config['return_type'] ?? 'url')
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
                      'download' => true
                  ]
              ];
          } elseif ($type === 'wysiwyg') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              $config = [
                  'editor_settings' => is_array($field_config['editor_settings']) ? $field_config['editor_settings'] : [
                      'media_buttons' => true,
                      'teeny' => false,
                      'textarea_rows' => 10
                  ]
              ];
          } elseif ($type === 'number') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              $config = [
                  'number_type' => sanitize_text_field($field_config['number_type'] ?? 'normal'),
                  'unique' => (bool)($field_config['unique'] ?? false),
                  'min_value' => isset($field_config['min_value']) && $field_config['min_value'] !== '' ? floatval($field_config['min_value']) : null,
                  'max_value' => isset($field_config['max_value']) && $field_config['max_value'] !== '' ? floatval($field_config['max_value']) : null,
                  'min_length' => isset($field_config['min_length']) && $field_config['min_length'] !== '' ? intval($field_config['min_length']) : null,
                  'max_length' => isset($field_config['max_length']) && $field_config['max_length'] !== '' ? intval($field_config['max_length']) : null,
                  'prepend' => sanitize_text_field($field_config['prepend'] ?? ''),
                  'append' => sanitize_text_field($field_config['append'] ?? '')
              ];
          } elseif ($type === 'range') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              $config = [
                  'min_value' => isset($field_config['min_value']) && $field_config['min_value'] !== '' && $field_config['min_value'] !== null ? floatval($field_config['min_value']) : null,
                  'max_value' => isset($field_config['max_value']) && $field_config['max_value'] !== '' && $field_config['max_value'] !== null ? floatval($field_config['max_value']) : null,
                  'prepend' => sanitize_text_field($field_config['prepend'] ?? ''),
                  'append' => sanitize_text_field($field_config['append'] ?? '')
              ];
          } elseif ($type === 'toggle') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              $config = [
                  'default_value' => (bool)($field_config['default_value'] ?? false)
              ];
          } elseif ($type === 'user') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              $config = [
                  'role_filter' => isset($field_config['role_filter']) && is_array($field_config['role_filter']) ? $field_config['role_filter'] : [],
                  'multiple' => (bool)($field_config['multiple'] ?? false),
                  'return_type' => sanitize_text_field($field_config['return_type'] ?? 'id'),
                  'searchable' => (bool)($field_config['searchable'] ?? true),
                  'orderby' => sanitize_text_field($field_config['orderby'] ?? 'display_name'),
                  'order' => sanitize_text_field($field_config['order'] ?? 'ASC')
              ];
          } elseif ($type === 'relationship') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              $config = [
                  'filter_post_types' => isset($field_config['filter_post_types']) && is_array($field_config['filter_post_types']) ? $field_config['filter_post_types'] : [],
                  'filter_post_status' => isset($field_config['filter_post_status']) && is_array($field_config['filter_post_status']) ? $field_config['filter_post_status'] : [],
                  'filter_taxonomy' => sanitize_text_field($field_config['filter_taxonomy'] ?? ''),
                  'filters' => isset($field_config['filters']) && is_array($field_config['filters']) ? $field_config['filters'] : ['search', 'post_type'],
                  'max_posts' => intval($field_config['max_posts'] ?? 0),
                  'return_format' => sanitize_text_field($field_config['return_format'] ?? 'object')
              ];
          } elseif ($type === 'link') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              $config = [
                  'link_types' => isset($field_config['link_types']) && is_array($field_config['link_types']) ? $field_config['link_types'] : ['internal', 'external'],
                  'default_type' => sanitize_text_field($field_config['default_type'] ?? 'internal'),
                  'post_types' => isset($field_config['post_types']) && is_array($field_config['post_types']) ? $field_config['post_types'] : ['post', 'page'],
                  'show_target' => (bool)($field_config['show_target'] ?? true),
                  'show_title' => (bool)($field_config['show_title'] ?? true)
              ];
          } elseif ($type === 'file') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              $config = [
                  'allowed_types' => isset($field_config['allowed_types']) && is_array($field_config['allowed_types']) ? $field_config['allowed_types'] : ['image', 'video', 'document', 'audio', 'archive'],
                  'max_file_size' => intval($field_config['max_file_size'] ?? 25),
                  'return_type' => sanitize_text_field($field_config['return_type'] ?? 'url'),
                  'show_preview' => (bool)($field_config['show_preview'] ?? true),
                  'show_download' => (bool)($field_config['show_download'] ?? true),
                  'show_delete' => (bool)($field_config['show_delete'] ?? true)
              ];
          }

          error_log('CCC createFieldCallback: Field config being saved: ' . json_encode($config));
          
          $field = new \CCC\Models\Field([
              'component_id' => $component_id,
              'label' => $label,
              'name' => $name,
              'type' => $type,
              'config' => json_encode($config),
              'field_order' => $next_order,
              'required' => $required,
              'placeholder' => $placeholder
          ]);

          if ($field->save()) {
              // If repeater, save nested fields as real DB rows
              if ($type === 'repeater' && $children && is_array($children)) {
                  \CCC\Core\Database::migrateNestedFieldsToRowsRecursive($component_id, $field->getId(), $children);
              }
              
              wp_send_json_success(['message' => 'Field created successfully.']);
          } else {
              global $wpdb;
              error_log('CCC AjaxHandler: Failed to create field. DB error: ' . $wpdb->last_error);
              wp_send_json_error(['message' => 'Failed to create field.']);
          }

      } catch (\Exception $e) {
          error_log("Exception in createFieldCallback: " . $e->getMessage());
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
          } elseif ($type === 'relationship') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              $config = [
                  'filter_post_types' => is_array($field_config['filter_post_types']) ? $field_config['filter_post_types'] : [],
                  'filter_post_status' => is_array($field_config['filter_post_status']) ? $field_config['filter_post_status'] : [],
                  'filter_taxonomy' => sanitize_text_field($field_config['filter_taxonomy'] ?? ''),
                  'filters' => is_array($field_config['filters']) ? $field_config['filters'] : ['search', 'post_type'],
                  'max_posts' => intval($field_config['max_posts'] ?? 0),
                  'return_format' => sanitize_text_field($field_config['return_format'] ?? 'object')
              ];
          } elseif ($type === 'number') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              if (!is_array($field_config)) {
                  $field_config = [];
              }
              $config = [
                  'number_type' => sanitize_text_field($field_config['number_type'] ?? 'normal'),
                  'unique' => (bool)($field_config['unique'] ?? false),
                  'min_value' => isset($field_config['min_value']) && $field_config['min_value'] !== '' ? floatval($field_config['min_value']) : null,
                  'max_value' => isset($field_config['max_value']) && $field_config['max_value'] !== '' ? floatval($field_config['max_value']) : null,
                  'min_length' => isset($field_config['min_length']) && $field_config['min_length'] !== '' ? intval($field_config['min_length']) : null,
                  'max_length' => isset($field_config['max_length']) && $field_config['max_length'] !== '' ? intval($field_config['max_length']) : null,
                  'prepend' => sanitize_text_field($field_config['prepend'] ?? ''),
                  'append' => sanitize_text_field($field_config['append'] ?? '')
              ];
          } elseif ($type === 'range') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              if (!is_array($field_config)) {
                  $field_config = [];
              }
              $config = [
                  'min_value' => isset($field_config['min_value']) && $field_config['min_value'] !== '' && $field_config['min_value'] !== null ? floatval($field_config['min_value']) : null,
                  'max_value' => isset($field_config['max_value']) && $field_config['max_value'] !== '' && $field_config['max_value'] !== null ? floatval($field_config['max_value']) : null,
                  'prepend' => sanitize_text_field($field_config['prepend'] ?? ''),
                  'append' => sanitize_text_field($field_config['append'] ?? '')
              ];
          } elseif ($type === 'toggle') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              if (!is_array($field_config)) {
                  $field_config = [];
              }
              $config = [
                  'default_value' => (bool)($field_config['default_value'] ?? false),
                  'ui_style' => sanitize_text_field($field_config['ui_style'] ?? 'switch'),
                  'conditional_logic' => isset($field_config['conditional_logic']) && is_array($field_config['conditional_logic']) ? $field_config['conditional_logic'] : []
              ];
          } elseif ($type === 'color') {
              $config = [];
          } elseif ($type === 'link') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              if (!is_array($field_config)) {
                  $field_config = [];
              }
              $config = [
                  'link_types' => isset($field_config['link_types']) && is_array($field_config['link_types']) ? $field_config['link_types'] : ['internal', 'external'],
                  'default_type' => sanitize_text_field($field_config['default_type'] ?? 'internal'),
                  'post_types' => isset($field_config['post_types']) && is_array($field_config['post_types']) ? $field_config['post_types'] : ['post', 'page'],
                  'show_target' => (bool)($field_config['show_target'] ?? true),
                  'show_title' => (bool)($field_config['show_title'] ?? true)
              ];
          } elseif ($type === 'file') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              error_log("CCC DEBUG: AjaxHandler updateFieldCallback file field_config raw: " . ($_POST['field_config'] ?? '{}'));
              error_log("CCC DEBUG: AjaxHandler updateFieldCallback file field_config decoded: " . json_encode($field_config));
              if (!is_array($field_config)) {
                  $field_config = [];
              }
              $config = [
                  'allowed_types' => isset($field_config['allowed_types']) && is_array($field_config['allowed_types']) ? array_map('sanitize_text_field', $field_config['allowed_types']) : ['image', 'video', 'document', 'audio', 'archive'],
                  'max_file_size' => isset($field_config['max_file_size']) && $field_config['max_file_size'] !== null && $field_config['max_file_size'] !== '' ? intval($field_config['max_file_size']) : null,
                  'return_type' => sanitize_text_field($field_config['return_type'] ?? 'url'),
                  'show_preview' => (bool)($field_config['show_preview'] ?? true),
                  'show_download' => (bool)($field_config['show_download'] ?? true),
                  'show_delete' => (bool)($field_config['show_delete'] ?? true)
              ];
              error_log("CCC DEBUG: AjaxHandler updateFieldCallback file config final: " . json_encode($config));
          } elseif ($type === 'taxonomy_term') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              error_log("CCC DEBUG: AjaxHandler taxonomy_term field_config raw: " . ($_POST['field_config'] ?? '{}'));
              error_log("CCC DEBUG: AjaxHandler taxonomy_term field_config decoded: " . json_encode($field_config));
              if (!is_array($field_config)) {
                  $field_config = [];
              }
              $config = [
                  'taxonomy' => sanitize_text_field($field_config['taxonomy'] ?? 'category'),
                  'multiple' => (bool)($field_config['multiple'] ?? false),
                  'allow_empty' => (bool)($field_config['allow_empty'] ?? true),
                  'placeholder' => sanitize_text_field($field_config['placeholder'] ?? 'Select terms...'),
                  'searchable' => (bool)($field_config['searchable'] ?? true),
                  'hierarchical' => (bool)($field_config['hierarchical'] ?? false),
                  'show_count' => (bool)($field_config['show_count'] ?? false),
                  'orderby' => sanitize_text_field($field_config['orderby'] ?? 'name'),
                  'order' => sanitize_text_field($field_config['order'] ?? 'ASC')
              ];
              error_log("CCC DEBUG: AjaxHandler taxonomy_term config final: " . json_encode($config));
          } elseif ($type === 'user') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              if (!is_array($field_config)) {
                  $field_config = [];
              }
              $config = [
                  'role_filter' => isset($field_config['role_filter']) && is_array($field_config['role_filter']) ? array_map('sanitize_text_field', $field_config['role_filter']) : [],
                  'multiple' => (bool)($field_config['multiple'] ?? false),
                  'return_type' => sanitize_text_field($field_config['return_type'] ?? 'id'),
                  'searchable' => (bool)($field_config['searchable'] ?? true),
                  'orderby' => sanitize_text_field($field_config['orderby'] ?? 'display_name'),
                  'order' => sanitize_text_field($field_config['order'] ?? 'ASC')
              ];
              error_log("CCC AjaxHandler updateFieldCallback user config final: " . json_encode($config));
          } else {
              // For all other field types that don't have specific config handling above,
              // preserve existing config and add any new conditional logic
              $existing_config = json_decode($field->getConfig(), true) ?: [];
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              if (is_array($field_config)) {
                  $config = array_merge($existing_config, $field_config);
              } else {
                  $config = $existing_config;
              }
          }

          // Add conditional logic to all field types (except toggle which handles it separately)
          if ($type !== 'toggle') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              error_log("CCC AjaxHandler updateFieldCallback - Raw field_config POST: " . ($_POST['field_config'] ?? 'NOT SET'));
              error_log("CCC AjaxHandler updateFieldCallback - Decoded field_config: " . json_encode($field_config));
              
              if (is_array($field_config)) {
                  $config['field_condition'] = sanitize_text_field($field_config['field_condition'] ?? 'always_show');
                  $config['conditional_logic'] = isset($field_config['conditional_logic']) && is_array($field_config['conditional_logic']) ? $field_config['conditional_logic'] : [];
                  $config['logic_operator'] = sanitize_text_field($field_config['logic_operator'] ?? 'AND');
                  
                  error_log("CCC AjaxHandler updateFieldCallback - Final conditional logic config: field_condition=" . $config['field_condition'] . ", logic_operator=" . $config['logic_operator'] . ", conditional_logic=" . json_encode($config['conditional_logic']));
              } else {
                  error_log("CCC AjaxHandler updateFieldCallback - field_config is not an array: " . gettype($field_config));
              }
          }

          $data = [
              'label' => $label,
              'name' => $name,
              'type' => $type,
              'required' => $required,
              'placeholder' => $placeholder,
              'config' => json_encode($config)
          ];
          
          error_log("CCC AjaxHandler updateFieldCallback - Final data config: " . json_encode($data['config']));

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
      try {
          check_ajax_referer('ccc_nonce', 'nonce');

          $post_type = sanitize_text_field($_POST['post_type'] ?? 'page');
          
          // Add error logging
          error_log("CCC AjaxHandler: getPosts called for post_type: " . $post_type);
          
          $posts = get_posts([
              'post_type' => $post_type,
              'post_status' => 'publish',
              'numberposts' => -1,
              'orderby' => 'title',
              'order' => 'ASC'
          ]);

          if (is_wp_error($posts)) {
              error_log("CCC AjaxHandler: get_posts() returned WP_Error: " . $posts->get_error_message());
              wp_send_json_error(['message' => 'Failed to retrieve posts: ' . $posts->get_error_message()]);
              return;
          }

          if (!is_array($posts)) {
              error_log("CCC AjaxHandler: get_posts() returned non-array: " . gettype($posts));
              wp_send_json_error(['message' => 'Invalid posts data received']);
              return;
          }

          $post_list = array_map(function ($post) {
              try {
                  $components = get_post_meta($post->ID, '_ccc_components', true);
                  return [
                      'id' => $post->ID,
                      'title' => $post->post_title,
                      'has_components' => !empty($components)
                  ];
              } catch (\Exception $e) {
                  error_log("CCC AjaxHandler: Error processing post {$post->ID}: " . $e->getMessage());
                  return [
                      'id' => $post->ID,
                      'title' => $post->post_title,
                      'has_components' => false
                  ];
              }
          }, $posts);

          error_log("CCC AjaxHandler: getPosts successful, returning " . count($post_list) . " posts");
          wp_send_json_success(['posts' => $post_list]);

      } catch (\Exception $e) {
          error_log("CCC AjaxHandler: Exception in getPosts: " . $e->getMessage());
          error_log("CCC AjaxHandler: Exception trace: " . $e->getTraceAsString());
          wp_send_json_error(['message' => 'An error occurred while retrieving posts: ' . $e->getMessage()]);
      }
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

          // Handle the new format from React metabox
          $success = true;
          if (is_array($field_values)) {
              foreach ($field_values as $instance_id => $fields_data) {
                  foreach ($fields_data as $field_id => $value) {
                      // Find the field by ID
                      $field_obj = Field::find($field_id);
                      if ($field_obj) {
                          if (!FieldValue::saveValue($field_obj->getId(), $post_id, $instance_id, $value)) {
                              $success = false;
                              error_log("CCC FieldValue: Failed to save value for field ID {$field_id} on post {$post_id} instance {$instance_id}");
                          }
                      } else {
                          error_log("CCC FieldValue: Field ID {$field_id} not found.");
                          $success = false;
                      }
                  }
              }
          } else {
              // Fallback to old format
              $success = FieldValue::saveMultiple($post_id, $field_values);
          }

          if ($success) {
              error_log("CCC DEBUG saveFieldValues - SUCCESS: Saved field values for post $post_id");
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

          // Debug: Check if the component exists first
          $component = Component::find($component_id);
          if (!$component) {
              error_log("CCC AjaxHandler: getComponentFields - Component $component_id not found");
              wp_send_json_error(['message' => 'Component not found.']);
              return;
          }
          
          error_log("CCC AjaxHandler: getComponentFields - Component $component_id found: " . $component->getName());

          // Debug: Check direct field query
          global $wpdb;
          $fields_table = $wpdb->prefix . 'cc_fields';
          $direct_fields = $wpdb->get_results(
              $wpdb->prepare("SELECT * FROM $fields_table WHERE component_id = %d", $component_id),
              ARRAY_A
          );
          error_log("CCC AjaxHandler: getComponentFields - Direct query found " . count($direct_fields) . " fields");
          foreach ($direct_fields as $direct_field) {
              error_log("CCC AjaxHandler: getComponentFields - Direct field: " . json_encode($direct_field));
          }

          // Use the recursive tree loader instead of flat loader
          error_log("CCC AjaxHandler: getComponentFields - About to call Field::findFieldsTree($component_id)");
          $fields = Field::findFieldsTree($component_id);
          error_log("CCC AjaxHandler: getComponentFields - Found " . count($fields) . " fields for component $component_id");
          
          // Debug: Check what each field contains
          foreach ($fields as $index => $field) {
              error_log("CCC AjaxHandler: getComponentFields - Field $index: " . json_encode([
                  'id' => $field->getId(),
                  'label' => $field->getLabel(),
                  'name' => $field->getName(),
                  'type' => $field->getType(),
                  'component_id' => $field->getComponentId()
              ]));
          }
          
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
                  // Handle user fields (can be single or multiple)
                  if ($field->getType() === 'user') {
                      error_log("CCC DEBUG: Processing user field - value: " . json_encode($value) . ", type: " . gettype($value));
                      if (isset($decoded_config['multiple']) && $decoded_config['multiple']) {
                          // Multiple user selection - handle as array
                          if (is_string($value)) {
                              error_log("CCC DEBUG: User field value is string: " . $value);
                              if (strpos($value, '[') === 0) {
                                  // JSON array format
                                  error_log("CCC DEBUG: Detected JSON array format, attempting to decode");
                                  $decoded = json_decode($value, true);
                                  error_log("CCC DEBUG: JSON decode result: " . json_encode($decoded));
                                  if (is_array($decoded)) {
                                      // Clean up the array to ensure all values are integers
                                      $value = array_map(function($item) {
                                          $clean = is_string($item) ? trim($item, '[]') : $item;
                                          return intval($clean);
                                      }, $decoded);
                                      $value = array_filter($value, function($item) {
                                          return $item > 0;
                                      });
                                      error_log("CCC DEBUG: Cleaned user array: " . json_encode($value));
                                  } else {
                                      error_log("CCC DEBUG: JSON decode failed, setting empty array");
                                      $value = [];
                                  }
                              } else {
                                  // Comma-separated format
                                  error_log("CCC DEBUG: Detected comma-separated format");
                                  $value = $value ? explode(',', $value) : [];
                                  $value = array_map('intval', $value);
                                  $value = array_filter($value, function($item) {
                                      return $item > 0;
                                  });
                                  error_log("CCC DEBUG: Processed comma-separated array: " . json_encode($value));
                              }
                          } elseif (is_array($value)) {
                              // If it's already an array, clean it up
                              error_log("CCC DEBUG: Value is already array, cleaning up");
                              $value = array_map('intval', $value);
                              $value = array_filter($value, function($item) {
                                  return $item > 0;
                              });
                              error_log("CCC DEBUG: Cleaned existing array: " . json_encode($value));
                          }
                      } else {
                          // Single user selection - ensure it's a string
                          if (is_array($value)) {
                              $value = count($value) > 0 ? strval($value[0]) : '';
                          } else {
                              $value = strval($value);
                          }
                          error_log("CCC DEBUG: Single user selection, final value: " . $value);
                      }
                      error_log("CCC DEBUG: Final user field value: " . json_encode($value));
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
      try {
          global $wpdb;
          $field_values_table = $wpdb->prefix . 'cc_field_values';
          $fields_table = $wpdb->prefix . 'cc_fields';
          
          // Check if tables exist before querying
          $field_values_exists = $wpdb->get_var("SHOW TABLES LIKE '$field_values_table'") == $field_values_table;
          $fields_exists = $wpdb->get_var("SHOW TABLES LIKE '$fields_table'") == $fields_table;
          
          if (!$field_values_exists || !$fields_exists) {
              error_log("CCC AjaxHandler: Required tables don't exist for getFieldValuesForPost");
              return [];
          }
          
          $values = $wpdb->get_results(
              $wpdb->prepare(
                  "SELECT field_id, instance_id, value FROM $field_values_table WHERE post_id = %d",
                  $post_id
              ),
              ARRAY_A
          );
          
          if (is_wp_error($values)) {
              error_log("CCC AjaxHandler: Database error in getFieldValuesForPost: " . $values->get_error_message());
              return [];
          }
          
          $field_values = [];
          foreach ($values as $value) {
              try {
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
              } catch (\Exception $e) {
                  error_log("CCC AjaxHandler: Error processing field value: " . $e->getMessage());
                  // Continue with other values
              }
          }
          
          return $field_values;
          
      } catch (\Exception $e) {
          error_log("CCC AjaxHandler: Exception in getFieldValuesForPost: " . $e->getMessage());
          return [];
      }
  }

  public function getPostsWithComponents() {
      error_log("CCC DEBUG: getPostsWithComponents method called");
      error_log("CCC DEBUG: POST data: " . print_r($_POST, true));
      error_log("CCC DEBUG: Request method: " . $_SERVER['REQUEST_METHOD']);
      error_log("CCC DEBUG: Request URI: " . $_SERVER['REQUEST_URI']);
      error_log("CCC DEBUG: User agent: " . $_SERVER['HTTP_USER_AGENT']);
      
      try {
          // Check if services are available
          if (!$this->component_service || !$this->field_service) {
              error_log("CCC AjaxHandler: Services not available");
              wp_send_json_error(['message' => 'Plugin services not initialized properly.']);
              return;
          }
          
          error_log("CCC DEBUG: About to check nonce");
          error_log("CCC DEBUG: Nonce from POST: " . ($_POST['nonce'] ?? 'NOT_SET'));
          error_log("CCC DEBUG: Expected nonce name: ccc_nonce");
          check_ajax_referer('ccc_nonce', 'nonce');
          error_log("CCC DEBUG: Nonce check passed");

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
              
              // Process components to check if they still exist and mark deleted ones
              $processed_components = [];
              error_log("CCC DEBUG: Processing " . count($components) . " components for post {$post_id}");
              error_log("CCC DEBUG: Raw components data: " . print_r($components, true));
              
              foreach ($components as $component) {
                  error_log("CCC DEBUG: Processing component entry: " . print_r($component, true));
                  
                  $component_id = intval($component['id'] ?? 0);
                  if ($component_id) {
                      error_log("CCC DEBUG: Looking up component {$component_id} in database");
                      $component_obj = \CCC\Models\Component::find($component_id);
                      
                      if ($component_obj) {
                          error_log("CCC DEBUG: Component {$component_id} found in database, using database data");
                          error_log("CCC DEBUG: Database component data - Name: {$component_obj->getName()}, Handle: {$component_obj->getHandleName()}");
                          // Component still exists - use database data but preserve stored metadata
                          $processed_components[] = [
                              'id' => $component_obj->getId(),
                              'name' => $component_obj->getName(),
                              'handle_name' => $component_obj->getHandleName(),
                              'order' => intval($component['order'] ?? 0),
                              'instance_id' => $component['instance_id'] ?? '',
                              'isHidden' => isset($component['isHidden']) ? (bool)$component['isHidden'] : false,
                              'isDeleted' => false
                          ];
                      } else {
                          error_log("CCC DEBUG: Component {$component_id} NOT found in database, checking stored data");
                          // Component not found in database - check if we have stored data
                          if (isset($component['name']) && isset($component['handle_name'])) {
                              error_log("CCC DEBUG: Using stored component data for component {$component_id}");
                              error_log("CCC DEBUG: Stored data - Name: {$component['name']}, Handle: {$component['handle_name']}");
                              
                              // Check if the component is marked as deleted in post meta
                              if (isset($component['isDeleted']) && $component['isDeleted']) {
                                  error_log("CCC DEBUG: Component {$component_id} is marked as deleted in post meta");
                                  $processed_components[] = [
                                      'id' => $component_id,
                                      'name' => $component['name'],
                                      'handle_name' => $component['handle_name'],
                                      'order' => intval($component['order'] ?? 0),
                                      'instance_id' => $component['instance_id'] ?? '',
                                      'isHidden' => isset($component['isHidden']) ? (bool)$component['isHidden'] : false,
                                      'isDeleted' => true
                                  ];
                              } else {
                                  error_log("CCC DEBUG: Component {$component_id} has stored data but not marked as deleted - treating as renamed");
                                  // We have stored component data, use it (component might be renamed)
                                  $processed_components[] = [
                                      'id' => $component_id,
                                      'name' => $component['name'],
                                      'handle_name' => $component['handle_name'],
                                      'order' => intval($component['order'] ?? 0),
                                      'instance_id' => $component['instance_id'] ?? '',
                                      'isHidden' => isset($component['isHidden']) ? (bool)$component['isHidden'] : false,
                                      'isDeleted' => false // Not deleted, just not found in DB lookup
                                  ];
                              }
                          } else {
                              error_log("CCC DEBUG: No stored data for component {$component_id}, marking as deleted");
                              error_log("CCC DEBUG: Component entry structure: " . print_r($component, true));
                              // No stored data, mark as deleted
                              $processed_components[] = [
                                  'id' => $component_id,
                                  'name' => $component['name'] ?? 'Deleted Component',
                                  'handle_name' => $component['handle_name'] ?? 'deleted_component',
                                  'order' => intval($component['order'] ?? 0),
                                  'instance_id' => $component['instance_id'] ?? '',
                                  'isHidden' => isset($component['isHidden']) ? (bool)$component['isHidden'] : false,
                                  'isDeleted' => true
                              ];
                          }
                      }
                  } else {
                      error_log("CCC DEBUG: Invalid component entry, skipping: " . print_r($component, true));
                  }
              }
              
              error_log("CCC DEBUG: Final processed components for post {$post_id}: " . print_r($processed_components, true));
              
              // Get field values for this post with proper instance_id handling
              $field_values = $this->getFieldValuesForPost($post_id);
              
              // Sort components by 'order' property before returning
              usort($processed_components, function($a, $b) {
                  return ($a['order'] ?? 0) - ($b['order'] ?? 0);
              });
              
              wp_send_json_success([
                  'components' => $processed_components,
                  'field_values' => $field_values
              ]);
              return;
          }

          // Return list of posts with components (original functionality)
          $post_type = sanitize_text_field($_POST['post_type'] ?? 'page');
          
          // Add error logging
          error_log("CCC AjaxHandler: getPostsWithComponents called for post_type: " . $post_type);
          
          // Validate post type
          $available_post_types = get_post_types(['public' => true], 'names');
          if (!in_array($post_type, $available_post_types)) {
              error_log("CCC AjaxHandler: Invalid post type requested: " . $post_type);
              error_log("CCC AjaxHandler: Available post types: " . implode(', ', $available_post_types));
              wp_send_json_error(['message' => 'Invalid post type: ' . $post_type]);
              return;
          }
          
          // Check if database tables exist
          global $wpdb;
          $components_table = $wpdb->prefix . 'cc_components';
          $fields_table = $wpdb->prefix . 'cc_fields';
          $field_values_table = $wpdb->prefix . 'cc_field_values';
          
          $tables_exist = $wpdb->get_var("SHOW TABLES LIKE '{$components_table}'") == $components_table &&
                         $wpdb->get_var("SHOW TABLES LIKE '{$fields_table}'") == $fields_table &&
                         $wpdb->get_var("SHOW TABLES LIKE '{$field_values_table}'") == $field_values_table;
          
          if (!$tables_exist) {
              error_log("CCC AjaxHandler: Required database tables don't exist");
              wp_send_json_error(['message' => 'Database tables not found. Please reactivate the plugin.']);
              return;
          }
          
          // Get posts with better error handling
          $posts = get_posts([
              'post_type' => $post_type,
              'post_status' => 'publish',
              'numberposts' => -1,
              'orderby' => 'title',
              'order' => 'ASC'
          ]);

          if (is_wp_error($posts)) {
              error_log("CCC AjaxHandler: get_posts() returned WP_Error: " . $posts->get_error_message());
              wp_send_json_error(['message' => 'Failed to retrieve posts: ' . $posts->get_error_message()]);
              return;
          }

          if (!is_array($posts)) {
              error_log("CCC AjaxHandler: get_posts() returned non-array: " . gettype($posts));
              wp_send_json_error(['message' => 'Failed to retrieve posts: Invalid data type returned']);
              return;
          }
          
          // Handle case where no posts exist for this post type
          if (empty($posts)) {
              error_log("CCC AjaxHandler: No posts found for post type: " . $post_type);
              wp_send_json_success(['posts' => []]);
              return;
          }

          $post_list = [];
          foreach ($posts as $post) {
              try {
                  if (!$post || !is_object($post) || !isset($post->ID)) {
                      error_log("CCC AjaxHandler: Invalid post object encountered: " . print_r($post, true));
                      continue;
                  }
                  
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
                  error_log("  - Will be selected in main interface: " . ($result['assigned_via_main_interface'] ? 'YES' : 'NO'));
                  error_log("  - Component count: " . (is_array($components) ? count($components) : 0));
                  
                  $post_list[] = $result;
              } catch (\Exception $e) {
                  error_log("CCC AjaxHandler: Error processing post {$post->ID}: " . $e->getMessage());
                  // Continue processing other posts instead of failing completely
                  $post_list[] = [
                      'id' => $post->ID,
                      'title' => $post->post_title ?? 'Unknown Title',
                      'status' => $post->post_status ?? 'unknown',
                      'has_components' => false,
                      'assigned_components' => [],
                      'assigned_via_main_interface' => false
                  ];
              }
          }

          error_log("CCC AjaxHandler: getPostsWithComponents successful, returning " . count($post_list) . " posts");
          error_log("CCC AjaxHandler: Response structure: " . json_encode(['posts' => $post_list]));
          wp_send_json_success([
              'posts' => $post_list,
              'timestamp' => current_time('mysql'),
              'debug_info' => 'Response from getPostsWithComponents method'
          ]);

      } catch (\Exception $e) {
          error_log("CCC AjaxHandler: Exception in getPostsWithComponents: " . $e->getMessage());
          error_log("CCC AjaxHandler: Exception trace: " . $e->getTraceAsString());
          wp_send_json_error(['message' => 'An error occurred while retrieving posts: ' . $e->getMessage()]);
      } catch (\Error $e) {
          error_log("CCC AjaxHandler: Fatal Error in getPostsWithComponents: " . $e->getMessage());
          error_log("CCC AjaxHandler: Error trace: " . $e->getTraceAsString());
          wp_send_json_error(['message' => 'A fatal error occurred while retrieving posts. Please check the server logs.']);
      }
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

      // Check assignment type
      $assignment_type = sanitize_text_field($_POST['assignment_type'] ?? 'individual_posts');
      error_log("CCC DEBUG saveComponentAssignments - Assignment type: $assignment_type");

      if ($assignment_type === 'post_types') {
          // Handle post type assignments
          $assignments = json_decode(wp_unslash($_POST['assignments'] ?? '{}'), true);
          
          if (!is_array($assignments)) {
              wp_send_json_error(['message' => 'Invalid assignments data']);
              return;
          }

          // Initialize PostTypeTemplateService
          $template_service = new \CCC\Services\PostTypeTemplateService();
          
          // Get all available components for template generation
          $all_components = [];
          $components_query = new \WP_Query([
              'post_type' => 'ccc_component',
              'post_status' => 'publish',
              'posts_per_page' => -1,
              'meta_query' => [
                  [
                      'key' => '_ccc_component_data',
                      'compare' => 'EXISTS'
                  ]
              ]
          ]);
          
          if ($components_query->have_posts()) {
              while ($components_query->have_posts()) {
                  $components_query->the_post();
                  $component_data = get_post_meta(get_the_ID(), '_ccc_component_data', true);
                  if (!empty($component_data)) {
                      $all_components[] = [
                          'id' => get_the_ID(),
                          'name' => get_the_title(),
                          'handle_name' => $component_data['handle_name'] ?? ''
                      ];
                  }
              }
              wp_reset_postdata();
          }

          foreach ($assignments as $assignment_key => $component_data_array) {
              if (strpos($assignment_key, 'post_type:') === 0) {
                  $post_type = substr($assignment_key, 10); // Remove 'post_type:' prefix
                  error_log("CCC DEBUG saveComponentAssignments - Processing post type: $post_type");
                  
                  // Get all posts of this post type
                  $posts = get_posts([
                      'post_type' => $post_type,
                      'post_status' => 'publish',
                      'numberposts' => -1,
                      'fields' => 'ids'
                  ]);
                  
                  if (is_array($posts)) {
                      foreach ($posts as $post_id) {
                          if (is_null($component_data_array)) {
                              // Post type is being assigned - mark all posts of this type
                              $previous_components = get_post_meta($post_id, '_ccc_previous_components', true);
                              if (!empty($previous_components) && is_array($previous_components)) {
                                  update_post_meta($post_id, '_ccc_components', $previous_components);
                                  error_log("CCC DEBUG saveComponentAssignments - Post $post_id (type: $post_type) assigned, restoring previous components: " . json_encode($previous_components));
                              } else {
                                  update_post_meta($post_id, '_ccc_components', []);
                                  error_log("CCC DEBUG saveComponentAssignments - Post $post_id (type: $post_type) assigned, no previous components to restore");
                              }
                              update_post_meta($post_id, '_ccc_had_components', '1');
                              update_post_meta($post_id, '_ccc_assigned_via_main_interface', '1');
                              update_post_meta($post_id, '_ccc_assigned_via_post_type', $post_type);
                              error_log("CCC DEBUG saveComponentAssignments - Post $post_id (type: $post_type): assigned via post type, showing metabox");
                          } else {
                              // Post type is being unassigned - clear all posts of this type
                              $current_components = get_post_meta($post_id, '_ccc_components', true);
                              if (!empty($current_components) && is_array($current_components)) {
                                  update_post_meta($post_id, '_ccc_previous_components', $current_components);
                                  error_log("CCC DEBUG saveComponentAssignments - Post $post_id (type: $post_type) unassigned, storing previous components: " . json_encode($current_components));
                              }
                              update_post_meta($post_id, '_ccc_components', []);
                              delete_post_meta($post_id, '_ccc_had_components');
                              delete_post_meta($post_id, '_ccc_assigned_via_main_interface');
                              delete_post_meta($post_id, '_ccc_assigned_via_post_type');
                              error_log("CCC DEBUG saveComponentAssignments - Post $post_id (type: $post_type): unassigned via post type, hiding metabox");
                          }
                      }
                      
                      // Create or remove post type template based on assignment status
                      try {
                          if (is_null($component_data_array)) {
                              // Post type is being assigned - create template
                              $template_service->createPostTypeTemplate($post_type, $all_components);
                              error_log("CCC DEBUG saveComponentAssignments - Created template for post type: $post_type");
                          } else {
                              // Post type is being unassigned - remove template
                              $template_service->removePostTypeTemplate($post_type);
                              error_log("CCC DEBUG saveComponentAssignments - Removed template for post type: $post_type");
                          }
                      } catch (\Exception $e) {
                          error_log("CCC DEBUG saveComponentAssignments - Error handling template for post type $post_type: " . $e->getMessage());
                      }
                  }
              }
          }
      } else {
          // Handle individual post assignments (existing logic)
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
          } elseif ($type === 'relationship') {
              $field_config = json_decode(wp_unslash($_POST['field_config'] ?? '{}'), true);
              $config = [
                  'filter_post_types' => is_array($field_config['filter_post_types']) ? $field_config['filter_post_types'] : [],
                  'filter_post_status' => is_array($field_config['filter_post_status']) ? $field_config['filter_post_status'] : [],
                  'filter_taxonomy' => sanitize_text_field($field_config['filter_taxonomy'] ?? ''),
                  'filters' => is_array($field_config['filters']) ? $field_config['filters'] : ['search', 'post_type'],
                  'max_posts' => intval($field_config['max_posts'] ?? 0),
                  'return_format' => sanitize_text_field($field_config['return_format'] ?? 'object')
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

  public function searchPosts() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');

          $search = sanitize_text_field($_POST['search'] ?? '');
          $post_type = sanitize_text_field($_POST['post_type'] ?? '');
          $taxonomy = sanitize_text_field($_POST['taxonomy'] ?? '');
          $filter_post_types = sanitize_text_field($_POST['filter_post_types'] ?? '');
          $filter_post_status = sanitize_text_field($_POST['filter_post_status'] ?? '');
          $filter_taxonomy = sanitize_text_field($_POST['filter_taxonomy'] ?? '');

          // Get all available post types
          $post_types = get_post_types(['public' => true], 'names');
          error_log("CCC DEBUG: Available post types: " . print_r($post_types, true));

          $args = [
              'post_type' => array_values($post_types), // Use all available post types
              'post_status' => ['publish', 'draft', 'private', 'pending'], // Include all relevant statuses
              'posts_per_page' => -1, // Get all posts
              'orderby' => 'title',
              'order' => 'ASC',
              'suppress_filters' => false // Allow modification by filters
          ];

          // Apply search
          if (!empty($search)) {
              $args['s'] = $search;
          }

          // Apply post type filter
          if (!empty($post_type)) {
              $args['post_type'] = $post_type;
          } elseif (!empty($filter_post_types)) {
              $allowed_types = array_filter(explode(',', $filter_post_types));
              if (!empty($allowed_types)) {
                  $args['post_type'] = $allowed_types;
              }
          } else {
              // If no post type filter is specified, show all post types
              $args['post_type'] = array_values($post_types);
          }

          // Apply post status filter
          if (!empty($filter_post_status)) {
              $allowed_statuses = array_filter(explode(',', $filter_post_status));
              if (!empty($allowed_statuses)) {
                  $args['post_status'] = $allowed_statuses;
              }
          }

          error_log("CCC DEBUG: Final post types in query: " . print_r($args['post_type'], true));
          error_log("CCC DEBUG: Final post statuses in query: " . print_r($args['post_status'], true));

          // Apply taxonomy filter
          if (!empty($taxonomy) || !empty($filter_taxonomy)) {
              $tax_query = [];
              
              if (!empty($taxonomy)) {
                  // Get all terms for this taxonomy
                  $terms = get_terms([
                      'taxonomy' => $taxonomy,
                      'fields' => 'slugs',
                      'hide_empty' => false
                  ]);
                  
                  if (!empty($terms) && !is_wp_error($terms)) {
                      $tax_query[] = [
                          'taxonomy' => $taxonomy,
                          'field' => 'slug',
                          'terms' => $terms,
                          'operator' => 'IN'
                      ];
                  }
              }
              
              if (!empty($filter_taxonomy)) {
                  // Get all terms for this taxonomy
                  $terms = get_terms([
                      'taxonomy' => $filter_taxonomy,
                      'fields' => 'slugs',
                      'hide_empty' => false
                  ]);
                  
                  if (!empty($terms) && !is_wp_error($terms)) {
                      $tax_query[] = [
                          'taxonomy' => $filter_taxonomy,
                          'field' => 'slug',
                          'terms' => $terms,
                          'operator' => 'IN'
                      ];
                  }
              }
              
              if (!empty($tax_query)) {
                  $args['tax_query'] = $tax_query;
              }
          }

          $query = new \WP_Query($args);
          $posts = [];
          
          error_log("CCC DEBUG: searchPosts query args: " . print_r($args, true));
          error_log("CCC DEBUG: searchPosts found posts: " . $query->found_posts);

          if ($query->have_posts()) {
              while ($query->have_posts()) {
                  $query->the_post();
                  $post_id = get_the_ID();
                  $post_data = [
                      'id' => $post_id,
                      'title' => get_the_title(),
                      'type' => get_post_type(),
                      'status' => get_post_status(),
                      'url' => get_permalink(),
                      'excerpt' => wp_trim_words(get_the_excerpt(), 20, '...'),
                      'date' => get_the_date(),
                      'author' => get_the_author()
                  ];
                  $posts[] = $post_data;
                  error_log("CCC DEBUG: Added post: " . print_r($post_data, true));
              }
          }
          
          error_log("CCC DEBUG: searchPosts returning posts: " . count($posts));
          error_log("CCC DEBUG: Final posts array: " . print_r($posts, true));

          wp_reset_postdata();
          wp_send_json_success(['data' => $posts]);

      } catch (\Exception $e) {
          error_log("Exception in searchPosts: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }

  public function getPostsByIds() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');

          $post_ids = sanitize_text_field($_POST['post_ids'] ?? '');
          error_log("CCC getPostsByIds: Requested post_ids: " . $post_ids);
          
          if (empty($post_ids)) {
              wp_send_json_success(['data' => []]);
              return;
          }

          $ids = array_map('intval', explode(',', $post_ids));
          error_log("CCC getPostsByIds: Parsed IDs: " . print_r($ids, true));
          $posts = [];

          foreach ($ids as $post_id) {
              $post = get_post($post_id);
              error_log("CCC getPostsByIds: Looking for post ID $post_id, found: " . ($post ? 'YES' : 'NO'));
              if ($post) {
                  $post_data = [
                      'id' => $post->ID,
                      'title' => $post->post_title,
                      'type' => $post->post_type,
                      'status' => $post->post_status,
                      'url' => get_permalink($post->ID),
                      'excerpt' => wp_trim_words(get_the_excerpt($post->ID), 20, '...'),
                      'date' => get_the_date('', $post->ID),
                      'author' => get_the_author_meta('display_name', $post->post_author)
                  ];
                  error_log("CCC getPostsByIds: Post data: " . print_r($post_data, true));
                  $posts[] = $post_data;
              }
          }

          error_log("CCC getPostsByIds: Returning " . count($posts) . " posts");
          wp_send_json_success(['data' => $posts]);

      } catch (\Exception $e) {
          error_log("Exception in getPostsByIds: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }



  public function getAvailablePostTypes() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');
          
          $post_types = get_post_types(['public' => true], 'objects');
          $available_post_types = [];
          
          error_log("CCC DEBUG: getAvailablePostTypes - Found " . count($post_types) . " post types");
          
          foreach ($post_types as $post_type => $post_type_object) {
              // Get the label with fallbacks
              $label = '';
              if (isset($post_type_object->labels->singular_name) && !empty($post_type_object->labels->singular_name)) {
                  $label = $post_type_object->labels->singular_name;
              } elseif (isset($post_type_object->labels->name) && !empty($post_type_object->labels->name)) {
                  $label = $post_type_object->labels->name;
              } else {
                  // Fallback to the post type name itself
                  $label = ucfirst(str_replace(['_', '-'], ' ', $post_type));
              }
              
              $available_post_types[] = [
                  'value' => $post_type,
                  'label' => $label
              ];
              error_log("CCC DEBUG: getAvailablePostTypes - Added post type: {$post_type} => {$label}");
          }
          
          error_log("CCC DEBUG: getAvailablePostTypes - Returning " . count($available_post_types) . " post types");
          error_log("CCC DEBUG: getAvailablePostTypes - Final data: " . json_encode($available_post_types));
          wp_send_json_success($available_post_types);
      } catch (\Exception $e) {
          error_log("Exception in getAvailablePostTypes: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }

  public function getAvailableTaxonomies() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');
          
          error_log("CCC DEBUG: getAvailableTaxonomies called");
          
          // Get all public taxonomies (including custom taxonomies)
          $taxonomies = get_taxonomies(['public' => true], 'objects');
          
          error_log("CCC DEBUG: Found " . count($taxonomies) . " public taxonomies");
          
          $available_taxonomies = [];
          
          foreach ($taxonomies as $taxonomy => $taxonomy_object) {
              // Include all public taxonomies, regardless of whether they have terms
              // This ensures custom taxonomies show up even if they don't have terms yet
              $label = '';
              if (isset($taxonomy_object->labels->singular_name) && !empty($taxonomy_object->labels->singular_name)) {
                  $label = $taxonomy_object->labels->singular_name;
              } elseif (isset($taxonomy_object->labels->name) && !empty($taxonomy_object->labels->name)) {
                  $label = $taxonomy_object->labels->name;
              } else {
                  // Fallback to the taxonomy name itself
                  $label = ucfirst(str_replace(['_', '-'], ' ', $taxonomy));
              }
              
              $available_taxonomies[] = [
                  'value' => $taxonomy,
                  'label' => $label
              ];
              
              error_log("CCC DEBUG: Added taxonomy: {$taxonomy} => {$label}");
          }
          
          error_log("CCC DEBUG: Returning " . count($available_taxonomies) . " taxonomies");
          wp_send_json_success($available_taxonomies);
      } catch (\Exception $e) {
          error_log("Exception in getAvailableTaxonomies: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }

  public function getTaxonomiesForPostType() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');
          
          $post_type = sanitize_text_field($_POST['post_type'] ?? '');
          
          error_log("CCC DEBUG: getTaxonomiesForPostType called with post_type: " . $post_type);
          
          if (empty($post_type) || $post_type === 'all') {
              // Return all public taxonomies if no specific post type or 'all' is selected
              $taxonomies = get_taxonomies(['public' => true], 'objects');
              error_log("CCC DEBUG: Returning all public taxonomies");
          } else {
              // Get taxonomies specific to the selected post type
              $taxonomies = get_object_taxonomies($post_type, 'objects');
              error_log("CCC DEBUG: Returning taxonomies for post type: " . $post_type);
          }
          
          $available_taxonomies = [];
          
          foreach ($taxonomies as $taxonomy => $taxonomy_object) {
              // Include all taxonomies, regardless of whether they have terms
              $label = '';
              if (isset($taxonomy_object->labels->singular_name) && !empty($taxonomy_object->labels->singular_name)) {
                  $label = $taxonomy_object->labels->singular_name;
              } elseif (isset($taxonomy_object->labels->name) && !empty($taxonomy_object->labels->name)) {
                  $label = $taxonomy_object->labels->name;
              } else {
                  // Fallback to the taxonomy name itself
                  $label = ucfirst(str_replace(['_', '-'], ' ', $taxonomy));
              }
              
              $available_taxonomies[] = [
                  'value' => $taxonomy,
                  'label' => $label
              ];
              
              error_log("CCC DEBUG: Added taxonomy: {$taxonomy} => {$label}");
          }
          
          error_log("CCC DEBUG: Returning " . count($available_taxonomies) . " taxonomies");
          wp_send_json_success($available_taxonomies);
      } catch (\Exception $e) {
          error_log("Exception in getTaxonomiesForPostType: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }

  public function checkNumberUniqueness()
  {
      // Check nonce for security
      if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ccc_nonce')) {
          wp_die('Security check failed');
      }

      $number = floatval($_POST['number'] ?? 0);
      $field_id = intval($_POST['field_id'] ?? 0);
      $post_id = intval($_POST['post_id'] ?? 0);
      $instance_id = sanitize_text_field($_POST['instance_id'] ?? '');

      error_log("CCC DEBUG: checkNumberUniqueness called with number: $number, field_id: $field_id, post_id: $post_id, instance_id: $instance_id");

      if ($number <= 0 || $field_id <= 0) {
          error_log("CCC DEBUG: checkNumberUniqueness - Invalid parameters");
          wp_send_json_error('Invalid parameters');
      }

      try {
          $field_service = new FieldService();
          $field = $field_service->getField($field_id);

          if (!$field || $field->getType() !== 'number') {
              error_log("CCC DEBUG: checkNumberUniqueness - Invalid field or not a number field");
              wp_send_json_error('Invalid field');
          }

          error_log("CCC DEBUG: checkNumberUniqueness - Field found, checking uniqueness");
          
          // Create a NumberField instance to check uniqueness
          $number_field = new \CCC\Fields\NumberField(
              $field->getLabel(),
              $field->getName(),
              $field->getComponentId(),
              $field->getRequired(),
              '',
              $field->getConfig()
          );
          $number_field->setId($field->getId());
          
          $is_unique = $number_field->isUnique($number, $post_id, $field_id, $instance_id);
          error_log("CCC DEBUG: checkNumberUniqueness - Uniqueness result: " . ($is_unique ? 'true' : 'false'));
          
          wp_send_json_success(['is_unique' => $is_unique]);
      } catch (Exception $e) {
          error_log("CCC DEBUG: checkNumberUniqueness - Exception: " . $e->getMessage());
          wp_send_json_error('Error checking uniqueness: ' . $e->getMessage());
      }
  }

  public function getTaxonomyTerms()
  {
      // Check nonce for security
      if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ccc_nonce')) {
          wp_die('Security check failed');
      }

      $taxonomy = sanitize_text_field($_POST['taxonomy'] ?? 'category');
      $orderby = sanitize_text_field($_POST['orderby'] ?? 'name');
      $order = sanitize_text_field($_POST['order'] ?? 'ASC');
      $hierarchical = (bool)($_POST['hierarchical'] ?? false);

      if (!taxonomy_exists($taxonomy)) {
          wp_send_json_error('Invalid taxonomy');
      }

      try {
          $args = [
              'taxonomy' => $taxonomy,
              'hide_empty' => false,
              'orderby' => $orderby,
              'order' => $order,
              'hierarchical' => $hierarchical
          ];

          $terms = get_terms($args);

          if (is_wp_error($terms)) {
              wp_send_json_error('Error getting terms: ' . $terms->get_error_message());
          }

          $formatted_terms = [];
          foreach ($terms as $term) {
              $formatted_terms[] = [
                  'term_id' => $term->term_id,
                  'name' => $term->name,
                  'slug' => $term->slug,
                  'taxonomy' => $term->taxonomy,
                  'description' => $term->description,
                  'count' => $term->count,
                  'parent' => $term->parent,
                  'term_taxonomy_id' => $term->term_taxonomy_id
              ];
          }

          wp_send_json_success(['terms' => $formatted_terms]);
      } catch (Exception $e) {
          wp_send_json_error('Error getting taxonomy terms: ' . $e->getMessage());
      }
  }

  public function getUsers()
  {
      error_log("CCC DEBUG: getUsers called with POST data: " . json_encode($_POST));
      
      // Check nonce for security
      if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ccc_nonce')) {
          error_log("CCC DEBUG: Nonce verification failed. Received nonce: " . ($_POST['nonce'] ?? 'none'));
          wp_die('Security check failed');
      }
      
      error_log("CCC DEBUG: Nonce verification passed");

      $role_filter = json_decode(wp_unslash($_POST['role_filter'] ?? '[]'), true);
      $search = sanitize_text_field($_POST['search'] ?? '');
      $orderby = sanitize_text_field($_POST['orderby'] ?? 'display_name');
      $order = sanitize_text_field($_POST['order'] ?? 'ASC');
      
      error_log("CCC DEBUG: Role filter received: " . json_encode($role_filter));
      error_log("CCC DEBUG: Role filter type: " . gettype($role_filter));

      try {
          $args = [
              'orderby' => $orderby,
              'order' => $order,
              'number' => -1
          ];

          // Add role filter if specified
          if (!empty($role_filter) && is_array($role_filter)) {
              $args['role__in'] = array_map('sanitize_text_field', $role_filter);
              error_log("CCC DEBUG: Added role filter to args: " . json_encode($args['role__in']));
          } else {
              error_log("CCC DEBUG: No role filter applied - role_filter is empty or not array");
          }
          
          // If role filter is empty or invalid, get all users
          if (empty($args['role__in'])) {
              error_log("CCC DEBUG: Getting all users (no role filter)");
          }

          // Add search if specified
          if (!empty($search)) {
              $args['search'] = '*' . $search . '*';
              $args['search_columns'] = ['user_login', 'user_email', 'display_name', 'user_nicename'];
          }

          error_log("CCC DEBUG: get_users args: " . json_encode($args));
          $users = get_users($args);

          if (is_wp_error($users)) {
              error_log("CCC DEBUG: get_users error: " . $users->get_error_message());
              wp_send_json_error('Error getting users: ' . $users->get_error_message());
          }

          error_log("CCC DEBUG: Found " . count($users) . " users");
          
          $formatted_users = [];
          foreach ($users as $user) {
              $formatted_users[] = [
                  'ID' => $user->ID,
                  'user_login' => $user->user_login,
                  'user_email' => $user->user_email,
                  'display_name' => $user->display_name,
                  'user_nicename' => $user->user_nicename,
                  'roles' => $user->roles ?? []
              ];
          }

          error_log("CCC DEBUG: Sending formatted users: " . json_encode($formatted_users));
          error_log("CCC DEBUG: Response will be: " . json_encode(['data' => $formatted_users]));
          wp_send_json_success(['data' => $formatted_users]);
      } catch (Exception $e) {
          wp_send_json_error('Error getting users: ' . $e->getMessage());
      }
  }

  /**
   * Save OpenAI API key to WordPress options
   */
  public function saveApiKey() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');

          $api_key = sanitize_text_field($_POST['api_key'] ?? '');

          if (empty($api_key)) {
              wp_send_json_error(['message' => 'API key is required']);
              return;
          }

          // Validate API key format (should start with sk-)
          if (!preg_match('/^sk-[a-zA-Z0-9]{32,}$/', $api_key)) {
              wp_send_json_error(['message' => 'Invalid API key format. Should start with "sk-" and be at least 32 characters long.']);
              return;
          }

          // Save to WordPress options
          update_option('ccc_openai_api_key', $api_key);

          wp_send_json_success(['message' => 'API key saved successfully']);

      } catch (\Exception $e) {
          error_log("Exception in saveApiKey: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }

  /**
   * Get OpenAI API key from WordPress options
   */
  public function getApiKey() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');

          $api_key = get_option('ccc_openai_api_key', '');
          
          // Return masked version for security
          $masked_key = '';
          if (!empty($api_key)) {
              $masked_key = substr($api_key, 0, 8) . '...' . substr($api_key, -4);
          }

          wp_send_json_success([
              'has_key' => !empty($api_key),
              'masked_key' => $masked_key
          ]);

      } catch (\Exception $e) {
          error_log("Exception in getApiKey: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }

  /**
   * Get full OpenAI API key for use in generation (secure endpoint)
   */
  public function getApiKeyForUse() {
      try {
          check_ajax_referer('ccc_nonce', 'nonce');

          $api_key = get_option('ccc_openai_api_key', '');
          
          if (empty($api_key)) {
              wp_send_json_error(['message' => 'API key not configured']);
              return;
          }

          wp_send_json_success(['api_key' => $api_key]);

      } catch (\Exception $e) {
          error_log("Exception in getApiKeyForUse: " . $e->getMessage());
          wp_send_json_error(['message' => $e->getMessage()]);
      }
  }
  
  /**
   * Generate a proxy API key for a user
   */
  public function generateProxyKey() {
      check_ajax_referer('ccc_nonce', 'nonce');
      
      if (!current_user_can('manage_options')) {
          wp_die('Unauthorized');
      }

      // Generate a unique proxy key
      $proxy_key = 'ccc_proxy_' . bin2hex(random_bytes(16));
      
      // Store the proxy key mapping (in production, use a proper database table)
      $proxy_keys = get_option('ccc_proxy_keys', []);
      $proxy_keys[$proxy_key] = [
          'created' => current_time('mysql'),
          'user_id' => get_current_user_id(),
          'usage_count' => 0,
          'last_used' => null,
          'is_active' => true
      ];
      
      update_option('ccc_proxy_keys', $proxy_keys);
      
      wp_send_json_success([
          'proxy_key' => $proxy_key,
          'message' => 'Proxy key generated successfully'
      ]);
  }

  /**
   * Validate a proxy API key
   */
  public function validateProxyKey() {
      check_ajax_referer('ccc_nonce', 'nonce');
      
      $proxy_key = sanitize_text_field($_POST['proxy_key'] ?? '');
      
      if (empty($proxy_key)) {
          wp_send_json_error('Proxy key is required');
      }

      $proxy_keys = get_option('ccc_proxy_keys', []);
      
      if (!isset($proxy_keys[$proxy_key])) {
          wp_send_json_error('Invalid proxy key');
      }

      $key_data = $proxy_keys[$proxy_key];
      
      if (!$key_data['is_active']) {
          wp_send_json_error('Proxy key is revoked');
      }

      wp_send_json_success([
          'valid' => true,
          'usage_count' => $key_data['usage_count'],
          'created' => $key_data['created']
      ]);
  }

  /**
   * Handle OpenAI API requests through proxy
   */
  public function proxyOpenAIRequest() {
      check_ajax_referer('ccc_nonce', 'nonce');
      
      $proxy_key = sanitize_text_field($_POST['proxy_key'] ?? '');
      $prompt = sanitize_textarea_field($_POST['prompt'] ?? '');
      $model = sanitize_text_field($_POST['model'] ?? 'gpt-4o-mini');
      
      if (empty($proxy_key) || empty($prompt)) {
          wp_send_json_error('Proxy key and prompt are required');
      }

      // Validate proxy key
      $proxy_keys = get_option('ccc_proxy_keys', []);
      
      if (!isset($proxy_keys[$proxy_key]) || !$proxy_keys[$proxy_key]['is_active']) {
          wp_send_json_error('Invalid or revoked proxy key');
      }

      // Get the real API key (stored securely on server)
      $real_api_key = get_option('ccc_openai_api_key');
      
      if (empty($real_api_key)) {
          wp_send_json_error('OpenAI API key not configured on server');
      }

      // Rate limiting (optional)
      $key_data = &$proxy_keys[$proxy_key];
      $key_data['usage_count']++;
      $key_data['last_used'] = current_time('mysql');
      update_option('ccc_proxy_keys', $proxy_keys);

      // Make the actual OpenAI API call
      $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
          'headers' => [
              'Authorization' => 'Bearer ' . $real_api_key,
              'Content-Type' => 'application/json',
          ],
          'body' => json_encode([
              'model' => $model,
              'messages' => [
                  [
                      'role' => 'system',
                      'content' => 'You are a WordPress component generator. Generate valid JSON responses only.'
                  ],
                  [
                      'role' => 'user',
                      'content' => $prompt
                  ]
              ],
              'temperature' => 0.7,
              'max_tokens' => 2000
          ]),
          'timeout' => 30
      ]);

      if (is_wp_error($response)) {
          wp_send_json_error('API request failed: ' . $response->get_error_message());
      }

      $body = wp_remote_retrieve_body($response);
      $data = json_decode($body, true);

      if (empty($data) || !isset($data['choices'][0]['message']['content'])) {
          wp_send_json_error('Invalid response from OpenAI API');
      }

      wp_send_json_success([
          'response' => $data['choices'][0]['message']['content'],
          'usage' => $data['usage'] ?? null
      ]);
  }

  /**
   * Revoke a proxy API key
   */
  public function revokeProxyKey() {
      check_ajax_referer('ccc_nonce', 'nonce');
      
      if (!current_user_can('manage_options')) {
          wp_die('Unauthorized');
      }

      $proxy_key = sanitize_text_field($_POST['proxy_key'] ?? '');
      
      if (empty($proxy_key)) {
          wp_send_json_error('Proxy key is required');
      }

      $proxy_keys = get_option('ccc_proxy_keys', []);
      
      if (isset($proxy_keys[$proxy_key])) {
          $proxy_keys[$proxy_key]['is_active'] = false;
          update_option('ccc_proxy_keys', $proxy_keys);
          wp_send_json_success('Proxy key revoked successfully');
      }

      wp_send_json_error('Proxy key not found');
  }

  /**
   * Get posts for relationship field with advanced filtering
   */
  public function getRelationshipPosts() {
      error_log("CCC AjaxHandler: getRelationshipPosts called");
      
      // Check nonce for security
      if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ccc_nonce')) {
          error_log("CCC DEBUG: Nonce verification failed. Received nonce: " . ($_POST['nonce'] ?? 'none'));
          wp_die('Security check failed');
      }
      
      error_log("CCC DEBUG: Nonce verification passed");
      
      try {

          // Get filter parameters
          $post_types_raw = $_POST['post_types'] ?? '';
          $post_status_raw = $_POST['post_status'] ?? '';
          $search = sanitize_text_field($_POST['search'] ?? '');
          $post_type_filter = sanitize_text_field($_POST['post_type_filter'] ?? '');
          $status_filter = sanitize_text_field($_POST['status_filter'] ?? '');
          $taxonomy_filters = sanitize_text_field($_POST['taxonomy_filters'] ?? '');
          $exclude = sanitize_text_field($_POST['exclude'] ?? '');
          $per_page = intval($_POST['per_page'] ?? 50);

          // Parse post_types - handle both JSON array and comma-separated string
          $post_types = '';
          if (!empty($post_types_raw)) {
              error_log("CCC AjaxHandler: Raw post_types_raw: '" . $post_types_raw . "'");
              
              if (strpos($post_types_raw, '[') === 0) {
                  // JSON array format - try to fix escaped quotes
                  error_log("CCC AjaxHandler: Detected JSON array format");
                  
                  // Fix escaped quotes: [\"page\"] -> ["page"]
                  $fixed_json = str_replace('\"', '"', $post_types_raw);
                  error_log("CCC AjaxHandler: Fixed JSON: '" . $fixed_json . "'");
                  
                  $post_types_array = json_decode($fixed_json, true);
                  error_log("CCC AjaxHandler: JSON decode result: " . print_r($post_types_array, true));
                  
                  if (is_array($post_types_array)) {
                      $post_types = implode(',', $post_types_array);
                      error_log("CCC AjaxHandler: Final post_types: '" . $post_types . "'");
                  } else {
                      error_log("CCC AjaxHandler: JSON decode failed, trying alternative approach");
                      // Alternative: extract post types using regex
                      if (preg_match('/\["([^"]+)"\]/', $post_types_raw, $matches)) {
                          $post_types = $matches[1];
                          error_log("CCC AjaxHandler: Extracted via regex: '" . $post_types . "'");
                      }
                  }
              } else {
                  // Comma-separated string format
                  error_log("CCC AjaxHandler: Detected comma-separated format");
                  $post_types = $post_types_raw;
              }
          }

          // Parse post_status - handle both JSON array and comma-separated string
          $post_status = '';
          if (!empty($post_status_raw)) {
              if (strpos($post_status_raw, '[') === 0) {
                  // JSON array format
                  $post_status_array = json_decode($post_status_raw, true);
                  if (is_array($post_status_array)) {
                      $post_status = implode(',', $post_status_array);
                  }
              } else {
                  // Comma-separated string format
                  $post_status = $post_status_raw;
              }
          }

          error_log("CCC AjaxHandler: getRelationshipPosts called with filters");
          error_log("CCC AjaxHandler: POST data: " . print_r($_POST, true));
          error_log("CCC AjaxHandler: All POST keys: " . implode(', ', array_keys($_POST)));
          error_log("CCC AjaxHandler: post_types_raw: " . $post_types_raw);
          error_log("CCC AjaxHandler: post_types_parsed: " . $post_types);
          error_log("CCC AjaxHandler: post_status: " . $post_status);
          error_log("CCC AjaxHandler: search: " . $search);

          // Build query args
          $args = [
              'post_type' => $post_types ? explode(',', $post_types) : 'any',
              'post_status' => $post_status ? explode(',', $post_status) : ['publish', 'private', 'draft'],
              'posts_per_page' => $per_page,
              'orderby' => 'title',
              'order' => 'ASC',
              'meta_query' => []
          ];

          error_log("CCC AjaxHandler: Initial args post_type: " . print_r($args['post_type'], true));

          // Add search
          if (!empty($search)) {
              $args['s'] = $search;
          }

          // Add post type filter
          if (!empty($post_type_filter)) {
              $args['post_type'] = $post_type_filter;
              error_log("CCC AjaxHandler: Override with post_type_filter: " . $post_type_filter);
          }

          error_log("CCC AjaxHandler: Final args post_type: " . print_r($args['post_type'], true));

          // Add status filter
          if (!empty($status_filter)) {
              $args['post_status'] = $status_filter;
          }

          // Add taxonomy filters
          if (!empty($taxonomy_filters)) {
              $tax_filters = json_decode($taxonomy_filters, true);
              error_log("CCC AjaxHandler: Parsed taxonomy_filters: " . print_r($tax_filters, true));
              
              if (is_array($tax_filters)) {
                  $tax_query = ['relation' => 'AND'];
                  foreach ($tax_filters as $taxonomy => $terms) {
                      if (is_array($terms) && !empty($terms)) {
                          // Filter by specific terms
                          $tax_query[] = [
                              'taxonomy' => $taxonomy,
                              'field' => 'term_id',
                              'terms' => $terms,
                              'operator' => 'IN'
                          ];
                          error_log("CCC AjaxHandler: Added taxonomy filter for {$taxonomy} with " . count($terms) . " specific terms");
                      } else {
                          // Filter by taxonomy only (show posts that have any terms in this taxonomy)
                          $all_terms = get_terms([
                              'taxonomy' => $taxonomy,
                              'hide_empty' => false,
                              'fields' => 'ids'
                          ]);
                          
                          if (!is_wp_error($all_terms) && !empty($all_terms)) {
                              $tax_query[] = [
                                  'taxonomy' => $taxonomy,
                                  'field' => 'term_id',
                                  'terms' => $all_terms,
                                  'operator' => 'IN'
                              ];
                              error_log("CCC AjaxHandler: Added taxonomy filter for {$taxonomy} with " . count($all_terms) . " all terms");
                          }
                      }
                  }
                  
                  if (count($tax_query) > 1) {
                      $args['tax_query'] = $tax_query;
                      error_log("CCC AjaxHandler: Applied tax_query: " . print_r($tax_query, true));
                  }
              }
          }

          // Exclude posts
          if (!empty($exclude)) {
              $exclude_ids = array_map('intval', explode(',', $exclude));
              $args['post__not_in'] = $exclude_ids;
          }

          // Get posts
          $query = new \WP_Query($args);
          $posts = $query->posts;

          if (is_wp_error($posts)) {
              error_log("CCC AjaxHandler: WP_Query returned error: " . $posts->get_error_message());
              wp_send_json_error(['message' => 'Failed to retrieve posts: ' . $posts->get_error_message()]);
              return;
          }

          $post_list = array_map(function ($post) {
              try {
                  return [
                      'ID' => $post->ID,
                      'post_title' => $post->post_title,
                      'post_name' => $post->post_name,
                      'post_type' => $post->post_type,
                      'post_status' => $post->post_status,
                      'post_date' => $post->post_date,
                      'post_modified' => $post->post_modified,
                      'post_excerpt' => $post->post_excerpt,
                      'featured_image' => get_the_post_thumbnail_url($post->ID, 'medium'),
                      'permalink' => get_permalink($post->ID),
                      'post_type_label' => get_post_type_object($post->post_type)->label ?? $post->post_type
                  ];
              } catch (\Exception $e) {
                  error_log("CCC AjaxHandler: Error processing post {$post->ID}: " . $e->getMessage());
                  return [
                      'ID' => $post->ID,
                      'post_title' => $post->post_title,
                      'post_name' => $post->post_name,
                      'post_type' => $post->post_type,
                      'post_status' => $post->post_status,
                      'post_date' => $post->post_date,
                      'post_modified' => $post->post_modified,
                      'post_excerpt' => '',
                      'featured_image' => '',
                      'permalink' => '',
                      'post_type_label' => $post->post_type
                  ];
              }
          }, $posts);

          error_log("CCC AjaxHandler: getRelationshipPosts successful, returning " . count($post_list) . " posts");
          wp_send_json_success($post_list);

      } catch (\Exception $e) {
          error_log("CCC AjaxHandler: Exception in getRelationshipPosts: " . $e->getMessage());
          error_log("CCC AjaxHandler: Exception trace: " . $e->getTraceAsString());
          wp_send_json_error(['message' => 'An error occurred while retrieving posts: ' . $e->getMessage()]);
      }
  }

  /**
   * Get gallery media/images for the gallery field
   */
  public function getGalleryMedia() {
      error_log("CCC AjaxHandler: getGalleryMedia called");
      
      // Check nonce for security
      if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ccc_nonce')) {
          error_log("CCC DEBUG: Nonce verification failed. Received nonce: " . ($_POST['nonce'] ?? 'none'));
          wp_die('Security check failed');
      }
      
      error_log("CCC DEBUG: Nonce verification passed");
      
      try {
          // Get filter parameters
          $search = sanitize_text_field($_POST['search'] ?? '');
          $mime_type = sanitize_text_field($_POST['mime_type'] ?? '');
          $per_page = intval($_POST['per_page'] ?? 50);
          $allowed_types_raw = $_POST['allowed_types'] ?? '';
          
          // Parse allowed types
          $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
          if (!empty($allowed_types_raw)) {
              if (is_string($allowed_types_raw)) {
                  $decoded_types = json_decode($allowed_types_raw, true);
                  if (is_array($decoded_types)) {
                      $allowed_types = $decoded_types;
                  }
              }
          }
          
          error_log("CCC AjaxHandler: getGalleryMedia called with filters");
          error_log("CCC AjaxHandler: search: " . $search);
          error_log("CCC AjaxHandler: mime_type: " . $mime_type);
          error_log("CCC AjaxHandler: allowed_types: " . print_r($allowed_types, true));
          
          // Build query args
          $args = [
              'post_type' => 'attachment',
              'post_status' => 'inherit',
              'posts_per_page' => $per_page,
              'orderby' => 'date',
              'order' => 'DESC',
              'post_mime_type' => $allowed_types
          ];
          
          // Add search
          if (!empty($search)) {
              $args['s'] = $search;
          }
          
          // Add specific mime type filter
          if (!empty($mime_type)) {
              $args['post_mime_type'] = $mime_type;
          }
          
          error_log("CCC AjaxHandler: Final query args: " . print_r($args, true));
          
          // Execute query
          $query = new \WP_Query($args);
          $attachments = $query->posts;
          
          error_log("CCC AjaxHandler: Found " . count($attachments) . " attachments");
          
          // Format attachments for frontend
          $media_data = [];
          foreach ($attachments as $attachment) {
              $file_path = get_attached_file($attachment->ID);
              $file_size = $file_path ? filesize($file_path) : 0;
              
              $media_data[] = [
                  'id' => $attachment->ID,
                  'title' => $attachment->post_title,
                  'filename' => basename($file_path),
                  'url' => wp_get_attachment_url($attachment->ID),
                  'thumbnail' => wp_get_attachment_image_url($attachment->ID, 'thumbnail'),
                  'medium' => wp_get_attachment_image_url($attachment->ID, 'medium'),
                  'large' => wp_get_attachment_image_url($attachment->ID, 'large'),
                  'alt' => get_post_meta($attachment->ID, '_wp_attachment_image_alt', true),
                  'caption' => $attachment->post_excerpt,
                  'description' => $attachment->post_content,
                  'mime_type' => get_post_mime_type($attachment->ID),
                  'filesize' => $file_size,
                  'filesizeHumanReadable' => $file_size ? size_format($file_size) : 'Unknown',
                  'date' => $attachment->post_date,
                  'modified' => $attachment->post_modified
              ];
          }
          
          error_log("CCC AjaxHandler: getGalleryMedia successful, returning " . count($media_data) . " media items");
          
          wp_send_json_success($media_data);
          
      } catch (\Exception $e) {
          error_log("CCC AjaxHandler: Exception in getGalleryMedia: " . $e->getMessage());
          error_log("CCC AjaxHandler: Exception trace: " . $e->getTraceAsString());
          wp_send_json_error(['message' => 'An error occurred while retrieving media: ' . $e->getMessage()]);
      }
  }

}
