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
        add_action('wp_ajax_ccc_get_posts', [$this, 'getPosts']); // This is likely unused now, but kept for safety
        add_action('wp_ajax_ccc_get_posts_with_components', [$this, 'getPostsWithComponents']);
        add_action('wp_ajax_ccc_save_component_assignments', [$this, 'saveComponentAssignments']);
        add_action('wp_ajax_ccc_delete_component', [$this, 'deleteComponent']);
        add_action('wp_ajax_ccc_delete_field', [$this, 'deleteField']);
        add_action('wp_ajax_ccc_save_assignments', [$this, 'saveAssignments']); // This is likely unused now, but kept for safety
        add_action('wp_ajax_ccc_save_field_values', [$this, 'saveFieldValues']);
        add_action('wp_ajax_nopriv_ccc_save_field_values', [$this, 'saveFieldValues']);
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

    public function getComponents() {
        try {
            check_ajax_referer('ccc_nonce', 'nonce');

            // No longer need post_id here as this is for the main component list
            // $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $components = $this->component_service->getComponentsWithFields(); // Fetch all components with their fields

            wp_send_json_success(['components' => $components]);

        } catch (\Exception $e) {
            error_log("Exception in getComponents: " . $e->getMessage());
            wp_send_json_error(['message' => 'Failed to fetch components: ' . $e->getMessage()]);
        }
    }

    /**
     * Recursively sanitizes nested field definitions.
     *
     * @param array $nested_fields The array of nested field definitions.
     * @return array The sanitized nested field definitions.
     */
    private function sanitizeNestedFieldDefinitions(array $nested_fields): array {
        $sanitized_fields = [];
        foreach ($nested_fields as $nf) {
            $sanitized_nf = [
                'label' => sanitize_text_field($nf['label'] ?? ''),
                'name' => sanitize_title($nf['name'] ?? ''),
                'type' => sanitize_text_field($nf['type'] ?? ''),
            ];

            // If it's a repeater, recursively sanitize its config
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

            // Handle type-specific configurations
            $config = [];
            
            if ($type === 'repeater') {
                $max_sets = intval($_POST['max_sets'] ?? 0);
                $nested_field_definitions = json_decode(wp_unslash($_POST['nested_field_definitions'] ?? '[]'), true);
                
                // Sanitize nested field definitions recursively
                $sanitized_nested_fields = $this->sanitizeNestedFieldDefinitions($nested_field_definitions);

                $config = [
                    'max_sets' => $max_sets,
                    'nested_fields' => $sanitized_nested_fields
                ];
            } elseif ($type === 'image') {
                $return_type = sanitize_text_field($_POST['return_type'] ?? 'url');
                $config = [
                    'return_type' => $return_type
                ];
            }

            // Create field with configuration
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
            $name = sanitize_text_field($_POST['name'] ?? ''); // Name cannot be changed, but we receive it
            $type = sanitize_text_field($_POST['type'] ?? ''); // Type can now be changed
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
            $field->setType($type); // Allow updating the field type

            // Handle type-specific configurations for update
            $config = json_decode($field->getConfig(), true) ?: [];
            
            if ($type === 'repeater') {
                $max_sets = intval($_POST['max_sets'] ?? 0);
                $nested_field_definitions = json_decode(wp_unslash($_POST['nested_field_definitions'] ?? '[]'), true);
                
                // Sanitize nested field definitions recursively
                $sanitized_nested_fields = $this->sanitizeNestedFieldDefinitions($nested_field_definitions);

                $config['max_sets'] = $max_sets;
                $config['nested_fields'] = $sanitized_nested_fields;
            } elseif ($type === 'image') {
                // Image return type is not currently editable in the modal, but if it were, it would be handled here.
                // For now, we just ensure the config is preserved if no change is intended.
            }
            
            $field->setConfig(json_encode($config));

            if ($field->save()) {
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
        // This function is likely deprecated by saveComponentAssignments, but kept for safety.
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

            if (!$component_id) {
                wp_send_json_error(['message' => 'Invalid component ID.']);
                return;
            }

            $fields = Field::findByComponent($component_id);
            $field_data = [];

            foreach ($fields as $field) {
                // Get value for this specific instance
                $value = '';
                if ($post_id && $instance_id) {
                    global $wpdb;
                    $values_table = $wpdb->prefix . 'cc_field_values';
                    $value = $wpdb->get_var($wpdb->prepare(
                        "SELECT value FROM $values_table WHERE post_id = %d AND field_id = %d AND instance_id = %s",
                        $post_id, $field->getId(), $instance_id
                    ));
                }

                $field_data[] = [
                    'id' => $field->getId(),
                    'label' => $field->getLabel(),
                    'name' => $field->getName(),
                    'type' => $field->getType(),
                    'value' => $value ?: '',
                    'config' => json_decode($field->getConfig(), true), // Pass config for repeater fields
                    'required' => $field->getRequired(),
                    'placeholder' => $field->getPlaceholder() // Pass placeholder
                ];
            }

            wp_send_json_success(['fields' => $field_data]);

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
                'has_components' => !empty($components), // Check if ANY components are assigned
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
       
            // $component_data_array now contains full component objects (id, name, handle_name)
            // We need to ensure instance_ids are preserved or generated for these.
            $existing_components = get_post_meta($post_id, '_ccc_components', true);
            if (!is_array($existing_components)) {
                $existing_components = [];
            }

            $new_components_data = [];
            $current_order = 0;

            foreach ($component_data_array as $incoming_comp) {
                $component_id = intval($incoming_comp['id']);
                
                // Try to find an existing instance of this component to reuse its instance_id
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
            
            // Sort by order (already handled by $current_order++)
            // usort($new_components_data, function($a, $b) {
            //     return $a['order'] - $b['order'];
            // });
            
            update_post_meta($post_id, '_ccc_components', $new_components_data);
        }

        wp_send_json_success(['message' => 'Component assignments saved successfully']);
    }
}
