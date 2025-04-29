<?php
/**
 * Plugin Name: CCC Plugin
 * Plugin URI: https://github.com/your-username/ccc-plugin
 * Description: Custom plugin with React frontend integration
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://your-website.com
 * Text Domain: ccc-plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CCC_PLUGIN_VERSION', '1.0.0');
define('CCC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CCC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CCC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once CCC_PLUGIN_PATH . 'inc/class-custom-component.php';
require_once CCC_PLUGIN_PATH . 'inc/class-plugin-updater.php';

// Initialize the plugin
function run_ccc_plugin() {
    $plugin = new CCC_Plugin();
    $plugin->run();
    
    // Initialize the updater
    $updater = new CCC_Updater();
    $updater->init();
}
run_ccc_plugin();