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
        $sanitized_fields = [];
        foreach ($nested_fields as $nf) {
            $sanitized_nf = [
                'label' => sanitize_text_field($nf['label'] ?? ''),
                'name' => sanitize_title($nf['name'] ?? ''),
                'type' => sanitize_text_field($nf['type'] ?? ''),
            ];

            if ($sanitized_nf['type'] === 'repeater' && isset($nf['config'])) {
                $config = $nf['config'];
                $sanitized_config = [
                    'max_sets' => intval($config['max_sets'] ?? 0),
                    'nested_fields' => $this->sanitizeNestedFieldDefinitions($config['nested_fields'] ?? [])
                ];
                $sanitized_nf['config'] = $sanitized_config;
            } elseif ($sanitized_nf['type'] === 'image' && isset($nf['config']['return_type'])) {
                $sanitized_nf['config'] = ['return_type' => sanitize_text_field($nf['config']['return_type'])];
            }
            
            $sanitized_fields[] = $sanitized_nf;
        }
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

            // Validate name uniqueness within the component
            global $wpdb;
            $table = $wpdb->prefix . 'cc_fields';
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table WHERE name = %s AND component_id = %d",
                    $name,
                    $component_id
                )
            );
            if ($exists) {
                wp_send_json_error(['message' => 'Field name already exists within this component.']);
                return;
            }

            $config = [];
            
            if ($type === 'repeater') {
                $max_sets = intval($_POST['max_sets'] ?? 0);
                $nested_field_definitions = json_decode(wp_unslash($_POST['nested_field_definitions'] ?? '[]'), true);
                
                error_log("CCC AjaxHandler: Adding repeater field with " . count($nested_field_definitions) . " nested fields");
                
                $sanitized_nested_fields = $this->sanitizeNestedFieldDefinitions($nested_field_definitions);

                $config = [
                    'max_sets' => $max_sets,
                    'nested_fields' => $sanitized_nested_fields
                ];
                
                error_log("CCC AjaxHandler: Repeater config: " . json_encode($config));
            } elseif ($type === 'image') {
                $return_type = sanitize_text_field($_POST['return_type'] ?? 'url');
                $config = [
                    'return_type' => $return_type
                ];
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

            if (empty($field_id) || empty($label) || empty($name)) {
                wp_send_json_error(['message' => 'Missing required fields.']);
                return;
            }

            $field = Field::find($field_id);
            if (!$field) {
                wp_send_json_error(['message' => 'Field not found.']);
                return;
            }

            // Validate name uniqueness within the component (excluding this field)
            global $wpdb;
            $table = $wpdb->prefix . 'cc_fields';
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table WHERE name = %s AND component_id = %d AND id != %d",
                    $name,
                    $field->getComponentId(),
                    $field_id
                )
            );
            if ($exists) {
                wp_send_json_error(['message' => 'Field name already exists within this component.']);
                return;
            }

            $field->setLabel($label);
            $field->setName($name);
            $field->setRequired($required);
            $field->setPlaceholder($placeholder);
            $field->setType($type);

            $config = json_decode($field->getConfig(), true) ?: [];
            
            if ($type === 'repeater') {
                $max_sets = intval($_POST['max_sets'] ?? 0);
                $nested_field_definitions = json_decode(wp_unslash($_POST['nested_field_definitions'] ?? '[]'), true);
                
                error_log("CCC AjaxHandler: Updating repeater field {$field_id} with " . count($nested_field_definitions) . " nested fields");
                
                $sanitized_nested_fields = $this->sanitizeNestedFieldDefinitions($nested_field_definitions);

                $config['max_sets'] = $max_sets;
                $config['nested_fields'] = $sanitized_nested_fields;
                
                error_log("CCC AjaxHandler: Updated repeater config: " . json_encode($config));
            } elseif ($type === 'image') {
                if (!isset($config['return_type'])) {
                    $config['return_type'] = 'url';
                }
            }
            
            $field->setConfig(json_encode($config));

            if ($field->save()) {
                error_log("CCC AjaxHandler: Successfully updated field {$field_id}");
                wp_send_json_success(['message' => 'Field updated successfully.']);
            } else {
                wp_send_json_error(['message' => 'Failed to update field.']);
            }

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

            $fields = Field::findByComponent($component_id);
            $field_data = [];

            foreach ($fields as $field) {
                $value = '';
                if ($post_id && $instance_id) {
                    global $wpdb;
                    $values_table = $wpdb->prefix . 'cc_field_values';
                    $value = $wpdb->get_var($wpdb->prepare(
                        "SELECT value FROM $values_table WHERE post_id = %d AND field_id = %d AND instance_id = %s",
                        $post_id, $field->getId(), $instance_id
                    ));
                }
                
                $config_json = $field->getConfig();
                $decoded_config = [];
                if (!empty($config_json)) {
                    $decoded_config = json_decode($config_json, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        error_log("CCC AjaxHandler: JSON decode error for field {$field->getId()}: " . json_last_error_msg());
                        $decoded_config = [];
                    }
                }
                
                error_log("CCC AjaxHandler: Field {$field->getName()} (Type: {$field->getType()}) - Config: " . print_r($decoded_config, true));

                $field_data[] = [
                    'id' => $field->getId(),
                    'label' => $field->getLabel(),
                    'name' => $field->getName(),
                    'type' => $field->getType(),
                    'value' => $value ?: '',
                    'config' => $decoded_config,
                    'required' => $field->getRequired(),
                    'placeholder' => $field->getPlaceholder()
                ];
            }

            wp_send_json_success(['fields' => $field_data]);

        } catch (\Exception $e) {
            error_log("Exception in getComponentFields: " . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function getPostsWithComponents() {
        try {
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
                    'components' => $components ?: [],
                    'has_components' => !empty($components)
                ];
            }, $posts);

            wp_send_json_success(['posts' => $post_list]);

        } catch (\Exception $e) {
            error_log("Exception in getPostsWithComponents: " . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function saveComponentAssignments() {
        try {
            check_ajax_referer('ccc_nonce', 'nonce');

            $post_id = intval($_POST['post_id'] ?? 0);
            $components = json_decode(wp_unslash($_POST['components'] ?? '[]'), true);

            if (!$post_id) {
                wp_send_json_error(['message' => 'Invalid post ID.']);
                return;
            }

            update_post_meta($post_id, '_ccc_components', $components);
            wp_send_json_success(['message' => 'Component assignments saved successfully.']);

        } catch (\Exception $e) {
            error_log("Exception in saveComponentAssignments: " . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}