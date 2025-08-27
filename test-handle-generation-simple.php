<?php
/**
 * Simple test for handle generation
 */

// Test the handle generation logic
function testHandleGeneration() {
    echo "<h1>Handle Generation Test</h1>";
    
    // Test cases
    $test_cases = [
        "hero section",
        "Hero Section", 
        "Hero",
        "hero_section",
        "hero_sectio",
        "Test Component 123",
        "Special@#$%^&*()Characters",
        "Multiple   Spaces",
        "UPPERCASE TEXT"
    ];
    
    echo "<h2>Test Cases:</h2>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Input</th><th>Expected</th><th>Actual</th><th>Match?</th></tr>";
    
    foreach ($test_cases as $input) {
        $expected = strtolower(preg_replace('/\s+/', '_', preg_replace('/[^a-z0-9_]/', '', strtolower($input))));
        $expected = trim($expected, '_');
        
        // Simulate the sanitizeHandle method
        $actual = strtolower($input);
        $actual = preg_replace('/\s+/', '_', $actual);
        $actual = preg_replace('/[^a-z0-9_]/', '', $actual);
        $actual = preg_replace('/_+/', '_', $actual);
        $actual = trim($actual, '_');
        
        $match = ($expected === $actual) ? "✓" : "✗";
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($input) . "</td>";
        echo "<td>" . htmlspecialchars($expected) . "</td>";
        echo "<td>" . htmlspecialchars($actual) . "</td>";
        echo "<td style='text-align: center;'>$match</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Test the specific case that's failing
    echo "<h2>Specific Test Case:</h2>";
    $input = "hero section";
    echo "<p><strong>Input:</strong> \"$input\"</p>";
    
    $step1 = strtolower($input);
    echo "<p><strong>Step 1 (lowercase):</strong> \"$step1\"</p>";
    
    $step2 = preg_replace('/\s+/', '_', $step1);
    echo "<p><strong>Step 2 (spaces to underscores):</strong> \"$step2\"</p>";
    
    $step3 = preg_replace('/[^a-z0-9_]/', '', $step2);
    echo "<p><strong>Step 3 (remove special chars):</strong> \"$step3\"</p>";
    
    $step4 = preg_replace('/_+/', '_', $step3);
    echo "<p><strong>Step 4 (multiple underscores):</strong> \"$step4\"</p>";
    
    $step5 = trim($step4, '_');
    echo "<p><strong>Step 5 (trim underscores):</strong> \"$step5\"</p>";
    
    echo "<p><strong>Final Result:</strong> \"$step5\"</p>";
}

// Run the test
testHandleGeneration();
?>
