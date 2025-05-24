<?php
namespace CCC\Admin;

use CCC\Models\Component;
use CCC\Models\Field;
use CCC\Models\FieldValue;

defined('ABSPATH') || exit;

class MetaBoxManager {
    public function init() {
        add_action('add_meta_boxes', [$this, 'addComponentMetaBox']);
        add_action('save_post', [$this, 'saveComponentData'], 10, 2);
    }

    public function addComponentMetaBox() {
        add_meta_box(
            'ccc_component_selector',
            'Custom Components',
            [$this, 'renderComponentMetaBox'],
            ['post', 'page'],
            'normal',
            'high'
        );
    }

    public function renderComponentMetaBox($post) {
        wp_nonce_field('ccc_component_meta_box', 'ccc_component_nonce');
        
        $components = Component::all();
        $current_components = get_post_meta($post->ID, '_ccc_components', true);
        if (!is_array($current_components)) {
            $current_components = [];
        }

        $field_values = $this->getFieldValues($components, $post->ID);
        
        include plugin_dir_path(__FILE__) . '../Views/meta-box.php';
    }

    public function saveComponentData($post_id, $post) {
        if (!isset($_POST['ccc_component_nonce']) || !wp_verify_nonce($_POST['ccc_component_nonce'], 'ccc_component_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save component assignments
        $components = isset($_POST['ccc_components']) && is_array($_POST['ccc_components']) 
            ? array_map(function($comp) {
                return json_decode(wp_unslash($comp), true);
            }, $_POST['ccc_components']) 
            : [];
        
        update_post_meta($post_id, '_ccc_components', $components);

        // Save field values
        $field_values = isset($_POST['ccc_field_values']) && is_array($_POST['ccc_field_values']) 
            ? $_POST['ccc_field_values'] 
            : [];

        FieldValue::saveMultiple($post_id, $field_values);
    }

    private function getFieldValues($components, $post_id) {
        $field_values = [];
        
        foreach ($components as $component) {
            $fields = Field::findByComponent($component->getId());
            foreach ($fields as $field) {
                $field_values[$field->getId()] = $field->getValue($post_id);
            }
        }
        
        return $field_values;
    }
}
