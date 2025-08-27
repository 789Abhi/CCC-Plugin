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
            throw new \Exception("A component with the handle '{$handle}' already exists. Please choose a different name or handle.");
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
     * Update an existing component
     */
    public function updateComponent($component_id, $name, $handle) {
        error_log("CCC ComponentService: updateComponent called for component {$component_id} -> {$name} ({$handle})");
        
        $component = Component::find($component_id);
        if (!$component) {
            error_log("CCC ComponentService: Component {$component_id} not found in database");
            throw new \Exception('Component not found.');
        }

        error_log("CCC ComponentService: Found component in database - Name: {$component->getName()}, Handle: {$component->getHandleName()}");

        // Check if the new handle already exists (excluding this component)
        if (Component::handleExistsExcluding($handle, $component_id)) {
            error_log("CCC ComponentService: Handle {$handle} already exists for another component");
            throw new \Exception("A component with the handle '{$handle}' already exists. Please choose a different name or handle.");
        }

        $old_handle = $component->getHandleName();
        error_log("CCC ComponentService: Old handle: {$old_handle}, New handle: {$handle}");
        
        $component->setName(sanitize_text_field($name));
        $component->setHandleName($this->sanitizeHandle($handle));

        if (!$component->save()) {
            error_log("CCC ComponentService: Failed to save component {$component_id}");
            throw new \Exception('Failed to update component.');
        }

        error_log("CCC ComponentService: Component saved successfully to database");

        // If the handle has changed, update the template file
        if ($old_handle !== $handle) {
            error_log("CCC ComponentService: Handle changed, updating template file");
            $this->updateComponentTemplate($component, $old_handle);
        } else {
            error_log("CCC ComponentService: Handle unchanged, skipping template update");
        }
        
        // Update component assignments in metaboxes to reflect the new name
        error_log("CCC ComponentService: Updating component assignments");
        $this->updateComponentAssignments($component_id, $name, $handle);

        error_log("CCC ComponentService: updateComponent completed successfully for component {$component_id}");
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

        // Check if old template file exists
        if (file_exists($old_template_file)) {
            $existing_content = file_get_contents($old_template_file);
            
            // Update the component header information while preserving all custom content
            $updated_content = $this->updateTemplateHeader($component, $existing_content);
            
            // First, try to rename the file (this preserves all file attributes and is atomic)
            if (rename($old_template_file, $new_template_file)) {
                // File renamed successfully, now update its content
                if (!file_put_contents($new_template_file, $updated_content)) {
                    error_log("Failed to update content in renamed template file: $new_template_file");
                    throw new \Exception('Failed to update content in renamed template file');
                }
                error_log("CCC ComponentService: Successfully renamed template file from {$old_handle}.php to {$component->getHandleName()}.php");
            } else {
                // If rename fails (e.g., different filesystem), fall back to copy and delete
                error_log("CCC ComponentService: File rename failed, falling back to copy and delete method");
                
                // Copy the file with new content
                if (!file_put_contents($new_template_file, $updated_content)) {
                    error_log("Failed to create new template file: $new_template_file");
                    throw new \Exception('Failed to create new template file');
                }
                
                // Delete the old file only after successful creation of new one
                if (file_exists($old_template_file)) {
                    unlink($old_template_file);
                }
            }
        } else {
            // Old template file doesn't exist, create a new one
            $new_content = $this->generateTemplateContent($component);
            if (!file_put_contents($new_template_file, $new_content)) {
                error_log("Failed to create new component template: $new_template_file");
                throw new \Exception('Failed to create new component template file');
            }
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
     * Update template header information while preserving all custom content
     */
    private function updateTemplateHeader(Component $component, $existing_content) {
        $component_name = $component->getName();
        $handle_name = $component->getHandleName();
        
        // Split content into PHP section and HTML/content section
        $parts = explode('?>', $existing_content, 2);
        
        if (count($parts) >= 2) {
            $php_section = trim($parts[0]);
            $html_content = trim($parts[1]);
            
            // Remove the old component header comment from PHP section
            $php_section = preg_replace('/\/\*\*[\s\S]*?\*\/\s*/', '', $php_section);
            
            // Clean up any remaining PHP tags or extra content in the PHP section
            $php_section = preg_replace('/<\?php\s*/', '', $php_section);
            $php_section = preg_replace('/\s*\?>\s*$/', '', $php_section);
            $php_section = trim($php_section);
            
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
            
            // Reconstruct the template with preserved HTML content
            return "<?php\n" . $new_header . $php_section . "\n?>\n" . $html_content;
        }
        
        // Fallback to basic template if parsing fails
        return $this->generateTemplateContent($component);
    }

    /**
     * Generate template content while preserving existing custom content
     * @deprecated Use updateTemplateHeader instead
     */
    private function generateTemplateContentWithPreservedContent(Component $component, $existing_content) {
        return $this->updateTemplateHeader($component, $existing_content);
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
        
        // Split content into PHP section and HTML/content section
        $parts = explode('?>', $existing_content, 2);
        
        if (count($parts) >= 2) {
            $html_content = trim($parts[1]);
            // If there's content after the PHP closing tag, it's custom content
            if (!empty($html_content)) {
                return true;
            }
        }
        
        // Also check if there's custom content within the PHP section (beyond the header)
        if (count($parts) >= 1) {
            $php_section = trim($parts[0]);
            $php_section_clean = preg_replace('/\/\*\*[\s\S]*?\*\/\s*/', '', $php_section);
            $php_section_clean = preg_replace('/\s+/', ' ', trim($php_section_clean));
            
            // If there's more than just the opening PHP tag, it has custom PHP content
            if (!empty($php_section_clean) && $php_section_clean !== '<?php') {
                return true;
            }
        }
        
        return false;
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
        
        error_log("CCC ComponentService: updateComponentAssignments called for component {$component_id} -> {$new_name} ({$new_handle})");
        
        // Get all posts that have this component assigned using a more reliable method
        // First, try to get posts using the meta query
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
        
        // Also check for posts that might have the component in a different format
        $additional_posts = get_posts([
            'post_type' => ['post', 'page'],
            'post_status' => 'any',
            'numberposts' => -1,
            'meta_query' => [
                [
                    'key' => '_ccc_components',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);
        
        // Merge and deduplicate posts
        $all_posts = [];
        $post_ids = [];
        foreach (array_merge($posts, $additional_posts) as $post) {
            if (!in_array($post->ID, $post_ids)) {
                $all_posts[] = $post;
                $post_ids[] = $post->ID;
            }
        }
        
        error_log("CCC ComponentService: Found " . count($all_posts) . " posts to check for component {$component_id}");
        
        foreach ($all_posts as $post) {
            error_log("CCC ComponentService: Processing post {$post->ID} ({$post->post_title})");
            
            $components = get_post_meta($post->ID, '_ccc_components', true);
            error_log("CCC ComponentService: Post {$post->ID} _ccc_components meta BEFORE update: " . print_r($components, true));
            
            if (is_array($components)) {
                $has_component = false;
                $updated_components = [];
                
                foreach ($components as $comp) {
                    error_log("CCC ComponentService: Processing component entry: " . print_r($comp, true));
                    
                    if (is_array($comp) && isset($comp['id']) && $comp['id'] == $component_id) {
                        // This is our updated component, update the name and handle
                        error_log("CCC ComponentService: Found component object, updating name and handle");
                        $comp['name'] = $new_name;
                        $comp['handle_name'] = $new_handle;
                        $updated_components[] = $comp;
                        $has_component = true;
                    } elseif ($comp === $component_id) {
                        // Legacy format: just the ID, convert to new format
                        error_log("CCC ComponentService: Found legacy format, converting to new format");
                        $updated_components[] = [
                            'id' => $component_id,
                            'name' => $new_name,
                            'handle_name' => $new_handle,
                            'order' => 0,
                            'instance_id' => '',
                            'isHidden' => false
                        ];
                        $has_component = true;
                    } else {
                        error_log("CCC ComponentService: Keeping other component: " . print_r($comp, true));
                        $updated_components[] = $comp;
                    }
                }
                
                // If we found and updated the component, save the changes
                if ($has_component) {
                    error_log("CCC ComponentService: Post {$post->ID} _ccc_components meta AFTER processing: " . print_r($updated_components, true));
                    
                    // Update the post meta with the updated component data
                    update_post_meta($post->ID, '_ccc_components', $updated_components);
                    
                    // Verify the update
                    $verified_components = get_post_meta($post->ID, '_ccc_components', true);
                    error_log("CCC ComponentService: Post {$post->ID} _ccc_components meta AFTER update_post_meta: " . print_r($verified_components, true));
                    
                    // Update the component details in post meta to reflect the new name and handle
                    $component_details = get_post_meta($post->ID, '_ccc_component_details', true);
                    if (is_array($component_details)) {
                        foreach ($component_details as &$detail) {
                            if ($detail['id'] == $component_id) {
                                $detail['name'] = $new_name;
                                $detail['handle_name'] = $new_handle;
                                break;
                            }
                        }
                        update_post_meta($post->ID, '_ccc_component_details', $component_details);
                    }
                    
                    // Clear any cached component data
                    $this->clearComponentCache($post->ID);
                    
                    error_log("CCC ComponentService: Updated component assignments for post {$post->ID}, component {$component_id} -> {$new_name} ({$new_handle})");
                } else {
                    error_log("CCC ComponentService: Post {$post->ID} does not contain component {$component_id}");
                }
            } else {
                error_log("CCC ComponentService: Post {$post->ID} _ccc_components meta is not an array: " . print_r($components, true));
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