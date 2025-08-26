<?php
/**
 * Test file to debug AJAX issues
 * Place this in your plugin directory and access it via browser
 */

// Load WordPress
require_once('../../../wp-load.php');

echo "<h1>Custom Craft Component - AJAX Test</h1>";

// Test 1: Check if WordPress is loaded
echo "<h2>Test 1: WordPress Status</h2>";
echo "ABSPATH defined: " . (defined('ABSPATH') ? 'YES' : 'NO') . "<br>";
echo "WordPress version: " . get_bloginfo('version') . "<br>";

// Test 2: Check if plugin classes are loaded
echo "<h2>Test 2: Plugin Classes</h2>";
echo "ComponentService exists: " . (class_exists('CCC\Services\ComponentService') ? 'YES' : 'NO') . "<br>";
echo "AjaxHandler exists: " . (class_exists('CCC\Ajax\AjaxHandler') ? 'YES' : 'NO') . "<br>";

// Test 3: Check database tables
echo "<h2>Test 3: Database Tables</h2>";
global $wpdb;
$tables = [
    $wpdb->prefix . 'cc_components',
    $wpdb->prefix . 'cc_fields',
    $wpdb->prefix . 'cc_field_values'
];

foreach ($tables as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
    echo "Table $table exists: " . ($exists ? 'YES' : 'NO') . "<br>";
}

// Test 4: Check if get_posts works
echo "<h2>Test 4: get_posts() Function</h2>";
try {
    $posts = get_posts([
        'post_type' => 'page',
        'post_status' => 'publish',
        'numberposts' => 5
    ]);
    echo "get_posts() successful: " . (is_array($posts) ? 'YES' : 'NO') . "<br>";
    echo "Number of posts returned: " . count($posts) . "<br>";
} catch (Exception $e) {
    echo "get_posts() error: " . $e->getMessage() . "<br>";
}

// Test 5: Check if get_post_meta works
echo "<h2>Test 5: get_post_meta() Function</h2>";
if (!empty($posts)) {
    $first_post = $posts[0];
    try {
        $meta = get_post_meta($first_post->ID, '_ccc_components', true);
        echo "get_post_meta() successful: " . (is_array($meta) || $meta === '' ? 'YES' : 'NO') . "<br>";
        echo "Meta value type: " . gettype($meta) . "<br>";
    } catch (Exception $e) {
        echo "get_post_meta() error: " . $e->getMessage() . "<br>";
    }
}

// Test 6: Check nonce creation
echo "<h2>Test 6: Nonce Creation</h2>";
$nonce = wp_create_nonce('ccc_nonce');
echo "Nonce created: " . (!empty($nonce) ? 'YES' : 'NO') . "<br>";
echo "Nonce value: " . $nonce . "<br>";

// Test 7: Check AJAX URL
echo "<h2>Test 7: AJAX URL</h2>";
$ajax_url = admin_url('admin-ajax.php');
echo "AJAX URL: " . $ajax_url . "<br>";

echo "<hr>";
echo "<p>If all tests pass, the issue might be in the AJAX handler logic or database queries.</p>";
echo "<p>Check your WordPress error logs for more details.</p>";
?>
