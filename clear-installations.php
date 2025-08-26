<?php
/**
 * Clear Installation Records Script
 * 
 * This script clears all existing installation records from the database.
 * Use this only for testing/reset purposes.
 * 
 * Place this file in your plugin directory and access it via browser to clear records.
 * DELETE THIS FILE after use for security.
 */

// Prevent direct access if not in WordPress
if (!defined('ABSPATH')) {
    // If not in WordPress, try to load it
    $wp_load = dirname(__FILE__) . '/../../../wp-load.php';
    if (file_exists($wp_load)) {
        require_once $wp_load;
    } else {
        die('WordPress not found. Please place this file in your plugin directory.');
    }
}

// Check if user is logged in and has admin privileges
if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

// Handle the clear action
if (isset($_POST['clear_installations']) && wp_verify_nonce($_POST['_wpnonce'], 'clear_installations')) {
    global $wpdb;
    
    $installations_table = $wpdb->prefix . 'ccc_plugin_installations';
    $licenses_table = $wpdb->prefix . 'ccc_licenses';
    
    // Delete related licenses first
    $licenses_deleted = $wpdb->query("DELETE FROM $licenses_table");
    
    // Delete all installations
    $installations_deleted = $wpdb->query("DELETE FROM $installations_table");
    
    $message = "Installation records cleared successfully!<br>";
    $message .= "Licenses deleted: " . ($licenses_deleted !== false ? $licenses_deleted : 0) . "<br>";
    $message .= "Installations deleted: " . ($installations_deleted !== false ? $installations_deleted : 0);
    
    $message_type = 'success';
} else {
    $message = '';
    $message_type = '';
}

// Get current installation count
global $wpdb;
$installations_table = $wpdb->prefix . 'ccc_plugin_installations';
$current_count = $wpdb->get_var("SELECT COUNT(*) FROM $installations_table");

?>
<!DOCTYPE html>
<html>
<head>
    <title>Clear Installation Records</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .container { max-width: 600px; margin: 0 auto; }
        .card { background: #f9f9f9; border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
        .button { background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; }
        .button:hover { background: #c82333; }
        .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Clear Installation Records</h1>
        
        <div class="card info">
            <h3>ℹ️ Information</h3>
            <p>This tool allows you to clear all existing installation records from the database.</p>
            <p><strong>Current installation count:</strong> <?php echo $current_count; ?></p>
        </div>
        
        <?php if ($message): ?>
            <div class="card <?php echo $message_type; ?>">
                <h3><?php echo $message_type === 'success' ? '✅ Success' : '⚠️ Warning'; ?></h3>
                <p><?php echo $message; ?></p>
            </div>
        <?php endif; ?>
        
        <div class="card warning">
            <h3>⚠️ Warning</h3>
            <p><strong>This action cannot be undone!</strong> All installation records and related license data will be permanently deleted.</p>
            <p>Only use this if you want to completely reset the installation tracking system.</p>
        </div>
        
        <div class="card">
            <h3>🗑️ Clear All Records</h3>
            <form method="post" onsubmit="return confirm('Are you absolutely sure you want to delete ALL installation records? This cannot be undone!');">
                <?php wp_nonce_field('clear_installations'); ?>
                <input type="submit" name="clear_installations" value="Clear All Installation Records" class="button">
            </form>
        </div>
        
        <div class="card">
            <h3>📋 What This Does</h3>
            <ul>
                <li>Deletes all records from <code>wp_ccc_plugin_installations</code> table</li>
                <li>Deletes all records from <code>wp_ccc_licenses</code> table</li>
                <li>Resets the installation tracking system</li>
                <li>Allows you to start fresh with installation management</li>
            </ul>
        </div>
        
        <div class="card">
            <h3>🔙 After Clearing</h3>
            <p>After clearing the records:</p>
            <ol>
                <li>Go to <strong>Custom Craft Settings → Master Password</strong></li>
                <li>Set your master password</li>
                <li>Use the "Create Initial Installation Record" button to create a new record</li>
                <li>Enable/disable automatic tracking as needed</li>
            </ol>
        </div>
        
        <div class="card">
            <h3>🚨 Security Note</h3>
            <p><strong>Important:</strong> Delete this file after use to prevent unauthorized access to your database.</p>
            <p>This file provides direct database access and should not remain on your server.</p>
        </div>
    </div>
</body>
</html>
