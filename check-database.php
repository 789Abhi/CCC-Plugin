<?php
/**
 * Database check script for Custom Craft Component
 * This will help identify if the database tables exist and are accessible
 */

// Load WordPress
require_once('../../../wp-load.php');

echo "<h1>Custom Craft Component - Database Check</h1>";

// Check if WordPress is loaded
if (!defined('ABSPATH')) {
    echo "<p style='color: red;'>ERROR: WordPress not loaded!</p>";
    exit;
}

echo "<p>WordPress loaded successfully.</p>";

// Check database connection
global $wpdb;
if (!$wpdb) {
    echo "<p style='color: red;'>ERROR: WordPress database object not available!</p>";
    exit;
}

echo "<p>Database connection: OK</p>";
echo "<p>Database prefix: " . $wpdb->prefix . "</p>";

// Check if required tables exist
$required_tables = [
    'cc_components',
    'cc_fields', 
    'cc_field_values'
];

echo "<h2>Database Tables Check</h2>";
$all_tables_exist = true;

foreach ($required_tables as $table) {
    $full_table_name = $wpdb->prefix . $table;
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") == $full_table_name;
    
    if ($table_exists) {
        echo "<p style='color: green;'>✓ Table $full_table_name exists</p>";
        
        // Check table structure
        $columns = $wpdb->get_results("DESCRIBE $full_table_name");
        echo "<ul>";
        foreach ($columns as $column) {
            echo "<li>{$column->Field} - {$column->Type}</li>";
        }
        echo "</ul>";
        
        // Check row count
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table_name");
        echo "<p>Row count: $count</p>";
        
    } else {
        echo "<p style='color: red;'>✗ Table $full_table_name does NOT exist</p>";
        $all_tables_exist = false;
    }
}

// If tables don't exist, try to create them
if (!$all_tables_exist) {
    echo "<h2>Attempting to Create Missing Tables</h2>";
    
    if (class_exists('CCC\Core\Database')) {
        echo "<p>Database class found, attempting to create tables...</p>";
        
        try {
            \CCC\Core\Database::activate();
            echo "<p style='color: green;'>Database activation completed.</p>";
            
            // Check again
            echo "<h3>Re-checking Tables</h3>";
            foreach ($required_tables as $table) {
                $full_table_name = $wpdb->prefix . $table;
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") == $full_table_name;
                echo "<p>" . ($table_exists ? "✓" : "✗") . " Table $full_table_name: " . ($table_exists ? "EXISTS" : "MISSING") . "</p>";
            }
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>ERROR creating tables: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: red;'>ERROR: Database class not found!</p>";
    }
}

// Test basic WordPress functions
echo "<h2>WordPress Function Tests</h2>";

// Test get_posts
try {
    $posts = get_posts([
        'post_type' => 'page',
        'post_status' => 'publish',
        'numberposts' => 3
    ]);
    echo "<p style='color: green;'>✓ get_posts() works: " . count($posts) . " posts found</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ get_posts() failed: " . $e->getMessage() . "</p>";
}

// Test get_post_meta
if (!empty($posts)) {
    try {
        $meta = get_post_meta($posts[0]->ID, '_ccc_components', true);
        echo "<p style='color: green;'>✓ get_post_meta() works</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ get_post_meta() failed: " . $e->getMessage() . "</p>";
    }
}

// Test nonce creation
try {
    $nonce = wp_create_nonce('ccc_nonce');
    echo "<p style='color: green;'>✓ Nonce creation works: " . substr($nonce, 0, 10) . "...</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Nonce creation failed: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ul>";
echo "<li>If tables are missing, the plugin may need to be reactivated</li>";
echo "<li>Check WordPress error logs for more details</li>";
echo "<li>Try accessing the test-ajax.php file to test AJAX functionality</li>";
echo "</ul>";
?>
