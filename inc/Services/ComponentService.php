<?php
namespace CCC\Services;

use CCC\Models\Component;
use CCC\Models\Field;

defined('ABSPATH') || exit;

class ComponentService {
    
    /**
     * Custom sanitization for component handles
     * Less aggressive than sanitize_title to preserve important characters
     */
    public function sanitizeHandle($handle) {
        // Convert to lowercase
        $handle = strtolower($handle);
        
        // Replace spaces with underscores
        $handle = preg_replace('/\s+/', '_', $handle);
        
        // Remove special characters but keep letters, numbers, and underscores
        $handle = preg_replace('/[^a-z0-9_]/', '', $handle);
        
        // Remove multiple consecutive underscores
        $handle = preg_replace('/_+/', '_', $handle);
        
        // Remove leading and trailing underscores
        $handle = trim($handle, '_');
        
        return $handle;
    }

    public function createComponent($name, $handle) {
        if (Component::handleExists($handle)) {
            throw new \Exception('Handle already exists. Please choose a different one.');
        }

        $component = new Component([
            'name' => sanitize_text_field($name),
            'handle_name' => $this->sanitizeHandle($handle)
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
        if (Component::handleExistsExcluding($handle, $component_id)) {
            throw new \Exception('Handle already exists. Please choose a different one.');
        }

        $old_handle = $component->getHandleName();
        $component->setName(sanitize_text_field($name));
        $component->setHandleName($this->sanitizeHandle($handle));

        if (!$component->save()) {
            throw new \Exception('Failed to update component.');
        }

        // If the handle has changed, update the template file
        if ($old_handle !== $handle) {
            $this->updateComponentTemplate($component, $old_handle);
        }
        
        // Update component assignments in metaboxes to reflect the new name
        $this->updateComponentAssignments($component_id, $name, $handle);

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

    private function updateComponentTemplate(Component $component, $old_handle) {
        $theme_dir = get_stylesheet_directory();
        $templates_dir = $theme_dir . '/ccc-templates';
        $old_template_file = $templates_dir . '/' . $old_handle . '.php';
        $new_template_file = $templates_dir . '/' . $component->getHandleName() . '.php';
        
        // Ensure templates directory exists
        if (!file_exists($templates_dir)) {
            wp_mkdir_p($templates_dir);
        }

        // Check if old template file exists and has custom content
        $existing_content = '';
        $has_custom_content = false;
        
        if (file_exists($old_template_file)) {
            $existing_content = file_get_contents($old_template_file);
            
            // Check if the file has content beyond the basic template
            $basic_template = $this->generateTemplateContent($component);
            $basic_template_clean = preg_replace('/\s+/', ' ', trim($basic_template));
            $existing_content_clean = preg_replace('/\s+/', ' ', trim($existing_content));
            
            // If existing content is different from basic template, it has custom content
            if ($existing_content_clean !== $basic_template_clean) {
                $has_custom_content = true;
            }
        }

        // Create new template file
        if ($has_custom_content) {
            // Preserve existing content but update component info
            $new_content = $this->generateTemplateContentWithPreservedContent($component, $existing_content);
        } else {
            // Generate new basic template
            $new_content = $this->generateTemplateContent($component);
        }

        // Write new template file
        if (!file_put_contents($new_template_file, $new_content)) {
            error_log("Failed to write updated component template: $new_template_file");
            throw new \Exception('Failed to update component template file');
        }

        // Delete old template file after successful creation of new one
        if (file_exists($old_template_file)) {
            unlink($old_template_file);
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
            . " * Example Usage to Fetch the Values:\n"
            . " * \$title = get_ccc_field('title') - Get field value\n"
            . " */\n"
            . "?>\n";
    }

    /**
     * Generate template content while preserving existing custom content
     */
    private function generateTemplateContentWithPreservedContent(Component $component, $existing_content) {
        $component_name = $component->getName();
        $handle_name = $component->getHandleName();
        
        // Extract the content between the opening PHP tag and the closing PHP tag
        $pattern = '/<\?php\s*(.*?)\s*\?>/s';
        if (preg_match($pattern, $existing_content, $matches)) {
            $inner_content = trim($matches[1]);
            
            // Remove the old component header comment if it exists
            $inner_content = preg_replace('/\/\*\*[\s\S]*?\*\/\s*/', '', $inner_content);
            
            // Add new component header
            $new_header = "/**\n"
                . " * Component: $component_name\n"
                . " * Handle: $handle_name\n"
                . " * \n"
                . " * This template is automatically generated by Custom Craft Component.\n"
                . " * You can customize this template to match your design needs.\n"
                . " * \n"
                . " * Example Usage to Fetch the Values:\n"
                . " * \$title = get_ccc_field('title') - Get field value\n"
                . " */\n\n";
            
            return "<?php\n" . $new_header . $inner_content . "\n?>";
        }
        
        // Fallback to basic template if parsing fails
        return $this->generateTemplateContent($component);
    }

    /**
     * Check if a component template file has custom content beyond the basic template
     */
    public function hasCustomTemplateContent($handle) {
        $theme_dir = get_stylesheet_directory();
        $template_file = $theme_dir . '/ccc-templates/' . $handle . '.php';
        
        if (!file_exists($template_file)) {
            return false;
        }
        
        $existing_content = file_get_contents($template_file);
        
        // Create a temporary component object to generate basic template for comparison
        $temp_component = new Component([
            'name' => 'Temp',
            'handle_name' => $handle
        ]);
        
        $basic_template = $this->generateTemplateContent($temp_component);
        $basic_template_clean = preg_replace('/\s+/', ' ', trim($basic_template));
        $existing_content_clean = preg_replace('/\s+/', ' ', trim($existing_content));
        
        // If existing content is different from basic template, it has custom content
        return $existing_content_clean !== $basic_template_clean;
    }

    /**
     * Get the existing template content for a component
     */
    public function getTemplateContent($handle) {
        $theme_dir = get_stylesheet_directory();
        $template_file = $theme_dir . '/ccc-templates/' . $handle . '.php';
        
        if (!file_exists($template_file)) {
            return '';
        }
        
        return file_get_contents($template_file);
    }
    
    /**
     * Update component assignments in metaboxes when component name/handle changes
     */
    private function updateComponentAssignments($component_id, $new_name, $new_handle) {
        global $wpdb;
        
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
        
        foreach ($posts as $post) {
            $components = get_post_meta($post->ID, '_ccc_components', true);
            if (is_array($components)) {
                // Update the component data in the assignments
                $updated_components = [];
                foreach ($components as $comp) {
                    if ($comp === $component_id) {
                        // This is our updated component, keep the ID but update the data
                        $updated_components[] = $component_id;
                    } else {
                        $updated_components[] = $comp;
                    }
                }
                
                // Update the post meta
                update_post_meta($post->ID, '_ccc_components', $updated_components);
                
                // Also update any cached component data
                $this->clearComponentCache($post->ID);
            }
        }
    }
    
    /**
     * Clear component cache for a specific post
     */
    private function clearComponentCache($post_id) {
        // Clear any WordPress object cache for this post
        wp_cache_delete($post_id, 'post_meta');
        
        // Clear any transients that might be caching component data
        delete_transient('ccc_components_post_' . $post_id);
        delete_transient('ccc_components_cache');
    }
    
    /**
     * Get all posts that have a specific component assigned
     */
    public function getPostsWithComponent($component_id) {
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
        
        return $posts;
    }
    
    /**
     * Remove a specific component from all posts
     */
    public function removeComponentFromAllPosts($component_id) {
        $posts = $this->getPostsWithComponent($component_id);
        
        foreach ($posts as $post) {
            $components = get_post_meta($post->ID, '_ccc_components', true);
            if (is_array($components)) {
                // Remove this component from the assignments
                $components = array_filter($components, function($comp) use ($component_id) {
                    return $comp !== $component_id;
                });
                
                if (empty($components)) {
                    // No more components, remove the metabox
                    delete_post_meta($post->ID, '_ccc_components');
                    delete_post_meta($post->ID, '_ccc_had_components');
                    delete_post_meta($post->ID, '_ccc_assigned_via_main_interface');
                } else {
                    // Update with remaining components
                    update_post_meta($post->ID, '_ccc_components', $components);
                }
            }
        }
        
        return count($posts);
    }
}