<?php
namespace CCC\Models;

defined('ABSPATH') || exit;

class Field {
    private $id;
    private $component_id;
    private $label;
    private $name;
    private $type;
    private $config;
    private $field_order;
    private $created_at;

    public function __construct($data = []) {
        $this->id = $data['id'] ?? null;
        $this->component_id = $data['component_id'] ?? null;
        $this->label = $data['label'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->type = $data['type'] ?? '';
        $this->config = $data['config'] ?? '{}';
        $this->field_order = $data['field_order'] ?? 0;
        $this->created_at = $data['created_at'] ?? null;
    }

    public function save() {
        global $wpdb;
        $table = $wpdb->prefix . 'cc_fields';

        $data = [
            'component_id' => $this->component_id,
            'label' => $this->label,
            'name' => $this->name,
            'type' => $this->type,
            'config' => $this->config,
            'field_order' => $this->field_order
        ];

        if ($this->id) {
            $result = $wpdb->update($table, $data, ['id' => $this->id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert($table, $data);
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
        return $wpdb->delete(
            $wpdb->prefix . 'cc_fields',
            ['id' => $this->id],
            ['%d']
        ) !== false;
    }

    public static function findByComponent($component_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'cc_fields';
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE component_id = %d ORDER BY field_order, created_at",
                $component_id
            ),
            ARRAY_A
        );

        return array_map(function($data) {
            return new self($data);
        }, $results);
    }

    public function getValue($post_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'cc_field_values';
        
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT value FROM $table WHERE field_id = %d AND post_id = %d",
                $this->id, $post_id
            )
        );
    }

    // Getters
    public function getId() { return $this->id; }
    public function getComponentId() { return $this->component_id; }
    public function getLabel() { return $this->label; }
    public function getName() { return $this->name; }
    public function getType() { return $this->type; }
    public function getConfig() { return $this->config; }
    public function getFieldOrder() { return $this->field_order; }
    public function getCreatedAt() { return $this->created_at; }

    // Setters
    public function setComponentId($id) { $this->component_id = $id; }
    public function setLabel($label) { $this->label = $label; }
    public function setName($name) { $this->name = $name; }
    public function setType($type) { $this->type = $type; }
    public function setConfig($config) { $this->config = $config; }
    public function setFieldOrder($order) { $this->field_order = $order; }
}
