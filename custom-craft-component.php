<?php
/**
 * Plugin Name: Custom Craft Component
 * Description: Create custom frontend components with fields like text and textareas.
 * Version: 1.2.9.1
 * Author: Abhishek
 */

defined('ABSPATH') || exit;

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    $prefix = 'CCC\\';
    $base_dir = plugin_dir_path(__FILE__) . 'inc/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

require_once plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';
require_once plugin_dir_path(__FILE__) . 'inc/Helpers/TemplateHelpers.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
use CCC\Core\Plugin;

register_activation_hook(__FILE__, ['CCC\Core\Database', 'activate']);

function custom_craft_component_init() {
    $plugin = new Plugin();
    $plugin->init();

    if (is_admin()) {
        PucFactory::buildUpdateChecker(
            'https://raw.githubusercontent.com/789Abhi/CCC-Plugin/Master/manifest.json',
            __FILE__,
            'custom-craft-component'
        );
    }
}
add_action('plugins_loaded', 'custom_craft_component_init');
