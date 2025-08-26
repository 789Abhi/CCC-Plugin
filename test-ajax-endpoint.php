<?php
/**
 * Test AJAX endpoint functionality
 * This will help identify if the AJAX handler is working properly
 */

// Load WordPress
require_once('../../../wp-load.php');

echo "<h1>Custom Craft Component - AJAX Endpoint Test</h1>";

// Check if WordPress is loaded
if (!defined('ABSPATH')) {
    echo "<p style='color: red;'>ERROR: WordPress not loaded!</p>";
    exit;
}

echo "<p>WordPress loaded successfully.</p>";

// Check if AJAX handler class exists
if (!class_exists('CCC\Ajax\AjaxHandler')) {
    echo "<p style='color: red;'>ERROR: AjaxHandler class not found!</p>";
    exit;
}

echo "<p>AjaxHandler class found.</p>";

// Check if AJAX actions are registered
global $wp_filter;
$ajax_actions = [
    'wp_ajax_ccc_get_posts',
    'wp_ajax_ccc_get_posts_with_components',
    'wp_ajax_ccc_get_components'
];

echo "<h2>AJAX Actions Registration Check</h2>";
foreach ($ajax_actions as $action) {
    if (isset($wp_filter[$action])) {
        echo "<p style='color: green;'>✓ $action is registered</p>";
    } else {
        echo "<p style='color: red;'>✗ $action is NOT registered</p>";
    }
}

// Test nonce creation
echo "<h2>Nonce Test</h2>";
try {
    $nonce = wp_create_nonce('ccc_nonce');
    echo "<p style='color: green;'>✓ Nonce created: " . substr($nonce, 0, 10) . "...</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Nonce creation failed: " . $e->getMessage() . "</p>";
}

// Test database connection
echo "<h2>Database Connection Test</h2>";
global $wpdb;
if ($wpdb->last_error) {
    echo "<p style='color: red;'>✗ Database error: " . $wpdb->last_error . "</p>";
} else {
    echo "<p style='color: green;'>✓ Database connection OK</p>";
}

// Check if required tables exist
echo "<h2>Database Tables Check</h2>";
$required_tables = [
    'cc_components',
    'cc_fields', 
    'cc_field_values'
];

foreach ($required_tables as $table) {
    $full_table_name = $wpdb->prefix . $table;
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") == $full_table_name;
    
    if ($table_exists) {
        echo "<p style='color: green;'>✓ Table $full_table_name exists</p>";
        
        // Check row count
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table_name");
        echo "<p>Row count: $count</p>";
        
    } else {
        echo "<p style='color: red;'>✗ Table $full_table_name does NOT exist</p>";
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

// Test AJAX URL
echo "<h2>AJAX URL Test</h2>";
$ajax_url = admin_url('admin-ajax.php');
echo "<p>AJAX URL: " . $ajax_url . "</p>";

// Test if we can make a request to the AJAX endpoint
echo "<h2>AJAX Endpoint Test</h2>";
echo "<p>Testing AJAX endpoint manually...</p>";

// Simulate the AJAX request
$_POST['action'] = 'ccc_get_posts_with_components';
$_POST['post_type'] = 'page';
$_POST['nonce'] = $nonce;

// Capture output
ob_start();

// Try to call the AJAX handler directly
try {
    $ajax_handler = new \CCC\Ajax\AjaxHandler();
    $ajax_handler->getPostsWithComponents();
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ AJAX handler failed: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace: " . $e->getTraceAsString() . "</p>";
}

$output = ob_get_clean();

if (!empty($output)) {
    echo "<p>AJAX handler output:</p>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
} else {
    echo "<p style='color: orange;'>⚠ No output from AJAX handler (this might be normal)</p>";
}

echo "<hr>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ul>";
echo "<li>If tables are missing, try reactivating the plugin</li>";
echo "<li>Check WordPress error logs for more details</li>";
echo "<li>Look at the browser's Network tab to see the actual HTTP response</li>";
echo "</ul>";

echo "<h3>Manual AJAX Test</h3>";
echo "<p>You can also test the AJAX endpoint manually by making a POST request to:</p>";
echo "<code>$ajax_url</code>";
echo "<p>With these parameters:</p>";
echo "<ul>";
echo "<li>action: ccc_get_posts_with_components</li>";
echo "<li>post_type: page</li>";
echo "<li>nonce: $nonce</li>";
echo "</ul>";
?>
