<?php
namespace CCC\Frontend;

defined('ABSPATH') || exit;

class TemplateManager {
    public function init() {
        add_filter('theme_page_templates', [$this, 'addCccTemplate']);
        add_filter('template_include', [$this, 'loadCccTemplate']);
        
        // Add helper functions for templates
        $this->addHelperFunctions();
    }

    public function addCccTemplate($templates) {
        // Only add CCC template option if we're editing a page/post
        global $post;
        
        // Check if we're in the admin area and editing a post/page
        if (is_admin() && $post && $post->ID) {
            // Check if this post has components assigned OR previously had components
            $components = get_post_meta($post->ID, '_ccc_components', true);
            $had_components = get_post_meta($post->ID, '_ccc_had_components', true);
            
            if ((is_array($components) && !empty($components)) || $had_components) {
                $templates['ccc-template.php'] = 'CCC Component Template';
            }
        }
        
        return $templates;
    }

    public function loadCccTemplate($template) {
        if (is_singular()) {
            $post_id = get_the_ID();
            $page_template = get_post_meta($post_id, '_wp_page_template', true);
            
            error_log("Checking template for post ID $post_id: $page_template");
            
            if ($page_template === 'ccc-template.php') {
                $plugin_template = plugin_dir_path(__FILE__) . '../../ccc-template.php';
                if (file_exists($plugin_template)) {
                    error_log("Loading CCC template: $plugin_template");
                    return $plugin_template;
                } else {
                    error_log("CCC template not found: $plugin_template");
                }
            }
        }
        return $template;
    }
    
    /**
     * Add helper functions for component templates
     */
    private function addHelperFunctions() {
        // Make get_ccc_field function available globally
        if (!function_exists('get_ccc_field')) {
            /**
             * Get CCC field value for current post
             * 
             * @param string $field_name The field name
             * @param string $format Optional format (url, id, array, etc.)
             * @param int $post_id Optional post ID (defaults to current post)
             * @param string $instance_id Optional instance ID for repeaters
             * @return mixed Field value
             */
            function get_ccc_field($field_name, $format = null, $post_id = null, $instance_id = '') {
                global $wpdb;
                
                // Default to current post if not specified
                if (!$post_id) {
                    $post_id = get_the_ID();
                }
                
                if (!$post_id) {
                    return '';
                }
                
                // Get field ID from field name
                $fields_table = $wpdb->prefix . 'cc_fields';
                $field_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $fields_table WHERE name = %s",
                    $field_name
                ));
                
                if (!$field_id) {
                    return '';
                }
                
                // Get field value
                $values_table = $wpdb->prefix . 'cc_field_values';
                $query = "SELECT value FROM $values_table WHERE field_id = %d AND post_id = %d";
                $params = [$field_id, $post_id];
                
                if (!empty($instance_id)) {
                    $query .= " AND instance_id = %s";
                    $params[] = $instance_id;
                }
                
                $query .= " ORDER BY id DESC LIMIT 1";
                $value = $wpdb->get_var($wpdb->prepare($query, $params));
                
                if (!$value) {
                    return '';
                }
                
                // Handle different formats
                if ($format === 'url' && is_numeric($value)) {
                    // Return image URL for image field
                    return wp_get_attachment_url($value);
                } elseif ($format === 'id' && is_numeric($value)) {
                    // Return attachment ID
                    return $value;
                } elseif ($format === 'array' && is_numeric($value)) {
                    // Return image array
                    return wp_get_attachment_image_src($value, 'full');
                } elseif ($format === 'html') {
                    // Return safe HTML
                    return wp_kses_post($value);
                } elseif ($format === 'raw') {
                    // Return raw value
                    return $value;
                } else {
                    // Default: Check if this is an image field and return URL
                    $fields_table = $wpdb->prefix . 'cc_fields';
                    $field_type = $wpdb->get_var($wpdb->prepare(
                        "SELECT type FROM $fields_table WHERE id = %d",
                        $field_id
                    ));
                    
                    if ($field_type === 'image' && is_numeric($value)) {
                        // For image fields, return URL by default
                        return wp_get_attachment_url($value);
                    } else {
                        // For other fields, return escaped value
                        return esc_html($value);
                    }
                }
            }
        }
        
        // Make get_ccc_fields function available globally
        if (!function_exists('get_ccc_fields')) {
            /**
             * Get all CCC fields for current post
             * 
             * @param int $post_id Optional post ID (defaults to current post)
             * @return array Array of field values
             */
            function get_ccc_fields($post_id = null) {
                global $wpdb;
                
                // Default to current post if not specified
                if (!$post_id) {
                    $post_id = get_the_ID();
                }
                
                if (!$post_id) {
                    return [];
                }
                
                // Get all field values for this post
                $values_table = $wpdb->prefix . 'cc_field_values';
                $fields_table = $wpdb->prefix . 'cc_fields';
                
                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT f.name, f.type, fv.value, fv.instance_id 
                     FROM $values_table fv 
                     JOIN $fields_table f ON fv.field_id = f.id 
                     WHERE fv.post_id = %d 
                     ORDER BY f.field_order ASC",
                    $post_id
                ));
                
                $fields = [];
                foreach ($results as $result) {
                    $fields[$result->name] = [
                        'value' => $result->value,
                        'type' => $result->type,
                        'instance_id' => $result->instance_id
                    ];
                }
                
                return $fields;
            }
        }
        
        // Make get_ccc_repeater_items function available globally
        if (!function_exists('get_ccc_repeater_items')) {
            /**
             * Get CCC repeater field items, filtering out hidden items
             * 
             * @param string $field_name The repeater field name
             * @param int $post_id Optional post ID (defaults to current post)
             * @param string $instance_id Optional instance ID
             * @return array Array of visible repeater items
             */
            function get_ccc_repeater_items($field_name, $post_id = null, $instance_id = '') {
                // Get the raw repeater data
                $raw_data = get_ccc_field($field_name, 'raw', $post_id, $instance_id);
                
                if (!$raw_data) {
                    return [];
                }
                
                // Parse the JSON data
                $items = json_decode($raw_data, true);
                
                if (!is_array($items)) {
                    return [];
                }
                
                // Filter out hidden items
                $visible_items = array_filter($items, function($item) {
                    return !isset($item['_hidden']) || !$item['_hidden'];
                });
                
                // Remove the _hidden property from visible items
                $clean_items = array_map(function($item) {
                    unset($item['_hidden']);
                    return $item;
                }, $visible_items);
                
                return array_values($clean_items); // Re-index array
            }
        }
    }
}
