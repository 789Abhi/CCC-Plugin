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
        if (!is_array($components_data)) {
            $components_data = [];
        }
        error_log("CCC MetaBoxManager: Saving component data for post {$post_id} - received " . count($components_data) . " components");
        
        $components = [];
        foreach ($components_data as $comp) {
            $components[] = [
                'id' => intval($comp['id']),
                'name' => sanitize_text_field($comp['name']),
                'handle_name' => sanitize_text_field($comp['handle_name']),
                'order' => intval($comp['order'] ?? 0),
                'instance_id' => sanitize_text_field($comp['instance_id'] ?? ''),
                'isHidden' => isset($comp['isHidden']) ? (bool)$comp['isHidden'] : false
            ];
        }
        
        usort($components, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        
        update_post_meta($post_id, '_ccc_components', $components);
        // Also update _ccc_previous_components to match current components
        update_post_meta($post_id, '_ccc_previous_components', $components);
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

        // Fix: decode JSON string to array for field values
        $field_values = [];
        if (isset($_POST['ccc_field_values'])) {
            error_log("CCC DEBUG: MetaBoxManager received ccc_field_values: " . $_POST['ccc_field_values']);
            $decoded = json_decode(wp_unslash($_POST['ccc_field_values']), true);
            if (is_array($decoded)) {
                $field_values = $decoded;
                error_log("CCC DEBUG: MetaBoxManager decoded field_values: " . json_encode($field_values));
            } else {
                error_log("CCC DEBUG: MetaBoxManager failed to decode field_values");
            }
        } else {
            error_log("CCC DEBUG: MetaBoxManager no ccc_field_values in POST");
        }

        global $wpdb;
        $field_values_table = $wpdb->prefix . 'cc_field_values';
        
        // $wpdb->delete($field_values_table, ['post_id' => $post_id], ['%d']); // Disabled: do not delete all field values on save

        foreach ($field_values as $instance_id => $instance_fields) {
            if (!is_array($instance_fields)) continue;
            
            foreach ($instance_fields as $field_id => $value) {
                $field_id = intval($field_id);
                error_log("CCC DEBUG: MetaBoxManager processing field_id: $field_id, instance_id: $instance_id, value type: " . gettype($value) . ", value: " . json_encode($value));
                
                // For repeater fields, we need to load the field with its children
                // First, get the field to check its type
                $field_obj = Field::find($field_id);
                if (!$field_obj) {
                    error_log("CCC: Field object not found for field_id: $field_id during save.");
                    continue;
                }
                
                error_log("CCC DEBUG: MetaBoxManager field type: " . $field_obj->getType());
                
                // If it's a repeater field, load it with children
                if ($field_obj->getType() === 'repeater') {
                    // Get the component ID for this field
                    $component_id = $field_obj->getComponentId();
                    if ($component_id) {
                        // Load all fields for the component with children using findFieldsTree
                        $all_fields_with_children = Field::findFieldsTree($component_id);
                        error_log("CCC DEBUG: MetaBoxManager loaded " . count($all_fields_with_children) . " fields for component $component_id");
                        // Find our specific field in the loaded fields
                        foreach ($all_fields_with_children as $loaded_field) {
                            error_log("CCC DEBUG: MetaBoxManager checking field " . $loaded_field->getId() . " against target $field_id");
                            if ($loaded_field->getId() == $field_id) {
                                $field_obj = $loaded_field;
                                error_log("CCC DEBUG: MetaBoxManager found and loaded repeater field with " . count($field_obj->getChildren()) . " children");
                                break;
                            }
                        }
                        if (count($field_obj->getChildren()) === 0) {
                            error_log("CCC DEBUG: MetaBoxManager WARNING: Field $field_id still has 0 children after loading");
                        }
                    }
                }

                $value_to_save = $this->sanitizeFieldValue($value, $field_obj);
                error_log("CCC DEBUG: MetaBoxManager sanitized value for field_id: $field_id: " . $value_to_save);
                
                // Save even if empty string or '[]', only skip if null or false
                if ($value_to_save !== null && $value_to_save !== false) {
                    // Check if a row already exists for this post_id, field_id, instance_id
                    $existing_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM $field_values_table WHERE post_id = %d AND field_id = %d AND instance_id = %s",
                        $post_id, $field_id, $instance_id
                    ));
                    if ($existing_id) {
                        // Update existing row
                        $result = $wpdb->update(
                            $field_values_table,
                            [
                                'value' => $value_to_save,
                                'updated_at' => current_time('mysql')
                            ],
                            [
                                'id' => $existing_id
                            ],
                            ['%s', '%s'],
                            ['%d']
                        );
                        error_log("CCC DEBUG:   Update result for field $field_id: $result, error: " . $wpdb->last_error);
                    } else {
                        // Insert new row
                        $result = $wpdb->insert(
                            $field_values_table,
                            [
                                'post_id' => $post_id,
                                'field_id' => $field_id,
                                'instance_id' => $instance_id,
                                'value' => $value_to_save,
                                'created_at' => current_time('mysql'),
                                'updated_at' => current_time('mysql')
                            ],
                            ['%d', '%d', '%s', '%s', '%s', '%s']
                        );
                        error_log("CCC DEBUG:   Insert result for field $field_id: $result, error: " . $wpdb->last_error);
                    }
                    if ($result === false) {
                        error_log("CCC: Failed to save field value for field_id: $field_id, instance: $instance_id, post_id: $post_id, error: " . $wpdb->last_error);
                    }
                }
            }
        }

        // Clean up orphaned field values for removed component instances
        $current_instance_ids = array_map(function($comp) { return $comp['instance_id']; }, $components);
        global $wpdb;
        $field_values_table = $wpdb->prefix . 'cc_field_values';
        if (!empty($current_instance_ids)) {
            $placeholders = implode(',', array_fill(0, count($current_instance_ids), '%s'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $field_values_table WHERE post_id = %d AND instance_id NOT IN ($placeholders)",
                array_merge([$post_id], $current_instance_ids)
            ));
        } else {
            // If no components left, delete all field values for this post
            $wpdb->delete($field_values_table, ['post_id' => $post_id]);
        }
    }

    private function sanitizeFieldValue($value, $field_obj) {
        $field_type = $field_obj->getType();
        
        // For file fields, don't use wp_unslash as it can break the object structure
        if ($field_type === 'file') {
            $value_to_save = $value;
        } else {
            $value_to_save = wp_unslash($value);
        }
        
        switch ($field_type) {
            case 'repeater':
                error_log("CCC DEBUG: MetaBoxManager sanitizing repeater field value: " . $value_to_save);
                $decoded_value = json_decode($value_to_save, true);
                error_log("CCC DEBUG: MetaBoxManager decoded repeater value: " . json_encode($decoded_value));
                error_log("CCC DEBUG: MetaBoxManager field config: " . $field_obj->getConfig());
                error_log("CCC DEBUG: MetaBoxManager field children count: " . count($field_obj->getChildren()));
                $sanitized_decoded_value = $this->sanitizeRepeaterData($decoded_value, $field_obj->getConfig(), $field_obj);
                error_log("CCC DEBUG: MetaBoxManager sanitized repeater value: " . json_encode($sanitized_decoded_value));
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
                
            case 'video':
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
                    if (is_array($decoded_value)) {
                        // Sanitize video data
                        $sanitized_video = [];
                        if (isset($decoded_value['url'])) {
                            $sanitized_video['url'] = esc_url_raw($decoded_value['url']);
                        }
                        if (isset($decoded_value['type'])) {
                            $sanitized_video['type'] = sanitize_text_field($decoded_value['type']);
                        }
                        if (isset($decoded_value['title'])) {
                            $sanitized_video['title'] = sanitize_text_field($decoded_value['title']);
                        }
                        if (isset($decoded_value['description'])) {
                            $sanitized_video['description'] = sanitize_textarea_field($decoded_value['description']);
                        }
                        return json_encode($sanitized_video);
                    }
                    return json_encode($decoded_value);
                }
                
            case 'color':
                // Handle enhanced color data structure
                if (is_string($value_to_save) && $value_to_save !== '' && $value_to_save[0] === '{') {
                    // JSON color data structure
                    $decoded_color = json_decode($value_to_save, true);
                    if (is_array($decoded_color)) {
                        $sanitized_color = [];
                        if (isset($decoded_color['main'])) {
                            $sanitized_color['main'] = sanitize_hex_color($decoded_color['main']) ?: sanitize_text_field($decoded_color['main']);
                        }
                        if (isset($decoded_color['adjusted'])) {
                            $sanitized_color['adjusted'] = sanitize_hex_color($decoded_color['adjusted']) ?: sanitize_text_field($decoded_color['adjusted']);
                        }
                        if (isset($decoded_color['hover'])) {
                            $sanitized_color['hover'] = sanitize_hex_color($decoded_color['hover']) ?: sanitize_text_field($decoded_color['hover']);
                        }
                        return json_encode($sanitized_color);
                    }
                }
                // Fallback to simple hex color
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
                
            case 'text':
            case 'textarea':
                return sanitize_textarea_field($value_to_save);
                
            case 'email':
                return sanitize_email($value_to_save);
                
            case 'link':
                // Link fields store JSON data, preserve it
                return $value_to_save;
                
            case 'wysiwyg':
                return wp_kses_post($value_to_save);
                
            case 'oembed':
                error_log("CCC DEBUG: MetaBoxManager sanitizing oembed field value: " . $value_to_save);
                // Allow iframe tags and common attributes for oembed fields
                $allowed_html = [
                    'iframe' => [
                        'src' => true,
                        'width' => true,
                        'height' => true,
                        'frameborder' => true,
                        'allowfullscreen' => true,
                        'loading' => true,
                        'referrerpolicy' => true,
                        'title' => true,
                        'style' => true,
                        'class' => true,
                        'id' => true
                    ]
                ];
                $sanitized_value = wp_kses($value_to_save, $allowed_html);
                error_log("CCC DEBUG: MetaBoxManager sanitized oembed value: " . $sanitized_value);
                return $sanitized_value;
                
            case 'relationship':
                error_log("CCC DEBUG: MetaBoxManager sanitizing relationship field value: " . $value_to_save);
                // Sanitize relationship field (comma-separated post IDs)
                if (empty($value_to_save)) {
                    return '';
                }
                $post_ids = explode(',', $value_to_save);
                $sanitized_ids = [];
                foreach ($post_ids as $post_id) {
                    $post_id = intval(trim($post_id));
                    if ($post_id > 0 && get_post($post_id)) {
                        $sanitized_ids[] = $post_id;
                    }
                }
                $sanitized_value = implode(',', $sanitized_ids);
                error_log("CCC DEBUG: MetaBoxManager sanitized relationship value: " . $sanitized_value);
                return $sanitized_value;
                
            case 'number':
                // Handle number field with min/max validation
                if (empty($value_to_save)) {
                    return '';
                }
                
                // Check if it's a valid number
                if (!is_numeric($value_to_save)) {
                    error_log("CCC DEBUG: MetaBoxManager number field received non-numeric value: " . $value_to_save);
                    return '';
                }
                
                $num = floatval($value_to_save);
                $field_config = $field_obj->getConfig();
                
                // Parse config if it's a string
                if (is_string($field_config)) {
                    try {
                        $field_config = json_decode($field_config, true);
                    } catch (Exception $e) {
                        $field_config = [];
                    }
                }
                
                // Apply min/max constraints
                if (isset($field_config['min_value']) && $field_config['min_value'] !== null && $num < $field_config['min_value']) {
                    error_log("CCC DEBUG: MetaBoxManager number field value $num is below minimum {$field_config['min_value']}, adjusting");
                    $num = $field_config['min_value'];
                }
                
                if (isset($field_config['max_value']) && $field_config['max_value'] !== null && $num > $field_config['max_value']) {
                    error_log("CCC DEBUG: MetaBoxManager number field value $num is above maximum {$field_config['max_value']}, adjusting");
                    $num = $field_config['max_value'];
                }
                
                error_log("CCC DEBUG: MetaBoxManager sanitized number field value: " . $num);
                return $num;
                
            case 'range':
                // Handle range field with min/max validation (similar to number field)
                if (empty($value_to_save)) {
                    return '';
                }
                
                // Check if it's a valid number
                if (!is_numeric($value_to_save)) {
                    error_log("CCC DEBUG: MetaBoxManager range field received non-numeric value: " . $value_to_save);
                    return '';
                }
                
                $num = floatval($value_to_save);
                $field_config = $field_obj->getConfig();
                
                // Parse config if it's a string
                if (is_string($field_config)) {
                    try {
                        $field_config = json_decode($field_config, true);
                    } catch (Exception $e) {
                        $field_config = [];
                    }
                }
                
                // Apply min/max constraints
                if (isset($field_config['min_value']) && $field_config['min_value'] !== null && $num < $field_config['min_value']) {
                    error_log("CCC DEBUG: MetaBoxManager range field value $num is below minimum {$field_config['min_value']}, adjusting");
                    $num = $field_config['min_value'];
                }
                
                if (isset($field_config['max_value']) && $field_config['max_value'] !== null && $num > $field_config['max_value']) {
                    error_log("CCC DEBUG: MetaBoxManager range field value $num is above maximum {$field_config['max_value']}, adjusting");
                    $num = $field_config['max_value'];
                }
                
                error_log("CCC DEBUG: MetaBoxManager sanitized range field value: " . $num);
                return $num;
                
            case 'file':
                error_log("CCC DEBUG: MetaBoxManager file field received value: " . json_encode($value_to_save));
                error_log("CCC DEBUG: MetaBoxManager file field value type: " . gettype($value_to_save));
                error_log("CCC DEBUG: MetaBoxManager file field is_array: " . (is_array($value_to_save) ? 'true' : 'false'));

                // Check if this is a single file object (has temp_id or id property)
                if (is_array($value_to_save) && (isset($value_to_save['temp_id']) || isset($value_to_save['id']))) {
                    // This is a single file object, not an array of files
                    error_log("CCC DEBUG: MetaBoxManager treating as single file object");
                    $sanitized_file = $this->sanitizeSingleFile($value_to_save);
                    error_log("CCC DEBUG: MetaBoxManager sanitized single file: " . $sanitized_file);
                    return $sanitized_file;
                }

                // If it's an array (multiple files), sanitize each one
                if (is_array($value_to_save)) {
                    $sanitized_files = [];
                    foreach ($value_to_save as $file) {
                        $sanitized_file = $this->sanitizeSingleFile($file);
                        if ($sanitized_file !== '') {
                            $sanitized_files[] = $sanitized_file;
                        }
                    }
                    error_log("CCC DEBUG: MetaBoxManager sanitized file field value: " . json_encode($sanitized_files));
                    return json_encode($sanitized_files); // JSON encode array for storage
                }

                // Single file (fallback for non-array single values, though React should send array/object)
                $sanitized_file = $this->sanitizeSingleFile($value_to_save);
                error_log("CCC DEBUG: MetaBoxManager sanitized file field value: " . $sanitized_file);
                return $sanitized_file;

            case 'taxonomy_term':
                error_log("CCC DEBUG: MetaBoxManager taxonomy_term field received value: " . json_encode($value_to_save));
                if (is_array($value_to_save)) {
                    $sanitized_terms = [];
                    foreach ($value_to_save as $term) {
                        if (isset($term['term_id']) && is_numeric($term['term_id'])) {
                            $sanitized_terms[] = [
                                'term_id' => intval($term['term_id']),
                                'name' => sanitize_text_field($term['name'] ?? ''),
                                'slug' => sanitize_text_field($term['slug'] ?? ''),
                                'taxonomy' => sanitize_text_field($term['taxonomy'] ?? '')
                            ];
                        }
                    }
                    error_log("CCC DEBUG: MetaBoxManager sanitized taxonomy_term field value: " . json_encode($sanitized_terms));
                    return json_encode($sanitized_terms);
                }
                return '';
                
            case 'user':
                error_log("CCC DEBUG: MetaBoxManager user field received value: " . json_encode($value_to_save));
                if (is_array($value_to_save)) {
                    // Multiple user selection - sanitize each user ID
                    $sanitized_user_ids = [];
                    foreach ($value_to_save as $user_id) {
                        $user_id = intval(trim($user_id));
                        if ($user_id > 0 && get_user_by('ID', $user_id)) {
                            $sanitized_user_ids[] = $user_id;
                        }
                    }
                    error_log("CCC DEBUG: MetaBoxManager sanitized user field value: " . json_encode($sanitized_user_ids));
                    return json_encode($sanitized_user_ids);
                } else {
                    // Single user selection - sanitize as single user ID
                    $user_id = intval($value_to_save);
                    if ($user_id > 0 && get_user_by('ID', $user_id)) {
                        error_log("CCC DEBUG: MetaBoxManager sanitized single user field value: " . $user_id);
                        return $user_id;
                    }
                    error_log("CCC DEBUG: MetaBoxManager invalid user ID: " . $value_to_save);
                    return '';
                }
                
        default:
            return wp_kses_post($value_to_save);
        }
    }
    
    private function sanitizeSingleFile($file) {
        if (empty($file)) {
            return '';
        }
        
        error_log("CCC DEBUG: sanitizeSingleFile received: " . json_encode($file));
        error_log("CCC DEBUG: sanitizeSingleFile data type: " . gettype($file));
        error_log("CCC DEBUG: sanitizeSingleFile is_array: " . (is_array($file) ? 'true' : 'false'));
        error_log("CCC DEBUG: sanitizeSingleFile is_object: " . (is_object($file) ? 'true' : 'false'));
        
        // If it's already a file ID, validate it exists
        if (is_numeric($file)) {
            $attachment = get_post($file);
            if ($attachment) {
                error_log("CCC DEBUG: File ID validated: " . $file);
                return $file;
            }
            error_log("CCC DEBUG: File ID not found: " . $file);
            return '';
        }
        
        // If it's a URL, validate it's a valid attachment URL
        if (is_string($file) && filter_var($file, FILTER_VALIDATE_URL)) {
            $attachment_id = attachment_url_to_postid($file);
            if ($attachment_id) {
                error_log("CCC DEBUG: URL converted to ID: " . $attachment_id);
                return $attachment_id;
            }
            error_log("CCC DEBUG: URL not found in attachments: " . $file);
            return '';
        }
        

        

        
        // If it's a temporary uploaded file (has temp_id), store the file data
        if (is_array($file) && isset($file['temp_id']) && isset($file['is_temp']) && $file['is_temp'] === true) {
            error_log("CCC DEBUG: Temporary file detected, storing file data: " . json_encode($file));
            // For temporary files, we'll store the file information
            // In a real implementation, you'd upload the file to the server first
            $result = json_encode([
                'temp_id' => $file['temp_id'],
                'name' => $file['name'] ?? '',
                'type' => $file['type'] ?? '',
                'size' => $file['size'] ?? 0,
                'is_temp' => true
            ]);
            error_log("CCC DEBUG: Temporary file result: " . $result);
            return $result;
        }

        // If it's a media library file (has id and is_media_library), store the file data
        if (is_array($file) && isset($file['id']) && isset($file['is_media_library']) && $file['is_media_library'] === true) {
            error_log("CCC DEBUG: Media library file detected, storing file data: " . json_encode($file));
            $result = json_encode([
                'id' => $file['id'],
                'url' => $file['url'] ?? '',
                'type' => $file['type'] ?? '',
                'name' => $file['name'] ?? '',
                'size' => $file['size'] ?? 0,
                'size_formatted' => $file['size_formatted'] ?? '',
                'thumbnail' => $file['thumbnail'] ?? '',
                'is_media_library' => true
            ]);
            error_log("CCC DEBUG: Media library file result: " . $result);
            return $result;
        }
        // If it's an object with id and is_media_library properties
        if (is_object($file) && isset($file->id) && isset($file->is_media_library) && $file->is_media_library === true) {
            error_log("CCC DEBUG: Media library file object detected, storing file data: " . json_encode($file));
            return json_encode([
                'id' => $file->id,
                'url' => $file->url ?? '',
                'type' => $file->type ?? '',
                'name' => $file->name ?? '',
                'size' => $file->size ?? 0,
                'size_formatted' => $file->size_formatted ?? '',
                'thumbnail' => $file->thumbnail ?? '',
                'is_media_library' => true
            ]);
        }
        
        // If it's an object with temp_id property
        if (is_object($file) && isset($file->temp_id) && isset($file->is_temp) && $file->is_temp === true) {
            error_log("CCC DEBUG: Temporary file object detected, storing file data: " . json_encode($file));
            return json_encode([
                'temp_id' => $file->temp_id,
                'name' => $file->name ?? '',
                'type' => $file->type ?? '',
                'size' => $file->size ?? 0,
                'is_temp' => true
            ]);
        }
        
        error_log("CCC DEBUG: File data not recognized: " . json_encode($file));
        return '';
    }

    private function sanitizeRepeaterData($data, $config_json, $field_obj = null) {
        error_log("CCC DEBUG: MetaBoxManager sanitizeRepeaterData called with data: " . json_encode($data));
        error_log("CCC DEBUG: MetaBoxManager config_json: " . $config_json);
        error_log("CCC DEBUG: MetaBoxManager field_obj: " . ($field_obj ? 'provided' : 'null'));
        if ($field_obj) {
            error_log("CCC DEBUG: MetaBoxManager field_obj children count: " . count($field_obj->getChildren()));
        }
        
        error_log("CCC DEBUG: MetaBoxManager starting sanitizeRepeaterData method");
        error_log("CCC DEBUG: MetaBoxManager data type: " . gettype($data));
        error_log("CCC DEBUG: MetaBoxManager is_array(data): " . (is_array($data) ? 'true' : 'false'));
        
        // Handle simple array format (what our RepeaterField sends) - CHECK THIS FIRST
        if (is_array($data) && !isset($data['data']) && !isset($data['state'])) {
            error_log("CCC DEBUG: MetaBoxManager handling simple array format for repeater");
            error_log("CCC DEBUG: MetaBoxManager data array length: " . count($data));
            $sanitized_data = [];
            $config = json_decode($config_json, true);
            $nested_field_definitions = $config['nested_fields'] ?? [];
            error_log("CCC DEBUG: MetaBoxManager config: " . json_encode($config));
            error_log("CCC DEBUG: MetaBoxManager nested_field_definitions: " . json_encode($nested_field_definitions));
            error_log("CCC DEBUG: MetaBoxManager empty(nested_field_definitions): " . (empty($nested_field_definitions) ? 'true' : 'false'));
            error_log("CCC DEBUG: MetaBoxManager field_obj provided: " . ($field_obj ? 'true' : 'false'));

            // If no nested_fields in config, try to get them from the field object's children
            if (empty($nested_field_definitions) && $field_obj) {
                error_log("CCC DEBUG: MetaBoxManager no nested_fields in config, trying to get from field object children");
                    
                $children = $field_obj->getChildren();
                error_log("CCC DEBUG: MetaBoxManager field object has " . count($children) . " children");
                
                if (!empty($children)) {
                    // Convert field children to nested_field_definitions format
                    $nested_field_definitions = [];
                    foreach ($children as $child_field) {
                        $nested_field_definitions[] = [
                            'name' => $child_field->getName(),
                            'type' => $child_field->getType(),
                            'config' => json_decode($child_field->getConfig(), true) ?: []
                        ];
                    }
                    error_log("CCC DEBUG: MetaBoxManager converted children to nested fields: " . json_encode($nested_field_definitions));
                    error_log("CCC DEBUG: MetaBoxManager will now process with nested field definitions");
                    error_log("CCC DEBUG: MetaBoxManager entering nested field definitions processing branch");
                    
                    // Process with the converted nested field definitions
                    foreach ($data as $item) {
                        if (!is_array($item)) continue;
                        
                        $sanitized_item = [];
                        foreach ($nested_field_definitions as $nested_field_def) {
                            $field_name = $nested_field_def['name'];
                            $field_type = $nested_field_def['type'];
                            $nested_field_config = $nested_field_def['config'] ?? [];

                            if (isset($item[$field_name])) {
                                $value = $item[$field_name];
                                
                                switch ($field_type) {
                                    case 'text':
                                    case 'textarea':
                                        $sanitized_item[$field_name] = sanitize_textarea_field($value);
                                        break;
                                        
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
                                        
                                    case 'video':
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
                                            if (is_array($decoded_value)) {
                                                $sanitized_video = [];
                                                if (isset($decoded_value['url'])) {
                                                    $sanitized_video['url'] = esc_url_raw($decoded_value['url']);
                                                }
                                                if (isset($decoded_value['type'])) {
                                                    $sanitized_video['type'] = sanitize_text_field($decoded_value['type']);
                                                }
                                                if (isset($decoded_value['title'])) {
                                                    $sanitized_video['title'] = sanitize_text_field($decoded_value['title']);
                                                }
                                                if (isset($decoded_value['description'])) {
                                                    $sanitized_video['description'] = sanitize_textarea_field($decoded_value['description']);
                                                }
                                                $sanitized_item[$field_name] = json_encode($sanitized_video);
                                            } else {
                                                $sanitized_item[$field_name] = json_encode($decoded_value);
                                            }
                                        }
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
                        
                        // Preserve the _hidden property if it exists
                        if (isset($item['_hidden'])) {
                            $sanitized_item['_hidden'] = (bool) $item['_hidden'];
                        }
                        
                        $sanitized_data[] = $sanitized_item;
                    }
                    
                    error_log("CCC DEBUG: MetaBoxManager returning sanitized array with nested field definitions: " . json_encode($sanitized_data));
                    return $sanitized_data;
                } else {
                    // Fallback to basic sanitization if no children found
                    error_log("CCC DEBUG: MetaBoxManager no children found in field object, using basic sanitization");
                    error_log("CCC DEBUG: MetaBoxManager entering basic sanitization branch");
                    error_log("CCC DEBUG: MetaBoxManager will process with basic sanitization");
                    error_log("CCC DEBUG: MetaBoxManager field_obj type: " . get_class($field_obj));
                    error_log("CCC DEBUG: MetaBoxManager field_obj ID: " . $field_obj->getId());
                    error_log("CCC DEBUG: MetaBoxManager field_obj children property: " . json_encode($field_obj->getChildren()));
                    
                    foreach ($data as $item) {
                        if (!is_array($item)) continue;
                        
                        $sanitized_item = [];
                        foreach ($item as $field_name => $value) {
                            // Basic sanitization based on common field types
                            if (strpos($field_name, 'url') !== false) {
                                $sanitized_item[$field_name] = esc_url_raw($value);
                            } elseif (strpos($field_name, 'photo') !== false || strpos($field_name, 'image') !== false) {
                                $sanitized_item[$field_name] = esc_url_raw($value);
                            } else {
                                $sanitized_item[$field_name] = sanitize_text_field($value);
                            }
                        }
                        
                        // Preserve the _hidden property if it exists
                        if (isset($item['_hidden'])) {
                            $sanitized_item['_hidden'] = (bool) $item['_hidden'];
                        }
                        
                        $sanitized_data[] = $sanitized_item;
                    }
                    
                    error_log("CCC DEBUG: MetaBoxManager returning sanitized simple array: " . json_encode($sanitized_data));
                    return $sanitized_data;
                }
            } else {
                error_log("CCC DEBUG: MetaBoxManager nested_field_definitions is not empty, but no field_obj provided or no children found");
                error_log("CCC DEBUG: MetaBoxManager falling through to unknown format");
            }
        }
        
        // Check if this is the new format with data and state
        if (is_array($data) && isset($data['data']) && isset($data['state'])) {
            error_log("CCC DEBUG: MetaBoxManager entering data/state format branch");
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
                                
                            case 'video':
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
                                    if (is_array($decoded_value)) {
                                        // Sanitize video data
                                        $sanitized_video = [];
                                        if (isset($decoded_value['url'])) {
                                            $sanitized_video['url'] = esc_url_raw($decoded_value['url']);
                                        }
                                        if (isset($decoded_value['type'])) {
                                            $sanitized_video['type'] = sanitize_text_field($decoded_value['type']);
                                        }
                                        if (isset($decoded_value['title'])) {
                                            $sanitized_video['title'] = sanitize_text_field($decoded_value['title']);
                                        }
                                        if (isset($decoded_value['description'])) {
                                            $sanitized_video['description'] = sanitize_textarea_field($decoded_value['description']);
                                        }
                                        $sanitized_item[$field_name] = json_encode($sanitized_video);
                                    } else {
                                        $sanitized_item[$field_name] = json_encode($decoded_value);
                                    }
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
            error_log("CCC DEBUG: MetaBoxManager entering legacy format branch");
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
                            case 'text':
                            case 'textarea':
                                $sanitized_item[$field_name] = sanitize_textarea_field($value);
                                break;
                                
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
                                
                            case 'video':
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
                                    if (is_array($decoded_value)) {
                                        // Sanitize video data
                                        $sanitized_video = [];
                                        if (isset($decoded_value['url'])) {
                                            $sanitized_video['url'] = esc_url_raw($decoded_value['url']);
                                        }
                                        if (isset($decoded_value['type'])) {
                                            $sanitized_video['type'] = sanitize_text_field($decoded_value['type']);
                                        }
                                        if (isset($decoded_value['title'])) {
                                            $sanitized_video['title'] = sanitize_text_field($decoded_value['title']);
                                        }
                                        if (isset($decoded_value['description'])) {
                                            $sanitized_video['description'] = sanitize_textarea_field($decoded_value['description']);
                                        }
                                        $sanitized_item[$field_name] = json_encode($sanitized_video);
                                    } else {
                                        $sanitized_item[$field_name] = json_encode($decoded_value);
                                    }
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
        
        error_log("CCC DEBUG: MetaBoxManager unknown repeater data format, returning as-is");
        return $data;
    }

    private function getFieldValues($components, $post_id) {
        global $wpdb;
        $field_values_table = $wpdb->prefix . 'cc_field_values';
        $fields_table = $wpdb->prefix . 'cc_fields';
        
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
                } elseif ($field_type === 'video') {
                    // Video fields are single values (URL or JSON)
                    $field_values[$instance_id][$field_id] = $raw_value;
                } else {
                    $field_values[$instance_id][$field_id] = $raw_value;
                }
            } else {
                $field_values[$instance_id][$field_id] = $raw_value;
            }
        }
        
        return $field_values;
    }

    // Old metabox styles removed - now using React with Tailwind CSS

    // Old metabox scripts removed - now using React components

}
