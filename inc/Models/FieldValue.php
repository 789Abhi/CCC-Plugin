<?php
namespace CCC\Models;

defined('ABSPATH') || exit;

class FieldValue {
    private $id;
    private $post_id;
    private $field_id;
    private $value;
    private $created_at;

    public function __construct($data = []) {
        $this->id = $data['id'] ?? null;
        $this->post_id = $data['post_id'] ?? null;
        $this->field_id = $data['field_id'] ?? null;
        $this->value = $data['value'] ?? '';
        $this->created_at = $data['created_at'] ?? null;
    }

    public function save() {
        global $wpdb;
        $table = $wpdb->prefix . 'cc_field_values';

        $data = [
            'post_id' => $this->post_id,
            'field_id' => $this->field_id,
            'value' => $this->value
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

    public static function deleteByPost($post_id) {
        global $wpdb;
        return $wpdb->delete(
            $wpdb->prefix . 'cc_field_values',
            ['post_id' => $post_id],
            ['%d']
        );
    }

    public static function saveMultiple($post_id, $field_values) {
        // Delete existing values
        self::deleteByPost($post_id);

        // Save new values
        foreach ($field_values as $field_id => $value) {
            if ($value !== '') {
                $field_value = new self([
                    'post_id' => $post_id,
                    'field_id' => intval($field_id),
                    'value' => wp_kses_post($value)
                ]);
                $field_value->save();
            }
        }
    }

    // Getters
    public function getId() { return $this->id; }
    public function getPostId() { return $this->post_id; }
    public function getFieldId() { return $this->field_id; }
    public function getValue() { return $this->value; }
    public function getCreatedAt() { return $this->created_at; }

    // Setters
    public function setPostId($id) { $this->post_id = $id; }
    public function setFieldId($id) { $this->field_id = $id; }
    public function setValue($value) { $this->value = $value; }
}
