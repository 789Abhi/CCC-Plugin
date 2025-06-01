<?php
namespace CCC\Models;

defined('ABSPATH') || exit;

/**
 * Represents a Field in the Custom Craft Component system.
 * Handles database operations for fields.
 */
class Field {
    protected $id;
    protected $component_id;
    protected $label;
    protected $name;
    protected $type;
    protected $config; // JSON string for field-specific configuration
    protected $field_order;
    protected $required;
    protected $placeholder; // Added placeholder property
    protected $created_at;

    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Find a field by its ID.
     *
     * @param int $id The ID of the field.
     * @return Field|null The Field object if found, otherwise null.
     */
    public static function find($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cc_fields';
        $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id), ARRAY_A);
        if ($result) {
            return new self($result);
        }
        return null;
    }

    /**
     * Find all fields associated with a specific component.
     *
     * @param int $component_id The ID of the component.
     * @return Field[] An array of Field objects.
     */
    public static function findByComponent($component_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cc_fields';
        $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE component_id = %d ORDER BY field_order ASC, created_at ASC", $component_id), ARRAY_A);
        return array_map(function($data) {
            return new self($data);
        }, $results);
    }

    /**
     * Save the field to the database (insert or update).
     *
     * @return bool True on success, false on failure.
     */
    public function save() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cc_fields';
        $data = [
            'component_id' => $this->component_id,
            'label' => $this->label,
            'name' => $this->name,
            'type' => $this->type,
            'config' => $this->config,
            'field_order' => $this->field_order,
            'required' => $this->required,
            'placeholder' => $this->placeholder, // Include placeholder in save data
        ];
        $format = ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s'];

        if ($this->id) {
            // Update existing record
            $result = $wpdb->update($table_name, $data, ['id' => $this->id], $format, ['%d']);
            return $result !== false;
        } else {
            // Insert new record
            $data['created_at'] = current_time('mysql');
            $format[] = '%s';
            $result = $wpdb->insert($table_name, $data, $format);
            if ($result) {
                $this->id = $wpdb->insert_id;
            }
            return $result !== false;
        }
    }

    /**
     * Delete the field from the database.
     * Also deletes associated field values.
     *
     * @return bool True on success, false on failure.
     */
    public function delete() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cc_fields';
        // Delete associated field values first
        $wpdb->delete($wpdb->prefix . 'cc_field_values', ['field_id' => $this->id], ['%d']);
        return $wpdb->delete($table_name, ['id' => $this->id], ['%d']);
    }

    // Getters
    public function getId() { return $this->id; }
    public function getComponentId() { return $this->component_id; }
    public function getLabel() { return $this->label; }
    public function getName() { return $this->name; }
    public function getType() { return $this->type; }
    public function getConfig() { return $this->config; }
    public function getFieldOrder() { return $this->field_order; }
    public function getRequired() { return $this->required; }
    public function getPlaceholder() { return $this->placeholder; }
    public function getCreatedAt() { return $this->created_at; }

    // Setters
    public function setLabel($label) { $this->label = $label; }
    public function setRequired($required) { $this->required = $required; }
    public function setPlaceholder($placeholder) { $this->placeholder = $placeholder; }
    public function setConfig($config) { $this->config = $config; }
    public function setType($type) { $this->type = $type; } // Added setType method
}
