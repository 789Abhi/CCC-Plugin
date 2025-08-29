<?php
/**
 * Plugin Name: Custom Craft Component
 * Description: Create custom frontend components with fields like text and textareas.
 * Version: 4.0
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

// Load update error suppressor
$update_error_suppressor_file = CCC_PLUGIN_PATH . 'inc/Helpers/UpdateErrorSuppressor.php';
if (file_exists($update_error_suppressor_file)) {
  require_once $update_error_suppressor_file;
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

// Create database tables on activation
register_activation_hook(__FILE__, function() {
    // Create the main database tables
    \CCC\Core\Database::activate();
});

// Prevent data deletion on uninstall - comment out or remove the uninstall hook
// register_uninstall_hook(__FILE__, ['CCC\Core\Database', 'uninstall']);

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
  
  // Initialize update error suppressor
  if (class_exists('CCC\Helpers\UpdateErrorSuppressor')) {
      \CCC\Helpers\UpdateErrorSuppressor::init();
  }
  
  $plugin = new Plugin();
  $plugin->init();

  if (is_admin()) {
      // Initialize update checker with error handling
      try {
          $updateChecker = PucFactory::buildUpdateChecker(
              'https://raw.githubusercontent.com/789Abhi/CCC-Plugin/Master/manifest.json',
              __FILE__,
              'custom-craft-component'
          );
          
          // Set longer timeout and error handling
          if (method_exists($updateChecker, 'setHttpRequestArgs')) {
              $updateChecker->setHttpRequestArgs(array(
                  'timeout' => 15,
                  'sslverify' => false
              ));
          }
          
      } catch (Exception $e) {
          // Silently handle update checker errors - don't show alerts
          error_log('CCC Update Checker Error: ' . $e->getMessage());
      }
  }
}

add_action('admin_enqueue_scripts', function($hook) {
    // Adjust the slug as needed for your plugin
    if (
        $hook === 'toplevel_page_custom-craft-component' ||
        strpos($hook, 'custom-craft-component') !== false
    ) {
        echo '<style>
            .notice, .notice-warning, .notice-error, .notice-success, .update-nag { display: none !important; }
        </style>';
    }
});

add_action('admin_enqueue_scripts', function() {
    if (is_admin()) {
        wp_enqueue_editor();
    }
});
