<?php
namespace CCC\Services;

use CCC\Models\Component;
use CCC\Models\Field;

defined('ABSPATH') || exit;

class ComponentService {
    public function createComponent($name, $handle) {
        if (Component::handleExists($handle)) {
            throw new \Exception('Handle already exists. Please choose a different one.');
        }

        $component = new Component([
            'name' => sanitize_text_field($name),
            'handle_name' => sanitize_title($handle)
        ]);

        if (!$component->save()) {
            throw new \Exception('Failed to create component');
        }

        $this->createComponentTemplate($component);
        
        return $component;
    }

    /**
     * Update an existing component's name and handle
     */
    public function updateComponent($component_id, $name, $handle) {
        $component = Component::find($component_id);
        if (!$component) {
            throw new \Exception('Component not found.');
        }

        // Check if the new handle already exists (excluding this component)
        if (Component::handleExists($handle, $component_id)) {
            throw new \Exception('Handle already exists. Please choose a different one.');
        }

        $old_handle = $component->getHandleName();
        $component->setName(sanitize_text_field($name));
        $component->setHandleName(sanitize_title($handle));

        if (!$component->save()) {
            throw new \Exception('Failed to update component.');
        }

        // If the handle has changed, update the template file
        if ($old_handle !== $handle) {
            // Delete the old template file
            $theme_dir = get_stylesheet_directory();
            $old_template_file = $theme_dir . '/ccc-templates/' . $old_handle . '.php';
            if (file_exists($old_template_file)) {
                unlink($old_template_file);
            }

            // Create a new template file with the updated handle
            $this->createComponentTemplate($component);
        }

        return $component;
    }

    /**
     * Get all components with their fields and properly decoded configurations
     */
    public function getComponentsWithFields($post_id = 0) {
        $components = Component::all();
        $result = [];

        // Helper to recursively convert Field objects to arrays, loading children from DB
        $fieldToArray = function($field, $component_id) use (&$fieldToArray, $post_id) {
            $config_json = $field->getConfig();
            $decoded_config = null;
            if (!empty($config_json)) {
                $decoded_config = json_decode($config_json, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("CCC ComponentService: JSON decode error for field {$field->getId()}: " . json_last_error_msg());
                    $decoded_config = [];
                }
            }
            $arr = [
                'id' => $field->getId(),
                'label' => $field->getLabel(),
                'name' => $field->getName(),
                'type' => $field->getType(),
                'required' => $field->getRequired(),
                'placeholder' => $field->getPlaceholder(),
                'field_order' => $field->getFieldOrder(),
                'config' => $decoded_config,
                'value' => $post_id ? $this->getFieldValue($field->getId(), $post_id) : '',
                'children' => []
            ];
            // Handle repeater fields - check both database children and config nested_fields
            if ($field->getType() === 'repeater') {
                // First, try to get children from database (traditional approach)
                $children = \CCC\Models\Field::findFieldsTree($component_id, $field->getId());
                
                if (!empty($children)) {
                    // Traditional nested fields stored as database records
                    $arr['children'] = array_map(function($child) use (&$fieldToArray, $component_id) {
                        return $fieldToArray($child, $component_id);
                    }, $children);
                } elseif ($decoded_config && isset($decoded_config['nested_fields'])) {
                    // ChatGPT-created nested fields stored in config
                    $arr['children'] = $decoded_config['nested_fields'];
                    error_log("CCC ComponentService: Using nested_fields from config for field {$field->getId()}: " . count($decoded_config['nested_fields']) . " fields");
                } else {
                    // No children found
                    $arr['children'] = [];
                }
            }
            return $arr;
        };

        foreach ($components as $component) {
            $fields = \CCC\Models\Field::findFieldsTree($component->getId());
            $component_data = [
                'id' => $component->getId(),
                'name' => $component->getName(),
                'handle_name' => $component->getHandleName(),
                'fields' => array_map(function($field) use (&$fieldToArray, $component) {
                    return $fieldToArray($field, $component->getId());
                }, $fields)
            ];
            $result[] = $component_data;
        }

        error_log("CCC ComponentService: Returning " . count($result) . " components with fields");
        return $result;
    }

    /**
     * Helper method to get field value for a specific post
     */
    private function getFieldValue($field_id, $post_id, $instance_id = '') {
        global $wpdb;
        $values_table = $wpdb->prefix . 'cc_field_values';
        
        $query = "SELECT value FROM $values_table WHERE field_id = %d AND post_id = %d";
        $params = [$field_id, $post_id];
        
        if (!empty($instance_id)) {
            $query .= " AND instance_id = %s";
            $params[] = $instance_id;
        }
        
        $query .= " ORDER BY id DESC LIMIT 1";
        
        return $wpdb->get_var($wpdb->prepare($query, $params)) ?: '';
    }

    private function createComponentTemplate(Component $component) {
        $theme_dir = get_stylesheet_directory();
        $templates_dir = $theme_dir . '/ccc-templates';
        
        if (!file_exists($templates_dir)) {
            wp_mkdir_p($templates_dir);
        }

        $template_file = $templates_dir . '/' . $component->getHandleName() . '.php';
        $template_content = $this->generateTemplateContent($component);

        if (!file_put_contents($template_file, $template_content)) {
            error_log("Failed to write component template: $template_file");
            throw new \Exception('Failed to create component template file');
        }
    }

    private function generateTemplateContent(Component $component) {
        $component_name = $component->getName();
        $handle_name = $component->getHandleName();
        
        return "<?php\n"
            . "/**\n"
            . " * Component: $component_name\n"
            . " * Handle: $handle_name\n"
            . " * \n"
            . " * This template is automatically generated by Custom Craft Component.\n"
            . " * You can customize this template to match your design needs.\n"
            . " * \n"
            . " * Available helper functions:\n"
            . " * - get_ccc_field('field_name') - Get field value\n"
            . " * - get_ccc_field('field_name', 'url') - Get image URL\n"
            . " * - get_ccc_field('field_name', 'html') - Get safe HTML\n"
            . " * - get_ccc_fields() - Get all fields for current post\n"
            . " */\n"
            . "?>\n\n"
            . "<!-- $component_name Component -->\n"
            . "<div class=\"ccc-component ccc-$handle_name\">\n"
            . "    <!-- Add your HTML structure here -->\n"
            . "    <!-- Example: -->\n"
            . "    <!-- <h2><?php echo get_ccc_field('title'); ?></h2> -->\n"
            . "    <!-- <p><?php echo get_ccc_field('description'); ?></p> -->\n"
            . "    \n"
            . "    <!-- Use the Design with ChatGPT feature to generate a complete layout! -->\n"
            . "</div>\n";
    }
}