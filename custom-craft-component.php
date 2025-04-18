<?php
/**
 * Plugin Name: Custom Craft Component
 * Description: Create custom frontend components with fields like text and textareas.
 * Version: 1.0
 * Author: Your Name
 */

defined('ABSPATH') || exit;

// Correct file path
require_once plugin_dir_path(__FILE__) . 'inc/class-custom-component.php';

// Instantiate the plugin
new Custom_Craft_Component();
