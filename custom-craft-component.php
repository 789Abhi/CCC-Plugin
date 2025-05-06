<?php
/**
 * Plugin Name: Custom Craft Component
 * Description: Create custom frontend components with fields like text and textareas.
 * Version: 1.2.3
 * Author: Abhishek
 */

defined('ABSPATH') || exit;

// Include your main plugin logic
require_once plugin_dir_path(__FILE__) . 'inc/class-custom-component.php';

// Include the Plugin Update Checker library
require_once plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

function custom_craft_component_init() {
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
