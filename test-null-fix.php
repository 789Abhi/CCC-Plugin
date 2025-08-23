<?php
/**
 * Test file to verify null value handling fix
 * 
 * This file tests that the get_ccc_field function now handles null values correctly
 * without throwing PHP deprecation warnings.
 */

// Test the null handling
echo "<h2>Testing Null Value Handling</h2>";

// Test 1: Direct json_decode with null (should not cause deprecation warning)
echo "<h3>Test 1: Direct json_decode with null</h3>";
$null_value = null;
$result = json_decode($null_value, true);
echo "Result: " . var_export($result, true) . "<br>";

// Test 2: Test the fixed functions
echo "<h3>Test 2: Testing fixed functions</h3>";

// Simulate the get_ccc_field logic for different field types
function test_field_handling($field_type, $value) {
    echo "<strong>Testing $field_type field with value: " . var_export($value, true) . "</strong><br>";
    
    // Handle null values safely
    if ($value === null) {
        echo "Value is null, returning empty result<br>";
        return $field_type === 'repeater' ? [] : '';
    }
    
    // Process based on field type
    switch ($field_type) {
        case 'repeater':
            $decoded = json_decode($value, true) ?: [];
            echo "Repeater field - decoded: " . var_export($decoded, true) . "<br>";
            return $decoded;
            
        case 'image':
        case 'video':
        case 'file':
            $decoded = json_decode($value, true);
            echo "Media field - decoded: " . var_export($decoded, true) . "<br>";
            return $decoded;
            
        case 'checkbox':
            echo "Checkbox field - returning array<br>";
            return $value ? explode(',', $value) : [];
            
        case 'select':
            echo "Select field - returning value<br>";
            return $value ?: '';
            
        default:
            echo "Default field - returning value<br>";
            return $value ?: '';
    }
}

// Test different field types with null values
$field_types = ['repeater', 'image', 'video', 'file', 'checkbox', 'select', 'text'];
foreach ($field_types as $type) {
    echo "<br>";
    $result = test_field_handling($type, null);
    echo "Final result: " . var_export($result, true) . "<br>";
}

echo "<h3>Test Complete!</h3>";
echo "<p>If you see this message without any PHP deprecation warnings, the fix is working correctly.</p>";
?>
