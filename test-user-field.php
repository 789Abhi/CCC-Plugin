<?php
/**
 * Test User Field
 * 
 * This file tests the user field functionality
 */

// Include WordPress
require_once('../../../wp-load.php');

// Test the UserField class
if (class_exists('CCC\Fields\UserField')) {
    echo "✅ UserField class exists\n";
    
    // Test creating a user field
    $userField = new CCC\Fields\UserField(
        'Test User Field',
        'test_user_field',
        1,
        false,
        'Select a user',
        [
            'role_filter' => ['administrator', 'editor'],
            'multiple' => true,
            'return_type' => 'id',
            'searchable' => true,
            'orderby' => 'display_name',
            'order' => 'ASC'
        ]
    );
    
    echo "✅ UserField instance created successfully\n";
    
    // Test getting configuration
    echo "Role Filter: " . implode(', ', $userField->getRoleFilter()) . "\n";
    echo "Multiple: " . ($userField->isMultiple() ? 'Yes' : 'No') . "\n";
    echo "Return Type: " . $userField->getReturnType() . "\n";
    
    // Test rendering (this will output HTML)
    echo "\n=== User Field Render Test ===\n";
    $rendered = $userField->render(1, 'test_instance', [1, 2]);
    echo $rendered;
    
} else {
    echo "❌ UserField class not found\n";
}

// Test AJAX endpoint
echo "\n=== Testing AJAX Endpoint ===\n";
if (function_exists('wp_ajax_ccc_get_users')) {
    echo "✅ AJAX endpoint exists\n";
} else {
    echo "❌ AJAX endpoint not found\n";
}

// Test getting users
echo "\n=== Testing User Retrieval ===\n";
$users = get_users([
    'role__in' => ['administrator', 'editor'],
    'orderby' => 'display_name',
    'order' => 'ASC',
    'number' => 5
]);

if (!empty($users)) {
    echo "✅ Found " . count($users) . " users:\n";
    foreach ($users as $user) {
        echo "  - {$user->display_name} ({$user->user_email})\n";
    }
} else {
    echo "❌ No users found\n";
}

echo "\n=== Test Complete ===\n";
?> 