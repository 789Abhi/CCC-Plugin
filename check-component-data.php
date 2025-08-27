<?php
/**
 * Check component data script
 */

// Load WordPress
require_once('../../../wp-load.php');

echo "<h1>Component Data Check</h1>";

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

// Check components table
$components_table = $wpdb->prefix . 'cc_components';
$components = $wpdb->get_results("SELECT * FROM $components_table");

echo "<h2>Components in Database</h2>";
if ($components) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Handle</th><th>Created</th><th>Updated</th></tr>";
    foreach ($components as $comp) {
        echo "<tr>";
        echo "<td>{$comp->id}</td>";
        echo "<td>{$comp->name}</td>";
        echo "<td>{$comp->handle_name}</td>";
        echo "<td>{$comp->created_at}</td>";
        echo "<td>{$comp->updated_at}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No components found in database.</p>";
}

// Check if there are any posts with component assignments
echo "<h2>Posts with Component Assignments</h2>";
$posts_with_components = get_posts([
    'post_type' => ['post', 'page'],
    'post_status' => 'any',
    'numberposts' => -1,
    'meta_query' => [
        [
            'key' => '_ccc_components',
            'compare' => 'EXISTS'
        ]
    ]
]);

if ($posts_with_components) {
    echo "<p>Found " . count($posts_with_components) . " posts with component assignments:</p>";
    foreach ($posts_with_components as $post) {
        $components = get_post_meta($post->ID, '_ccc_components', true);
        $component_details = get_post_meta($post->ID, '_ccc_component_details', true);
        
        echo "<h3>Post: {$post->post_title} (ID: {$post->ID})</h3>";
        echo "<p><strong>Components:</strong> " . (is_array($components) ? implode(', ', $components) : $components) . "</p>";
        echo "<p><strong>Component Details:</strong></p>";
        echo "<pre>" . print_r($component_details, true) . "</pre>";
    }
} else {
    echo "<p>No posts found with component assignments.</p>";
}

echo "<hr>";
echo "<p><strong>Analysis:</strong></p>";
echo "<ul>";
echo "<li>Check if component handles match the template files</li>";
echo "<li>Verify component assignments are working correctly</li>";
echo "<li>Ensure metabox data is being updated properly</li>";
echo "</ul>";
?>
