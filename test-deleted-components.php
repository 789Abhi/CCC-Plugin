<?php
/**
 * Test script to verify deleted components functionality
 * 
 * This script tests:
 * 1. Creating a component
 * 2. Assigning it to a post
 * 3. Deleting the component
 * 4. Checking if it still appears in the post with isDeleted flag
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if user is logged in and has admin privileges
if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

echo "<h1>Testing Deleted Components Functionality</h1>\n";

try {
    // Step 1: Create a test component
    echo "<h2>Step 1: Creating test component</h2>\n";
    
    global $wpdb;
    $components_table = $wpdb->prefix . 'cc_components';
    
    $component_data = [
        'name' => 'Test Component for Deletion',
        'handle_name' => 'test_component_for_deletion',
        'instruction' => 'This component will be deleted to test the functionality',
        'hidden' => 0,
        'component_order' => 0,
        'created_at' => current_time('mysql')
    ];
    
    $result = $wpdb->insert($components_table, $component_data);
    if ($result === false) {
        throw new Exception("Failed to create component: " . $wpdb->last_error);
    }
    
    $component_id = $wpdb->insert_id;
    echo "✓ Created component with ID: {$component_id}\n";
    
    // Step 2: Create a test post and assign the component
    echo "<h2>Step 2: Creating test post and assigning component</h2>\n";
    
    $post_data = [
        'post_title' => 'Test Post for Deleted Components',
        'post_content' => 'This post will have a component that gets deleted.',
        'post_status' => 'publish',
        'post_type' => 'page'
    ];
    
    $post_id = wp_insert_post($post_data);
    if (is_wp_error($post_id)) {
        throw new Exception("Failed to create post: " . $post_id->get_error_message());
    }
    
    echo "✓ Created post with ID: {$post_id}\n";
    
    // Assign component to post
    $component_assignment = [
        [
            'id' => $component_id,
            'name' => $component_data['name'],
            'handle_name' => $component_data['handle_name'],
            'order' => 0,
            'instance_id' => 'test_instance_' . time(),
            'isHidden' => false
        ]
    ];
    
    update_post_meta($post_id, '_ccc_components', $component_assignment);
    echo "✓ Assigned component to post\n";
    
    // Step 3: Verify component is assigned
    echo "<h2>Step 3: Verifying component assignment</h2>\n";
    
    $assigned_components = get_post_meta($post_id, '_ccc_components', true);
    if (empty($assigned_components)) {
        throw new Exception("Component assignment failed");
    }
    
    echo "✓ Component is assigned to post\n";
    echo "  - Component ID: " . $assigned_components[0]['id'] . "\n";
    echo "  - Component Name: " . $assigned_components[0]['name'] . "\n";
    
    // Step 4: Delete the component
    echo "<h2>Step 4: Deleting the component</h2>\n";
    
    $delete_result = $wpdb->delete(
        $components_table,
        ['id' => $component_id],
        ['%d']
    );
    
    if ($delete_result === false) {
        throw new Exception("Failed to delete component: " . $wpdb->last_error);
    }
    
    echo "✓ Component deleted from database\n";
    
    // Step 5: Check if component still appears in post meta
    echo "<h2>Step 5: Checking post meta after component deletion</h2>\n";
    
    $remaining_components = get_post_meta($post_id, '_ccc_components', true);
    if (empty($remaining_components)) {
        echo "⚠ Component assignment was removed (this is not what we want)\n";
    } else {
        echo "✓ Component assignment still exists in post meta\n";
        echo "  - Component ID: " . $remaining_components[0]['id'] . "\n";
        echo "  - Component Name: " . $remaining_components[0]['name'] . "\n";
    }
    
    // Step 6: Test the AJAX endpoint to see if it marks deleted components
    echo "<h2>Step 6: Testing AJAX endpoint for deleted components</h2>\n";
    
    // Simulate the AJAX request
    $_POST['action'] = 'ccc_get_posts_with_components';
    $_POST['post_id'] = $post_id;
    $_POST['nonce'] = wp_create_nonce('ccc_nonce');
    
    // Capture output
    ob_start();
    
    // Call the AJAX handler directly
    $ajax_handler = new \CCC\Ajax\AjaxHandler();
    $ajax_handler->getPostsWithComponents();
    
    $ajax_output = ob_get_clean();
    
    // Parse the JSON response
    $response_data = json_decode($ajax_output, true);
    
    if ($response_data && isset($response_data['success']) && $response_data['success']) {
        echo "✓ AJAX endpoint responded successfully\n";
        
        if (isset($response_data['data']['components']) && !empty($response_data['data']['components'])) {
            $component = $response_data['data']['components'][0];
            echo "  - Component ID: " . $component['id'] . "\n";
            echo "  - Component Name: " . $component['name'] . "\n";
            echo "  - Is Deleted: " . ($component['isDeleted'] ? 'YES' : 'NO') . "\n";
            
            if ($component['isDeleted']) {
                echo "✓ SUCCESS: Deleted component is properly marked!\n";
            } else {
                echo "⚠ Component is not marked as deleted\n";
            }
        } else {
            echo "⚠ No components returned from AJAX endpoint\n";
        }
    } else {
        echo "⚠ AJAX endpoint failed or returned error\n";
        echo "Response: " . $ajax_output . "\n";
    }
    
    // Cleanup: Remove test post
    echo "<h2>Cleanup</h2>\n";
    wp_delete_post($post_id, true);
    echo "✓ Test post deleted\n";
    
    echo "<h2>Test Summary</h2>\n";
    echo "The test has completed. Check the results above to verify that:\n";
    echo "1. Components are not automatically removed from posts when deleted\n";
    echo "2. The AJAX endpoint properly marks deleted components with isDeleted flag\n";
    echo "3. The frontend will display deleted components with red background\n";
    
} catch (Exception $e) {
    echo "<h2>Error</h2>\n";
    echo "Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}
?>
