<?php

namespace CCC\Admin;

use CCC\Models\Component;
use CCC\Models\Field;

defined('ABSPATH') || exit;

class MetaBoxManager {

    public function __construct() {
        // Constructor - no longer registering field renderers
    }

    public function init() {
        add_action('add_meta_boxes', [$this, 'addComponentMetaBox'], 10, 2);
        add_action('save_post', [$this, 'saveComponentData'], 10, 2);
    }

    // Old field renderers registration removed - now using React components

    public function addComponentMetaBox($post_type, $post = null) {
        if (!$post || !($post instanceof \WP_Post) || empty($post->ID)) {
            return;
        }

        // Show metabox if this post has components assigned OR if it previously had components
        // This ensures the metabox stays visible even when all components are removed
        // The metabox will only disappear when the user explicitly saves the page with no components
        $components = get_post_meta($post->ID, '_ccc_components', true);
        $had_components = get_post_meta($post->ID, '_ccc_had_components', true);
        
        error_log("CCC MetaBoxManager: Post {$post->ID} - components: " . json_encode($components) . ", had_components: " . $had_components);
        
        if ((is_array($components) && !empty($components)) || $had_components) {
            error_log("CCC MetaBoxManager: Adding metabox for post {$post->ID}");
            add_meta_box(
                'ccc_component_selector',
                'Custom Components',
                [$this, 'renderComponentMetaBox'],
                ['post', 'page'],
                'normal',
                'high'
            );
        } else {
            error_log("CCC MetaBoxManager: Not adding metabox for post {$post->ID} - no components and never had components");
        }
    }

    public function renderComponentMetaBox($post) {
        wp_nonce_field('ccc_component_meta_box', 'ccc_component_nonce');
        
        error_log("CCC MetaBoxManager: Rendering metabox for post {$post->ID}");
        
        echo '<div class="ccc-meta-box">';
        echo '<div id="ccc-metabox-root" data-post-id="' . esc_attr($post->ID) . '"></div>';
        echo '</div>';
    }

    // Old PHP metabox methods removed - now using React metabox

    // Old field rendering removed - now using React components

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

        $components_data = isset($_POST['ccc_components_data']) ? json_decode(wp_unslash($_POST['ccc_components_data']), true) : [];
        
        error_log("CCC MetaBoxManager: Saving component data for post {$post_id} - received " . count($components_data) . " components");
        
        $components = [];
        foreach ($components_data as $comp) {
            $components[] = [
                'id' => intval($comp['id']),
                'name' => sanitize_text_field($comp['name']),
                'handle_name' => sanitize_text_field($comp['handle_name']),
                'order' => intval($comp['order'] ?? 0),
                'instance_id' => sanitize_text_field($comp['instance_id'] ?? '')
            ];
        }
        
        usort($components, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        
        update_post_meta($post_id, '_ccc_components', $components);
        
        // Update the had_components flag based on whether components are currently assigned
        if (!empty($components)) {
            update_post_meta($post_id, '_ccc_had_components', '1'); // Mark that components were previously assigned
            error_log("CCC MetaBoxManager: Post {$post_id} has components, setting _ccc_had_components flag");
        } else {
            // For metabox saves, preserve the flag to keep metabox visible
            // This ensures the metabox stays visible even when all components are removed
            // The metabox will only disappear when the user explicitly saves via main plugin interface
            error_log("CCC MetaBoxManager: Post {$post_id} metabox save with no components, preserving _ccc_had_components flag");
        }
        
        // Note: Template removal is now manual - users must manually change the template
        // if they want to remove the CCC template when no components are assigned

        $field_values = isset($_POST['ccc_field_values']) && is_array($_POST['ccc_field_values']) 
            ? $_POST['ccc_field_values'] 
            : [];

        global $wpdb;
        $field_values_table = $wpdb->prefix . 'cc_field_values';
        
        $wpdb->delete($field_values_table, ['post_id' => $post_id], ['%d']);

        foreach ($field_values as $instance_id => $instance_fields) {
            if (!is_array($instance_fields)) continue;
            
            foreach ($instance_fields as $field_id => $value) {
                $field_id = intval($field_id);
                
                $field_obj = Field::find($field_id);
                if (!$field_obj) {
                    error_log("CCC: Field object not found for field_id: $field_id during save.");
                    continue;
                }

                $value_to_save = $this->sanitizeFieldValue($value, $field_obj);
                
                if ($value_to_save !== '' && $value_to_save !== '[]') {
                    $result = $wpdb->insert(
                        $field_values_table,
                        [
                            'post_id' => $post_id,
                            'field_id' => $field_id,
                            'instance_id' => $instance_id,
                            'value' => $value_to_save,
                            'created_at' => current_time('mysql')
                        ],
                        ['%d', '%d', '%s', '%s', '%s']
                    );
                    
                    if ($result === false) {
                        error_log("CCC: Failed to save field value for field_id: $field_id, instance: $instance_id, post_id: $post_id, error: " . $wpdb->last_error);
                    }
                }
            }
        }
    }

    private function sanitizeFieldValue($value, $field_obj) {
        $field_type = $field_obj->getType();
        $value_to_save = wp_unslash($value);
        
        switch ($field_type) {
            case 'repeater':
                $decoded_value = json_decode($value_to_save, true);
                $sanitized_decoded_value = $this->sanitizeRepeaterData($decoded_value, $field_obj->getConfig());
                return json_encode($sanitized_decoded_value);
                
            case 'image':
                $field_config = json_decode($field_obj->getConfig(), true);
                $return_type = $field_config['return_type'] ?? 'url';
                if ($return_type === 'url') {
                    $decoded_value = json_decode($value_to_save, true);
                    if (is_array($decoded_value) && isset($decoded_value['url'])) {
                        return esc_url_raw($decoded_value['url']);
                    } else {
                        return esc_url_raw($value_to_save);
                    }
                } else {
                    $decoded_value = json_decode($value_to_save, true);
                    return json_encode($decoded_value);
                }
                
            case 'color':
                return sanitize_hex_color($value_to_save) ?: sanitize_text_field($value_to_save);
                
            case 'checkbox':
                if (is_array($value_to_save)) {
                    return implode(',', array_map('sanitize_text_field', $value_to_save));
                }
                return sanitize_text_field($value_to_save);
                
            case 'select':
                if (is_array($value_to_save)) {
                    return implode(',', array_map('sanitize_text_field', $value_to_save));
                }
                return sanitize_text_field($value_to_save);
                
            case 'radio':
                return sanitize_text_field($value_to_save);
                
            case 'wysiwyg':
                return wp_kses_post($value_to_save);
                
            default:
                return wp_kses_post($value_to_save);
        }
    }

    private function sanitizeRepeaterData($data, $config_json) {
        // Check if this is the new format with data and state
        if (is_array($data) && isset($data['data']) && isset($data['state'])) {
            $repeater_data = $data['data'];
            $repeater_state = $data['state'];
            
            // Sanitize the data part
            $sanitized_data = [];
            $config = json_decode($config_json, true);
            $nested_field_definitions = $config['nested_fields'] ?? [];

            foreach ($repeater_data as $item) {
                $sanitized_item = [];
                foreach ($nested_field_definitions as $nested_field_def) {
                    $field_name = $nested_field_def['name'];
                    $field_type = $nested_field_def['type'];
                    $nested_field_config = $nested_field_def['config'] ?? [];

                    if (isset($item[$field_name])) {
                        $value = $item[$field_name];
                        
                        switch ($field_type) {
                            case 'image':
                                $return_type = $nested_field_config['return_type'] ?? 'url';
                                if ($return_type === 'url') {
                                    $decoded_value = json_decode($value, true);
                                    if (is_array($decoded_value) && isset($decoded_value['url'])) {
                                        $sanitized_item[$field_name] = esc_url_raw($decoded_value['url']);
                                    } else {
                                        $sanitized_item[$field_name] = esc_url_raw($value);
                                    }
                                } else {
                                    $decoded_value = json_decode($value, true);
                                    $sanitized_item[$field_name] = json_encode($decoded_value);
                                }
                                break;
                                
                            case 'repeater':
                                $decoded_value = is_array($value) ? $value : json_decode($value, true);
                                $sanitized_item[$field_name] = $this->sanitizeRepeaterData($decoded_value, json_encode($nested_field_config));
                                break;
                                
                            case 'color':
                                $sanitized_item[$field_name] = sanitize_hex_color($value) ?: sanitize_text_field($value);
                                break;
                                
                            case 'checkbox':
                            case 'select':
                                if (is_array($value)) {
                                    $sanitized_item[$field_name] = implode(',', array_map('sanitize_text_field', $value));
                                } else {
                                    $sanitized_item[$field_name] = sanitize_text_field($value);
                                }
                                break;
                                
                            case 'wysiwyg':
                                $sanitized_item[$field_name] = wp_kses_post($value);
                                break;
                                
                            default:
                                $sanitized_item[$field_name] = wp_kses_post($value);
                                break;
                        }
                    }
                }
                $sanitized_data[] = $sanitized_item;
            }

            // Sanitize the state part (ensure it's an array of booleans)
            $sanitized_state = [];
            foreach ($repeater_state as $index => $is_expanded) {
                $sanitized_state[$index] = (bool) $is_expanded;
            }

            // Return the new format with both data and state
            return [
                'data' => $sanitized_data,
                'state' => $sanitized_state
            ];
        } else {
            // Legacy format - just sanitize the data array
            $sanitized_data = [];
            $config = json_decode($config_json, true);
            $nested_field_definitions = $config['nested_fields'] ?? [];

            foreach ($data as $item) {
                $sanitized_item = [];
                foreach ($nested_field_definitions as $nested_field_def) {
                    $field_name = $nested_field_def['name'];
                    $field_type = $nested_field_def['type'];
                    $nested_field_config = $nested_field_def['config'] ?? [];

                    if (isset($item[$field_name])) {
                        $value = $item[$field_name];
                        
                        switch ($field_type) {
                            case 'image':
                                $return_type = $nested_field_config['return_type'] ?? 'url';
                                if ($return_type === 'url') {
                                    $decoded_value = json_decode($value, true);
                                    if (is_array($decoded_value) && isset($decoded_value['url'])) {
                                        $sanitized_item[$field_name] = esc_url_raw($decoded_value['url']);
                                    } else {
                                        $sanitized_item[$field_name] = esc_url_raw($value);
                                    }
                                } else {
                                    $decoded_value = json_decode($value, true);
                                    $sanitized_item[$field_name] = json_encode($decoded_value);
                                }
                                break;
                                
                            case 'repeater':
                                $decoded_value = is_array($value) ? $value : json_decode($value, true);
                                $sanitized_item[$field_name] = $this->sanitizeRepeaterData($decoded_value, json_encode($nested_field_config));
                                break;
                                
                            case 'color':
                                $sanitized_item[$field_name] = sanitize_hex_color($value) ?: sanitize_text_field($value);
                                break;
                                
                            case 'checkbox':
                            case 'select':
                                if (is_array($value)) {
                                    $sanitized_item[$field_name] = implode(',', array_map('sanitize_text_field', $value));
                                } else {
                                    $sanitized_item[$field_name] = sanitize_text_field($value);
                                }
                                break;
                                
                            case 'wysiwyg':
                                $sanitized_item[$field_name] = wp_kses_post($value);
                                break;
                                
                            default:
                                $sanitized_item[$field_name] = wp_kses_post($value);
                                break;
                        }
                    }
                }
                $sanitized_data[] = $sanitized_item;
            }

            return $sanitized_data;
        }
    }

    private function getFieldValues($components, $post_id) {
        global $wpdb;
        $field_values_table = $wpdb->prefix . 'cc_field_values';
        
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
            $field_values[$instance_id][$value['field_id']] = $value['value'];
        }
        
        return $field_values;
    }

    // Old metabox styles removed - now using React with Tailwind CSS

    // Old metabox scripts removed - now using React components

}
