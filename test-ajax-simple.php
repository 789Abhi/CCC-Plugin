<?php
// Simple AJAX test file
require_once('../../../wp-load.php');

// Check if user is logged in and has admin capabilities
if (!current_user_can('manage_options')) {
    die('Access denied');
}

echo "<h2>Simple AJAX Test</h2>";

// Test 1: Check if AJAX actions are registered
echo "<h3>1. Checking AJAX Actions</h3>";
global $wp_filter;
if (isset($wp_filter['wp_ajax_ccc_get_posts_with_components'])) {
    echo "✅ AJAX action 'ccc_get_posts_with_components' is registered<br>";
} else {
    echo "❌ AJAX action 'ccc_get_posts_with_components' is NOT registered<br>";
}

// Test 2: Check if AjaxHandler class exists
echo "<h3>2. Checking AjaxHandler Class</h3>";
if (class_exists('CCC\Ajax\AjaxHandler')) {
    echo "✅ AjaxHandler class exists<br>";
} else {
    echo "❌ AjaxHandler class does NOT exist<br>";
}

// Test 3: Test basic get_posts functionality
echo "<h3>3. Testing get_posts()</h3>";
$posts = get_posts([
    'post_type' => 'post',
    'numberposts' => 1,
    'post_status' => 'publish'
]);

if (is_array($posts) && !empty($posts)) {
    echo "✅ get_posts() works - found " . count($posts) . " posts<br>";
} elseif (is_wp_error($posts)) {
    echo "❌ get_posts() returned WP_Error: " . $posts->get_error_message() . "<br>";
} else {
    echo "❌ get_posts() failed or returned empty result<br>";
}

// Test 4: Check database tables
echo "<h3>4. Checking Database Tables</h3>";
global $wpdb;

$tables = ['wp_cc_components', 'wp_cc_fields', 'wp_cc_field_values'];
foreach ($tables as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        echo "✅ Table '$table' exists with $count rows<br>";
    } else {
        echo "❌ Table '$table' does NOT exist<br>";
    }
}

// Test 5: Test nonce creation
echo "<h3>5. Testing Nonce</h3>";
$nonce = wp_create_nonce('ccc_nonce');
if ($nonce) {
    echo "✅ Nonce created successfully: $nonce<br>";
} else {
    echo "❌ Failed to create nonce<br>";
}

echo "<hr>";
echo "<p><strong>Next step:</strong> Try accessing the actual AJAX endpoint in your browser console or via a tool like Postman:</p>";
echo "<p>URL: " . admin_url('admin-ajax.php') . "</p>";
echo "<p>Action: ccc_get_posts_with_components</p>";
echo "<p>Nonce: $nonce</p>";
?>
