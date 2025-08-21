<?php
namespace CCC\Models;

defined('ABSPATH') || exit;

class FieldValue {
    // This class primarily provides static methods for database interaction
    // No instance properties or constructor are needed if only static methods are used.

    /**
     * Retrieves a single field value from the database.
     *
     * @param int $field_id The ID of the field.
     * @param int $post_id The ID of the post.
     * @param string $instance_id The unique instance ID of the component.
     * @return string The field value, or an empty string if not found.
     */
    public static function getValue($field_id, $post_id, $instance_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cc_field_values';
        
        error_log("CCC FieldValue::getValue - Looking for field_id: $field_id, post_id: $post_id, instance_id: $instance_id");
        
        $query = $wpdb->prepare(
            "SELECT value FROM {$table_name} WHERE field_id = %d AND post_id = %d AND instance_id = %s ORDER BY id DESC LIMIT 1",
            $field_id,
            $post_id,
            $instance_id
        );
        
        error_log("CCC FieldValue::getValue - SQL Query: $query");
        
        $value = $wpdb->get_var($query);
        
        error_log("CCC FieldValue::getValue - Query result: " . ($value ?: 'NULL/empty'));
        
        // Also check if there are any values at all for this field/post combination
        $all_values = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE field_id = %d AND post_id = %d",
            $field_id,
            $post_id
        ), ARRAY_A);
        
        error_log("CCC FieldValue::getValue - All values for field $field_id on post $post_id: " . json_encode($all_values));
        
        return $value ?: '';
    }

    /**
     * Saves a single field value to the database.
     * If a value for the given field, post, and instance already exists, it will be updated.
     * Otherwise, a new record will be inserted.
     *
     * @param int $field_id The ID of the field.
     * @param int $post_id The ID of the post.
     * @param string $instance_id The unique instance ID of the component.
     * @param mixed $value The value to save. Can be a string or an array (which will be JSON encoded).
     * @return bool True on success, false on failure.
     */
    public static function saveValue($field_id, $post_id, $instance_id, $value) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cc_field_values';

        // Get field type to determine how to handle the value
        $field = Field::find($field_id);
        $field_type = $field ? $field->getType() : '';
        
        error_log("CCC FieldValue::saveValue - Field ID: $field_id, Type: $field_type, Value: " . print_r($value, true));

        // Handle array values (e.g., from checkboxes, repeaters, multiple user selections) by JSON encoding them
        if (is_array($value)) {
            $value = json_encode($value);
            error_log("CCC FieldValue::saveValue - Array value JSON encoded: $value");
        } else {
            // For field types that store JSON data, preserve the JSON string
            $json_field_types = ['link', 'relationship', 'repeater', 'color', 'video'];
            
            if (in_array($field_type, $json_field_types) && is_string($value)) {
                // Validate if it's valid JSON, if so preserve it, otherwise treat as regular text
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Valid JSON, keep as is
                    error_log("CCC FieldValue::saveValue - Valid JSON for field type $field_type, preserving: $value");
                    $value = $value;
                } else {
                    // Not valid JSON, sanitize as text
                    error_log("CCC FieldValue::saveValue - Invalid JSON for field type $field_type, sanitizing as text: $value");
                    $value = sanitize_text_field($value);
                }
            } else {
                // Sanitize scalar values for non-JSON field types
                error_log("CCC FieldValue::saveValue - Non-JSON field type $field_type, sanitizing as text: $value");
                
                // Use appropriate sanitization based on field type
                if ($field_type === 'text' || $field_type === 'textarea') {
                    $value = sanitize_textarea_field($value);
                } else {
                    $value = sanitize_text_field($value);
                }
            }
        }
        
        // Special handling for user fields
        if ($field_type === 'user') {
            error_log("CCC FieldValue::saveValue - User field detected, final value: $value");
        }

        // Check if a value already exists for this field, post, and instance
        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE field_id = %d AND post_id = %d AND instance_id = %s",
            $field_id,
            $post_id,
            $instance_id
        ));

        if ($existing_id) {
            // Update existing value
            $result = $wpdb->update(
                $table_name,
                ['value' => $value, 'updated_at' => current_time('mysql')],
                ['id' => $existing_id],
                ['%s', '%s'],
                ['%d']
            );
            if ($result === false) {
                error_log("CCC FieldValue: DB update error: " . $wpdb->last_error);
            }
            // Log the updated row
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $existing_id
            ), ARRAY_A);
            error_log("CCC FieldValue: Updated row: " . print_r($row, true));
        } else {
            // Insert new value
            error_log("CCC FieldValue: About to insert row into $table_name: " . print_r([
                'field_id' => $field_id,
                'post_id' => $post_id,
                'instance_id' => $instance_id,
                'value' => $value,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ], true));
            $result = $wpdb->insert(
                $table_name,
                [
                    'field_id' => $field_id,
                    'post_id' => $post_id,
                    'instance_id' => $instance_id,
                    'value' => $value,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['%d', '%d', '%s', '%s', '%s', '%s']
            );
            error_log("CCC FieldValue: Insert result: $result, error: " . $wpdb->last_error);
            if ($result === false) {
                error_log("CCC FieldValue: DB insert error: " . $wpdb->last_error);
            }
            // Log the inserted row
            $insert_id = $wpdb->insert_id;
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $insert_id
            ), ARRAY_A);
            error_log("CCC FieldValue: Inserted row: " . print_r($row, true));
            // Log the total row count in the table
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            error_log("CCC FieldValue: Total rows in table after insert: $count");
        }

        return $result !== false;
    }

    /**
     * Saves multiple field values for a given post.
     * This is typically used when saving all fields from a meta box submission.
     *
     * @param int $post_id The ID of the post.
     * @param array $field_values An associative array of field values, typically from $_POST['ccc_field_values'].
     *                            Expected format: [component_id => [instance_id => [field_name => value]]]
     * @return bool True on success, false on failure.
     */
    public static function saveMultiple($post_id, $field_values) {
        global $wpdb;
        // Ensure the Field model is loaded to use Field::findByNameAndComponent
        if (!class_exists('CCC\Models\Field')) {
            require_once plugin_dir_path(__FILE__) . 'Field.php'; // Adjust path if necessary
        }

        $success = true;
        foreach ($field_values as $component_id => $instances) {
            foreach ($instances as $instance_id => $fields_data) {
                foreach ($fields_data as $field_name => $value) {
                    // Find the field ID by name and component ID
                    $field_obj = Field::findByNameAndComponent($field_name, $component_id);
                    if ($field_obj) {
                        if (!self::saveValue($field_obj->getId(), $post_id, $instance_id, $value)) {
                            $success = false;
                            error_log("CCC FieldValue: Failed to save value for field '{$field_name}' (ID: {$field_obj->getId()}) on post {$post_id} instance {$instance_id}");
                        }
                    } else {
                        error_log("CCC FieldValue: Field '{$field_name}' not found for component {$component_id}.");
                        $success = false;
                    }
                }
            }
        }
        return $success;
    }
}
