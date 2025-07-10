<?php
namespace CCC\Models;

defined('ABSPATH') || exit;

class Field {
    private $id;
    private $component_id;
    private $label;
    private $name;
    private $handle;
    private $type;
    private $required;
    private $placeholder;
    private $config;
    private $field_order;
    private $created_at;
    private $children;

    public function __construct($data = []) {
        $this->id = $data['id'] ?? null;
        $this->component_id = $data['component_id'] ?? null;
        $this->label = $data['label'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->handle = $data['handle'] ?? sanitize_title($this->name);
        $this->type = $data['type'] ?? '';
        $this->required = $data['required'] ?? false;
        $this->placeholder = $data['placeholder'] ?? '';
        $this->config = $data['config'] ?? '';
        $this->field_order = $data['field_order'] ?? 0;
        $this->created_at = $data['created_at'] ?? null;
        $this->children = $data['children'] ?? [];
    }

    public static function find($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'cc_fields';
        $data = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id),
            ARRAY_A
        );
        return $data ? new self($data) : null;
    }

    public static function findByComponent($component_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'cc_fields';
        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table WHERE component_id = %d ORDER BY field_order, created_at", $component_id),
            ARRAY_A
        );
        return array_map(function($data) {
            return new self($data);
        }, $results);
    }
    
    public static function findByNameAndComponent($name, $component_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cc_fields';
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE name = %s AND component_id = %d",
                $name,
                $component_id
            ),
            ARRAY_A
        );
        
        return $result ? new self($result) : null;
    }

    public function save() {
        global $wpdb;
        $table = $wpdb->prefix . 'cc_fields';

        $data = [
            'component_id' => $this->component_id,
            'label' => $this->label,
            'name' => $this->name,
            'handle' => $this->handle,
            'type' => $this->type,
            'required' => $this->required ? 1 : 0,
            'placeholder' => $this->placeholder,
            'config' => $this->config,
            'field_order' => $this->field_order
        ];

        $format = [
            '%d', // component_id
            '%s', // label
            '%s', // name
            '%s', // handle
            '%s', // type
            '%d', // required
            '%s', // placeholder
            '%s', // config
            '%d'  // field_order
        ];

        if ($this->id) {
            $result = $wpdb->update(
                $table,
                $data,
                ['id' => $this->id],
                $format,
                ['%d']
            );
        } else {
            $data['created_at'] = current_time('mysql');
            $format[] = '%s'; // created_at
            $result = $wpdb->insert($table, $data, $format);
            if ($result !== false) {
                $this->id = $wpdb->insert_id;
            }
        }

        return $result !== false;
    }

    public function delete() {
        if (!$this->id) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cc_fields';
        $result = $wpdb->delete($table, ['id' => $this->id], ['%d']);
        return $result !== false;
    }

    // Getters
    public function getId() { return $this->id; }
    public function getComponentId() { return $this->component_id; }
    public function getLabel() { return $this->label; }
    public function getName() { return $this->name; }
    public function getHandle() { return $this->handle; }
    public function getType() { return $this->type; }
    public function getRequired() { return (bool) $this->required; }
    public function getPlaceholder() { return $this->placeholder; }
    public function getConfig() { return $this->config; }
    public function getFieldOrder() { return $this->field_order; }
    public function getCreatedAt() { return $this->created_at; }
    public function getChildren() { return $this->children; }

    // Setters
    public function setComponentId($id) { $this->component_id = $id; }
    public function setLabel($label) { $this->label = $label; }
    public function setName($name) { $this->name = $name; }
    public function setHandle($handle) { $this->handle = $handle; }
    public function setType($type) { $this->type = $type; }
    public function setRequired($required) { $this->required = (bool) $required; }
    public function setPlaceholder($placeholder) { $this->placeholder = $placeholder; }
    public function setConfig($config) { $this->config = $config; }
    public function setFieldOrder($order) { $this->field_order = $order; }
    public function setChildren($children) { $this->children = $children; }

    /**
     * Recursively load all fields for a component, including nested fields
     */
    public static function findFieldsTree($component_id, $parent_field_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'cc_fields';
        $fields = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE component_id = %d AND " .
                ($parent_field_id === null ? "parent_field_id IS NULL" : "parent_field_id = %d") .
                " ORDER BY field_order, created_at",
                $parent_field_id === null ? $component_id : [$component_id, $parent_field_id]
            ),
            ARRAY_A
        );
        $result = [];
        foreach ($fields as $data) {
            $field = new self($data);
            // Recursively load children for repeaters
            if ($field->getType() === 'repeater') {
                $field->children = self::findFieldsTree($component_id, $field->getId());
            }
            $result[] = $field;
        }
        return $result;
    }
}
