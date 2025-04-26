<?php
/**
 * Plugin Name: Custom Craft Component
 * Description: Create custom frontend components with fields like text and textareas.
 * Version: 1.1.2
 * Author: Abhishek
 */

defined('ABSPATH') || exit;

// Required for get_plugin_data()
require_once(ABSPATH . 'wp-admin/includes/plugin.php');

// Load classes
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

// Debug mode
function ccc_plugin_debug_info() {
    if (!isset($_GET['ccc_debug']) || !current_user_can('manage_options')) {
        return;
    }
    
    echo '<div class="notice notice-info is-dismissible">';
    echo '<h2>CCC Plugin Debug Information</h2>';
    
    // Plugin info
    $plugin_file = 'custom-craft-component/custom-craft-component.php';
    $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);
    echo '<p>Plugin Version (from file): ' . esc_html($plugin_data['Version']) . '</p>';
    
    // Database info
    $stored_version = get_option('ccc_plugin_build_version', 'Not set');
    $update_available = get_option('ccc_plugin_update_available') ? 'Yes' : 'No';
    $new_version = get_option('ccc_plugin_new_version', 'None');
    $download_url = get_option('ccc_plugin_download_url', 'None');
    $last_check = get_option('ccc_plugin_last_update_check', 'Never');
    
    echo '<p>Stored Version: ' . esc_html($stored_version) . '</p>';
    echo '<p>Update Available: ' . esc_html($update_available) . '</p>';
    echo '<p>New Version: ' . esc_html($new_version) . '</p>';
    echo '<p>Download URL: ' . esc_html($download_url) . '</p>';
    echo '<p>Last Check: ' . ($last_check ? date('Y-m-d H:i:s', $last_check) : 'Never') . '</p>';
    
    // Test manifest
    $updater = new Custom_Craft_Component_Updater();
    $manifest = $updater->get_remote_manifest();
    echo '<h3>Remote Manifest Test</h3>';
    echo '<p>Manifest URL: ' . esc_html($updater->get_manifest_url()) . '</p>';
    echo '<p>Result: ' . ($manifest ? 'Success' : 'Failed') . '</p>';
    if ($manifest) {
        echo '<pre>' . print_r($manifest, true) . '</pre>';
    }
    
    // Force buttons
    echo '<p><a href="' . admin_url('?ccc_force_check=1') . '" class="button button-primary">Force Update Check</a> ';
    echo '<a href="' . admin_url('?ccc_force_update=1') . '" class="button button-secondary">Force Update Available</a></p>';
    
    echo '</div>';
}
add_action('admin_notices', 'ccc_plugin_debug_info');

// Force update checks
function ccc_handle_force_actions() {
    if (isset($_GET['ccc_force_check']) && current_user_can('manage_options')) {
        $updater = new Custom_Craft_Component_Updater();
        $updater->check_for_updates();
        wp_redirect(admin_url('?ccc_debug=1'));
        exit;
    }
    
    if (isset($_GET['ccc_force_update']) && current_user_can('manage_options')) {
        update_option('ccc_plugin_update_available', true);
        update_option('ccc_plugin_new_version', '1.1.3');
        update_option('ccc_plugin_download_url', 'https://github.com/789Abhi/CCC-Plugin/releases/download/v1.1.3/custom-craft-component.zip');
        wp_redirect(admin_url());
        exit;
    }
}
add_action('admin_init', 'ccc_handle_force_actions');