<?php
// One-time migration tool for CCC: Convert nested fields in config.nested_fields to real DB rows
// Place this file in your plugin directory and visit /wp-admin/?ccc-migrate-nested-fields=1 as an admin

use CCC\Core\Database;

require_once __DIR__ . '/inc/Core/Database.php';

add_action('admin_init', function() {
    if (!is_admin() || !current_user_can('manage_options')) return;
    if (!isset($_GET['ccc-migrate-nested-fields'])) return;
    
    echo '<div style="padding:2em;font-family:monospace;background:#fff;">';
    echo '<h2>CCC Nested Fields Migration</h2>';
    try {
        Database::migrateAllNestedFieldsToRows();
        echo '<p style="color:green;font-weight:bold;">Migration complete! All nested fields are now real DB rows.</p>';
    } catch (Exception $e) {
        echo '<p style="color:red;">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    echo '<p><a href="' . esc_url(admin_url()) . '">Back to Dashboard</a></p>';
    echo '</div>';
    exit;
}); 