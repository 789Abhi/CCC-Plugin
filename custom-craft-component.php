<?php
/**
 * Plugin Name: Custom Craft Component
 * Description: Create custom frontend components with fields like text and textareas.
 * Version: 1.3.1.1
 * Author: Abhishek
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('CCC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CCC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load helper functions IMMEDIATELY - before anything else
$helper_file = CCC_PLUGIN_PATH . 'inc/Helpers/TemplateHelpers.php';
if (file_exists($helper_file)) {
    require_once $helper_file;
}

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    $prefix = 'CCC\\';
    $base_dir = CCC_PLUGIN_PATH . 'inc/';
    
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

require_once CCC_PLUGIN_PATH . 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
use CCC\Core\Plugin;

register_activation_hook(__FILE__, ['CCC\Core\Database', 'activate']);

// Initialize plugin very early
add_action('plugins_loaded', 'custom_craft_component_init', 1);

function custom_craft_component_init() {
    // Ensure helper functions are loaded
    $helper_file = CCC_PLUGIN_PATH . 'inc/Helpers/TemplateHelpers.php';
    if (file_exists($helper_file) && !function_exists('get_ccc_field')) {
        require_once $helper_file;
    }
    
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

// Additional safety net - load helpers on init
add_action('init', function() {
    $helper_file = CCC_PLUGIN_PATH . 'inc/Helpers/TemplateHelpers.php';
    if (file_exists($helper_file) && !function_exists('get_ccc_field')) {
        require_once $helper_file;
    }
}, 1);

// Load helpers before template loading
add_action('template_redirect', function() {
    $helper_file = CCC_PLUGIN_PATH . 'inc/Helpers/TemplateHelpers.php';
    if (file_exists($helper_file) && !function_exists('get_ccc_field')) {
        require_once $helper_file;
    }
}, 1);
