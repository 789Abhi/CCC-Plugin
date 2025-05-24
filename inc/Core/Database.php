<?php
namespace CCC\Core;

defined('ABSPATH') || exit;

class Database {
    public static function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $components_table = $wpdb->prefix . 'cc_components';
        $fields_table = $wpdb->prefix . 'cc_fields';
        $field_values_table = $wpdb->prefix . 'cc_field_values';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        self::createComponentsTable($components_table, $charset_collate);
        self::createFieldsTable($fields_table, $components_table, $charset_collate);
        self::createFieldValuesTable($field_values_table, $fields_table, $charset_collate);
        self::createTemplatesDirectory();
    }

    private static function createComponentsTable($table_name, $charset_collate) {
        global $wpdb;
        
        $sql = "
            CREATE TABLE $table_name (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                handle_name VARCHAR(255) NOT NULL UNIQUE,
                instruction VARCHAR(255),
                hidden BOOLEAN DEFAULT FALSE,
                component_order INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;
        ";
        
        dbDelta($sql);
        
        if ($wpdb->last_error) {
            error_log("Failed to create $table_name: " . $wpdb->last_error);
        }
    }

    private static function createFieldsTable($table_name, $components_table, $charset_collate) {
        global $wpdb;
        
        $sql = "
            CREATE TABLE $table_name (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                component_id BIGINT UNSIGNED NOT NULL,
                label VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL,
                config JSON,
                field_order INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                FOREIGN KEY (component_id) REFERENCES $components_table(id) ON DELETE CASCADE
            ) $charset_collate;
        ";
        
        dbDelta($sql);
        
        if ($wpdb->last_error) {
            error_log("Failed to create $table_name: " . $wpdb->last_error);
        }
    }

    private static function createFieldValuesTable($table_name, $fields_table, $charset_collate) {
        global $wpdb;
        
        $sql = "
            CREATE TABLE $table_name (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                post_id BIGINT UNSIGNED NOT NULL,
                field_id BIGINT UNSIGNED NOT NULL,
                value LONGTEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                FOREIGN KEY (field_id) REFERENCES $fields_table(id) ON DELETE CASCADE
            ) $charset_collate;
        ";
        
        dbDelta($sql);
        
        if ($wpdb->last_error) {
            error_log("Failed to create $table_name: " . $wpdb->last_error);
        }
    }

    private static function createTemplatesDirectory() {
        $theme_dir = get_stylesheet_directory();
        $templates_dir = $theme_dir . '/ccc-templates';
        
        if (!file_exists($templates_dir)) {
            if (!wp_mkdir_p($templates_dir)) {
                error_log("Failed to create directory: $templates_dir");
            }
            if (!chmod($templates_dir, 0755)) {
                error_log("Failed to set permissions on directory: $templates_dir");
            }
        }
    }
}
