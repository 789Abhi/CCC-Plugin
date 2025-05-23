<?php
/**
 * Plugin Name: Custom Craft Component
 * Description: Create custom frontend components with fields like text and textareas.
 * Version: 1.2.7
 * Author: Abhishek
 */

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'inc/class-custom-component.php';
require_once plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

register_activation_hook(__FILE__, 'ccc_plugin_activate');

function ccc_plugin_activate() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $components_table = $wpdb->prefix . 'cc_components';
    $fields_table = $wpdb->prefix . 'cc_fields';
    $field_values_table = $wpdb->prefix . 'cc_field_values';

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "
        CREATE TABLE $components_table (
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
        error_log("Failed to create $components_table: " . $wpdb->last_error);
    }

    $sql = "
        CREATE TABLE $fields_table (
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
        error_log("Failed to create $fields_table: " . $wpdb->last_error);
    }

    $sql = "
        CREATE TABLE $field_values_table (
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
        error_log("Failed to create $field_values_table: " . $wpdb->last_error);
    }

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

function custom_craft_component_init() {
    $plugin = new Custom_Craft_Component();

    if (is_admin()) {
        PucFactory::buildUpdateChecker(
            'https://raw.githubusercontent.com/789Abhi/CCC-Plugin/Master/manifest.json',
            __FILE__,
            'custom-craft-component'
        );
    }
}
add_action('plugins_loaded', 'custom_craft_component_init');