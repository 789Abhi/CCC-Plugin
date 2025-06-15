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
        
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT value FROM {$table_name} WHERE field_id = %d AND post_id = %d AND instance_id = %s ORDER BY id DESC LIMIT 1",
            $field_id,
            $post_id,
            $instance_id
        ));
        
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

        // Handle array values (e.g., from checkboxes, repeaters) by JSON encoding them
        if (is_array($value)) {
            $value = json_encode($value);
        } else {
            // Sanitize scalar values
            $value = sanitize_text_field($value);
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
        } else {
            // Insert new value
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
