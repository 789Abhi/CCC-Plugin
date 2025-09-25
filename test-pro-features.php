<?php
/**
 * Test script for PRO features integration
 * Add this to your WordPress theme's functions.php temporarily to test
 */

function test_ccc_pro_features() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    echo '<div style="background: #f0f0f0; padding: 20px; margin: 20px; border: 1px solid #ccc;">';
    echo '<h3>CCC PRO Features Test</h3>';
    
    // Test license validation
    $license_validator = new \CCC\Services\LicenseValidator();
    $license_key = get_option('ccc_license_key', '');
    
    echo '<h4>1. License Validation Test</h4>';
    $validation = $license_validator->validate_license($license_key);
    echo '<pre>' . print_r($validation, true) . '</pre>';
    
    // Test field access
    $pro_access_service = new \CCC\Services\ProFieldAccessService();
    
    echo '<h4>2. Field Access Tests</h4>';
    
    $test_fields = ['text', 'repeater', 'gallery', 'ai_generator'];
    foreach ($test_fields as $field_type) {
        $access = $pro_access_service->can_access_field($field_type);
        echo '<strong>' . $field_type . ':</strong> ';
        echo $access['canAccess'] ? '✅ Accessible' : '❌ Blocked';
        echo ' - ' . $access['message'] . '<br>';
    }
    
    // Test field configuration
    echo '<h4>3. Field Configuration Test</h4>';
    $manifest_service = new \CCC\Services\ManifestService();
    $config = $manifest_service->get_field_configuration();
    echo '<pre>' . print_r($config, true) . '</pre>';
    
    echo '</div>';
}

// Add test to admin menu
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'CCC PRO Test',
        'CCC PRO Test',
        'manage_options',
        'ccc-pro-test',
        'test_ccc_pro_features'
    );
});
