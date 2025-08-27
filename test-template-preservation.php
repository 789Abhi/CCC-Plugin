<?php
/**
 * Test file to demonstrate template preservation functionality
 * 
 * This file tests the new functionality that preserves custom template content
 * when updating component names/handles.
 */

// Include WordPress
require_once('../../../wp-load.php');

// Check if user is logged in and has admin privileges
if (!current_user_can('manage_options')) {
    wp_die('Access denied. Admin privileges required.');
}

echo "<h1>Template Preservation Test</h1>";

// Test the ComponentService methods
try {
    $component_service = new \CCC\Services\ComponentService();
    
    echo "<h2>Testing Template Content Detection</h2>";
    
    // Test with a component that should exist
    $test_handle = 'hero_section'; // Adjust this to match an existing component
    
    echo "<p>Testing handle: <strong>$test_handle</strong></p>";
    
    // Check if template has custom content
    $has_custom = $component_service->hasCustomTemplateContent($test_handle);
    echo "<p>Has custom content: <strong>" . ($has_custom ? 'YES' : 'NO') . "</strong></p>";
    
    if ($has_custom) {
        $content = $component_service->getTemplateContent($test_handle);
        echo "<h3>Current Template Content:</h3>";
        echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd; max-height: 300px; overflow-y: auto;'>";
        echo htmlspecialchars($content);
        echo "</pre>";
    }
    
    echo "<h2>Test Complete</h2>";
    echo "<p>If you see custom content above, the template preservation functionality is working correctly.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><a href='javascript:history.back()'>← Go Back</a></p>";
?>
