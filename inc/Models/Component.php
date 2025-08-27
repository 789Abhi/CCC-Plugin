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
            'hidden' => $this->hidden,
            'component_order' => $this->component_order
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
        
        // NOTE: We no longer remove component assignments when deleting
        // This allows deleted components to still appear in metaboxes with red background
        // Users can manually remove them if needed
        
        // Delete all fields associated with this component
        $this->deleteComponentFields();
        
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

        // Mark this component as deleted in all posts that have it assigned
        $this->markComponentAsDeletedInPosts();

        return $result !== false;
    }
    
    /**
     * Delete all fields associated with this component
     */
    private function deleteComponentFields() {
        global $wpdb;
        
        // Delete all fields for this component
        $wpdb->delete(
            $wpdb->prefix . 'cc_fields',
            ['component_id' => $this->id],
            ['%d']
        );
        
        // Delete all field values for this component's fields
        $field_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}cc_fields WHERE component_id = %d",
            $this->id
        ));
        
        if (!empty($field_ids)) {
            $placeholders = implode(',', array_fill(0, count($field_ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}cc_field_values WHERE field_id IN ($placeholders)",
                ...$field_ids
            ));
        }
    }

    /**
     * Mark this component as deleted in all posts that have it assigned
     */
    private function markComponentAsDeletedInPosts() {
        // Get all posts that have this component assigned
        $posts = get_posts([
            'post_type' => ['post', 'page'],
            'post_status' => 'any',
            'numberposts' => -1,
            'meta_query' => [
                [
                    'key' => '_ccc_components',
                    'value' => $this->id,
                    'compare' => 'LIKE'
                ]
            ]
        ]);

        foreach ($posts as $post) {
            $components = get_post_meta($post->ID, '_ccc_components', true);
            if (is_array($components)) {
                // Find and mark this component as deleted
                foreach ($components as &$component) {
                    if (is_array($component) && isset($component['id']) && $component['id'] == $this->id) {
                        $component['isDeleted'] = true;
                        $component['deleted_at'] = current_time('mysql');
                        // Don't break here - we want to mark ALL instances of this component
                    }
                }
                
                // Update the post meta
                update_post_meta($post->ID, '_ccc_components', $components);
                
                // Also update component details
                $component_details = get_post_meta($post->ID, '_ccc_component_details', true);
                if (is_array($component_details)) {
                    foreach ($component_details as &$detail) {
                        if (isset($detail['id']) && $detail['id'] == $this->id) {
                            $detail['isDeleted'] = true;
                            $detail['deleted_at'] = current_time('mysql');
                            // Don't break here - we want to mark ALL instances of this component
                        }
                    }
                    update_post_meta($post->ID, '_ccc_component_details', $component_details);
                }
            }
        }
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

    public static function handleExists($handle) {
        global $wpdb;
        $table = $wpdb->prefix . 'cc_components';
        
        return $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $table WHERE handle_name = %s", $handle)
        ) !== null;
    }

    /**
     * Check if a handle exists excluding a specific component ID
     */
    public static function handleExistsExcluding($handle, $exclude_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'cc_components';
        
        return $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $table WHERE handle_name = %s AND id != %d", $handle, $exclude_id)
        ) !== null;
    }

    // Getters
    public function getId() { return $this->id; }
    public function getName() { return $this->name; }
    public function getHandleName() { return $this->handle_name; }
    public function getInstruction() { return $this->instruction; }
    public function isHidden() { return $this->hidden; }
    public function getComponentOrder() { return $this->component_order; }
    public function getCreatedAt() { return $this->created_at; }

    // Setters
    public function setName($name) { $this->name = $name; }
    public function setHandleName($handle) { $this->handle_name = $handle; }
    public function setInstruction($instruction) { $this->instruction = $instruction; }
    public function setHidden($hidden) { $this->hidden = $hidden; }
    public function setComponentOrder($order) { $this->component_order = $order; }
}
