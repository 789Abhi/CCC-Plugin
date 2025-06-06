<?php
namespace CCC\Models;

defined('ABSPATH') || exit;

class Component {
    private $id;
    private $name;
    private $handle_name;
    private $instruction;
    private $hidden;
    private $component_order;
    private $created_at;

    public function __construct($data = []) {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? '';
        $this->handle_name = $data['handle_name'] ?? '';
        $this->instruction = $data['instruction'] ?? '';
        $this->hidden = $data['hidden'] ?? false;
        $this->component_order = $data['component_order'] ?? 0;
        $this->created_at = $data['created_at'] ?? null;
    }

    public function save() {
        global $wpdb;
        $table = $wpdb->prefix . 'cc_components';

        $data = [
            'name' => $this->name,
            'handle_name' => $this->handle_name,
            'instruction' => $this->instruction,
            'hidden' => $this->hidden ? 1 : 0, // Ensure boolean is saved as integer
            'component_order' => $this->component_order
        ];

        $format = [
            '%s', // name
            '%s', // handle_name
            '%s', // instruction
            '%d', // hidden
            '%d'  // component_order
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
        $result = $wpdb->delete(
            $wpdb->prefix . 'cc_components',
            ['id' => $this->id],
            ['%d']
        );

        // Delete template file
        $theme_dir = get_stylesheet_directory();
        $template_file = $theme_dir . '/ccc-templates/' . $this->handle_name . '.php';
        if (file_exists($template_file)) {
            unlink($template_file);
        }

        return $result !== false;
    }

    public static function find($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'cc_components';
        
        $data = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id),
            ARRAY_A
        );

        return $data ? new self($data) : null;
    }

    public static function all() {
        global $wpdb;
        $table = $wpdb->prefix . 'cc_components';
        
        $results = $wpdb->get_results("SELECT * FROM $table ORDER BY component_order, created_at", ARRAY_A);
        
        return array_map(function($data) {
            return new self($data);
        }, $results);
    }

    public static function handleExists($handle, $exclude_id = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'cc_components';
        
        $query = "SELECT id FROM $table WHERE handle_name = %s";
        $params = [$handle];
        
        if ($exclude_id) {
            $query .= " AND id != %d";
            $params[] = $exclude_id;
        }

        $result = $wpdb->get_var($wpdb->prepare($query, $params));
        return $result !== null;
    }

    // Getters
    public function getId() { return $this->id; }
    public function getName() { return $this->name; }
    public function getHandleName() { return $this->handle_name; }
    public function getInstruction() { return $this->instruction; }
    public function isHidden() { return (bool) $this->hidden; }
    public function getComponentOrder() { return $this->component_order; }
    public function getCreatedAt() { return $this->created_at; }

    // Setters
    public function setName($name) { $this->name = $name; }
    public function setHandleName($handle) { $this->handle_name = $handle; }
    public function setInstruction($instruction) { $this->instruction = $instruction; }
    public function setHidden($hidden) { $this->hidden = (bool) $hidden; }
    public function setComponentOrder($order) { $this->component_order = (int) $order; }
}