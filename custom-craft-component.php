<?php
/**
 * Plugin Name: Custom Craft Component
 * Description: Create custom frontend components with fields like text and textareas.
 * Version: 1.2.4
 * Author: Abhishek
 */

defined('ABSPATH') || exit;

// Include your main plugin logic
require_once plugin_dir_path(__FILE__) . 'inc/class-custom-component.php';

// Include the Plugin Update Checker library
require_once plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;



register_activation_hook(__FILE__, 'ccc_plugin_activate');

function ccc_plugin_activate()
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $components_table = $wpdb->prefix . 'cc_components';
    $fields_table = $wpdb->prefix . 'cc_fields';
    $field_values_table = $wpdb->prefix . 'cc_field_values';

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    dbDelta("
        CREATE TABLE $components_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            handle_name VARCHAR(255) NOT NULL UNIQUE,
            instruction VARCHAR(255),
            hidden BOOLEAN DEFAULT FALSE,
            component_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;
    ");

    dbDelta("
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
    ");

    dbDelta("
        CREATE TABLE $field_values_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            field_id BIGINT UNSIGNED NOT NULL,
            value LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            FOREIGN KEY (field_id) REFERENCES $fields_table(id) ON DELETE CASCADE
        ) $charset_collate;
    ");
}


function custom_craft_component_init()
{
    // Instantiate your plugin class
    $plugin = new Custom_Craft_Component();

    // Setup plugin update checker (using manifest.json)
    if (is_admin()) {
        $updateChecker = PucFactory::buildUpdateChecker(
            'https://raw.githubusercontent.com/789Abhi/CCC-Plugin/Master/manifest.json',
            __FILE__,
            'custom-craft-component'
        );

        // Note: Do not call setBranch() when using manifest.json
    }
}
add_action('plugins_loaded', 'custom_craft_component_init');
