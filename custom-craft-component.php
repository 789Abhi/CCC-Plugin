<?php
/**
 * Plugin Name: Custom Craft Component
 * Description: Create custom frontend components with fields like text and textareas.
 * Version: 1.1.1
 * Author: Abhishek
 */

defined('ABSPATH') || exit;

// Correct file path
require_once plugin_dir_path(__FILE__) . 'inc/class-custom-component.php';
require_once plugin_dir_path(__FILE__) . 'inc/class-plugin-updater.php';

// Instantiate the plugin
function custom_craft_component_init() {
    $plugin = new Custom_Craft_Component();
    
    // Initialize updater
    if (is_admin()) {
        new Custom_Craft_Component_Updater();
    }
}


add_action('plugins_loaded', 'custom_craft_component_init');
