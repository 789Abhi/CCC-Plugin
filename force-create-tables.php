<?php
/**
 * Temporary script to force create database tables
 * Run this in your browser or via command line to create the missing tables
 */

// Load WordPress
require_once('wp-load.php');

// Check if Database class exists
if (class_exists('CCC\Core\Database')) {
    echo "Database class found. Creating tables...\n";
    
    // Force create new tables (no admin users will be created)
    $result = \CCC\Core\Database::forceCreateNewTables();
    
    if ($result) {
        echo "✅ Tables created successfully!\n";
        echo "ℹ️  Note: No admin users were created automatically.\n";
        echo "ℹ️  Users must register through the plugin interface.\n";
    } else {
        echo "❌ Failed to create tables!\n";
    }
} else {
    echo "❌ Database class not found! Make sure the plugin is activated.\n";
}

// Also check what tables exist
global $wpdb;
$tables = [
    $wpdb->prefix . 'ccc_users',
    $wpdb->prefix . 'ccc_plugin_installations', 
    $wpdb->prefix . 'ccc_licenses'
];

echo "\nChecking table status:\n";
foreach ($tables as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    echo $exists ? "✅ $table exists" : "❌ $table missing";
    echo "\n";
}
