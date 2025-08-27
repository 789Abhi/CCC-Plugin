<?php
/**
 * Fix component handle mismatch
 * This script will update the component handle in the database to match the actual template file
 */

// Load WordPress
require_once('../../../wp-load.php');

echo "<h1>Fix Component Handle Mismatch</h1>";

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

echo "<h2>Current Components in Database</h2>";
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
    exit;
}

// Check template files
$theme_dir = get_stylesheet_directory();
$templates_dir = $theme_dir . '/ccc-templates';

echo "<h2>Template Files in Theme</h2>";
if (is_dir($templates_dir)) {
    $template_files = glob($templates_dir . '/*.php');
    if ($template_files) {
        echo "<ul>";
        foreach ($template_files as $file) {
            $filename = basename($file, '.php');
            echo "<li><strong>$filename.php</strong> (handle: $filename)</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No template files found.</p>";
    }
} else {
    echo "<p>Template directory not found: $templates_dir</p>";
}

// Find mismatches
echo "<h2>Handle Mismatches</h2>";
$mismatches = [];
foreach ($components as $comp) {
    $template_file = $templates_dir . '/' . $comp->handle_name . '.php';
    $expected_template = $templates_dir . '/' . $comp->handle_name . '.php';
    
    if (!file_exists($template_file)) {
        // Check if there's a template file with a different name that should match
        foreach ($template_files as $file) {
            $filename = basename($file, '.php');
            if ($filename !== $comp->handle_name) {
                $mismatches[] = [
                    'component_id' => $comp->id,
                    'component_name' => $comp->name,
                    'current_handle' => $comp->handle_name,
                    'template_file' => $filename,
                    'suggested_fix' => $filename
                ];
                break;
            }
        }
    }
}

if ($mismatches) {
    echo "<p style='color: orange;'>Found " . count($mismatches) . " handle mismatches:</p>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Component ID</th><th>Name</th><th>Current Handle</th><th>Template File</th><th>Suggested Fix</th></tr>";
    foreach ($mismatches as $mismatch) {
        echo "<tr>";
        echo "<td>{$mismatch['component_id']}</td>";
        echo "<td>{$mismatch['component_name']}</td>";
        echo "<td>{$mismatch['current_handle']}</td>";
        echo "<td>{$mismatch['template_file']}.php</td>";
        echo "<td>{$mismatch['suggested_fix']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Offer to fix
    if (isset($_POST['fix_mismatches'])) {
        echo "<h2>Fixing Mismatches...</h2>";
        
        foreach ($mismatches as $mismatch) {
            $new_handle = $mismatch['suggested_fix'];
            
            // Update the component handle in the database
            $result = $wpdb->update(
                $components_table,
                ['handle_name' => $new_handle],
                ['id' => $mismatch['component_id']],
                ['%s'],
                ['%d']
            );
            
            if ($result !== false) {
                echo "<p style='color: green;'>✓ Fixed component {$mismatch['component_id']}: {$mismatch['current_handle']} → {$new_handle}</p>";
                
                // Also update any post meta that references this component
                $posts = get_posts([
                    'post_type' => ['post', 'page'],
                    'post_status' => 'any',
                    'numberposts' => -1,
                    'meta_query' => [
                        [
                            'key' => '_ccc_components',
                            'value' => $mismatch['component_id'],
                            'compare' => 'LIKE'
                        ]
                    ]
                ]);
                
                foreach ($posts as $post) {
                    // Update component details in post meta
                    $component_details = get_post_meta($post->ID, '_ccc_component_details', true);
                    if (is_array($component_details)) {
                        foreach ($component_details as &$detail) {
                            if ($detail['id'] == $mismatch['component_id']) {
                                $detail['handle_name'] = $new_handle;
                                break;
                            }
                        }
                        update_post_meta($post->ID, '_ccc_component_details', $component_details);
                    }
                }
                
            } else {
                echo "<p style='color: red;'>✗ Failed to fix component {$mismatch['component_id']}</p>";
            }
        }
        
        echo "<p style='color: green;'><strong>Mismatches fixed! Please refresh the page to see the updated data.</strong></p>";
        
    } else {
        echo "<form method='post'>";
        echo "<p><strong>Click the button below to automatically fix these mismatches:</strong></p>";
        echo "<input type='submit' name='fix_mismatches' value='Fix Handle Mismatches' style='background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer;'>";
        echo "</form>";
    }
    
} else {
    echo "<p style='color: green;'>✓ No handle mismatches found!</p>";
}

echo "<hr>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ul>";
echo "<li>If mismatches were found and fixed, refresh this page to verify</li>";
echo "<li>Check the WordPress admin to ensure components are working correctly</li>";
echo "<li>Verify that metaboxes show the correct component names</li>";
echo "</ul>";
?>
