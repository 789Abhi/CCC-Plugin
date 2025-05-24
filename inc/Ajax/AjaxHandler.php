<?php
namespace CCC\Ajax;

use CCC\Services\ComponentService;
use CCC\Services\FieldService;
use CCC\Models\Component;
use CCC\Models\FieldValue;

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
        add_action('wp_ajax_ccc_add_field', [$this, 'addFieldCallback']);
        add_action('wp_ajax_ccc_get_posts', [$this, 'getPosts']);
        add_action('wp_ajax_ccc_delete_component', [$this, 'deleteComponent']);
        add_action('wp_ajax_ccc_delete_field', [$this, 'deleteField']);
        add_action('wp_ajax_ccc_save_assignments', [$this, 'saveAssignments']);
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

            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $components = $this->component_service->getComponentsWithFields($post_id);

            wp_send_json_success(['components' => $components]);

        } catch (\Exception $e) {
            error_log("Exception in getComponents: " . $e->getMessage());
            wp_send_json_error(['message' => 'Failed to fetch components: ' . $e->getMessage()]);
        }
    }

    public function addFieldCallback() {
        try {
            check_ajax_referer('ccc_nonce', 'nonce');

            $label = sanitize_text_field($_POST['label'] ?? '');
            $name = sanitize_text_field($_POST['name'] ?? '');
            $type = sanitize_text_field($_POST['type'] ?? '');
            $component_id = intval($_POST['component_id'] ?? 0);

            if (empty($label) || empty($name) || empty($type) || empty($component_id)) {
                wp_send_json_error(['message' => 'Missing required fields.']);
                return;
            }

            $this->field_service->createField($component_id, $label, $name, $type);
            wp_send_json_success(['message' => 'Field added successfully.']);

        } catch (\Exception $e) {
            error_log("Exception in addFieldCallback: " . $e->getMessage());
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
}
