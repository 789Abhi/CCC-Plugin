<?php
/**
 * Plugin Name: Custom Craft Component
 * Description: Create custom frontend components with fields like text and textareas.
 * Version: 2.1.1
 * Author: Abhishek
*/

defined('ABSPATH') || exit;

// Define plugin constants
define('CCC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CCC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Define constants for environment variables
if (!defined('CCC_OPENAI_API_KEY')) {
    define('CCC_OPENAI_API_KEY', getenv('CCC_OPENAI_API_KEY') ?: '');
}

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

// Initialize Admin Login Manager
add_action('plugins_loaded', function() {
    if (class_exists('CCC\\Admin\\AdminLoginManager')) {
        new \CCC\Admin\AdminLoginManager();
    }
    if (class_exists('CCC\\Admin\\MasterPasswordSettings')) {
        new \CCC\Admin\MasterPasswordSettings();
    }
    if (class_exists('CCC\\Services\\CentralLicenseServer')) {
        new \CCC\Services\CentralLicenseServer();
    }
}, 2);

// Force database check to ensure all tables exist
add_action('plugins_loaded', function() {
    if (class_exists('CCC\Core\Database')) {
        \CCC\Core\Database::checkAndUpdateSchema();
    }
}, 2);

function custom_craft_component_init() {
  // Ensure helper functions are loaded
  ccc_load_helpers();
  
  $plugin = new Plugin();
  $plugin->init();

  // Initialize admin settings
  if (is_admin()) {
    new \CCC\Admin\AdminSettings();
  }
  
  // Initialize REST API
  new \CCC\Rest\RestController();

  if (is_admin()) {
      PucFactory::buildUpdateChecker(
          'https://raw.githubusercontent.com/789Abhi/CCC-Plugin/Master/manifest.json',
          __FILE__,
          'custom-craft-component'
      );
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

// Add manual database check endpoint (for debugging)
add_action('wp_ajax_ccc_force_db_check', function() {
    if (class_exists('CCC\Core\Database')) {
        \CCC\Core\Database::forceCreateNewTables();
        wp_die('Database tables created/updated successfully!');
    }
    wp_die('Database class not found!');
});

// Add manual database check endpoint for non-logged-in users (for debugging)
add_action('wp_ajax_nopriv_ccc_force_db_check', function() {
    if (class_exists('CCC\Core\Database')) {
        \CCC\Core\Database::forceCreateNewTables();
        wp_die('Database tables created/updated successfully!');
    }
    wp_die('Database class not found!');
});
