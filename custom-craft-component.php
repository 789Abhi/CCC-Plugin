<?php
/**
 * Plugin Name: Custom Craft Component
 * Description: Create custom frontend components with fields like text and textareas.
 * Version: 1.3.5
 * Author: Abhishek
*/

defined('ABSPATH') || exit;

// Define plugin constants
define('CCC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CCC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load GLOBAL helper functions FIRST - before anything else
$global_helpers_file = CCC_PLUGIN_PATH . 'inc/Helpers/GlobalHelpers.php';
if (file_exists($global_helpers_file)) {
  require_once $global_helpers_file;
}

// Load namespaced helper functions
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

// Load helper functions on multiple hooks to ensure availability
add_action('plugins_loaded', 'ccc_load_helpers', 1);
add_action('init', 'ccc_load_helpers', 1);
add_action('wp_loaded', 'ccc_load_helpers', 1);
add_action('template_redirect', 'ccc_load_helpers', 1);

function ccc_load_helpers() {
  $global_helpers_file = CCC_PLUGIN_PATH . 'inc/Helpers/GlobalHelpers.php';
  if (file_exists($global_helpers_file) && !function_exists('get_ccc_field')) {
      require_once $global_helpers_file;
  }
  
  $helper_file = CCC_PLUGIN_PATH . 'inc/Helpers/TemplateHelpers.php';
  if (file_exists($helper_file)) {
      require_once $helper_file;
  }
}

// Initialize plugin very early
add_action('plugins_loaded', 'custom_craft_component_init', 1);

function custom_craft_component_init() {
  // Ensure helper functions are loaded
  ccc_load_helpers();
  
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
