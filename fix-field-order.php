<?php
/**
 * Fix Field Order Script
 * 
 * This script fixes any existing fields that have field_order = 0
 * by reassigning proper order numbers based on creation time.
 * 
 * Run this script once to fix existing data.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once('../../../wp-load.php');
}

// Ensure we're in admin context
if (!current_user_can('manage_options')) {
    wp_die('Insufficient permissions');
}

global $wpdb;

echo "<h2>Fixing Field Order Issues</h2>";

// Get all components
$components_table = $wpdb->prefix . 'cc_components';
$fields_table = $wpdb->prefix . 'cc_fields';

$components = $wpdb->get_results("SELECT id, name FROM $components_table ORDER BY id");

if (empty($components)) {
    echo "<p>No components found.</p>";
    return;
}

$total_fixed = 0;

foreach ($components as $component) {
    echo "<h3>Processing Component: {$component->name} (ID: {$component->id})</h3>";
    
    // Get all fields for this component, ordered by created_at
    $fields = $wpdb->get_results($wpdb->prepare(
        "SELECT id, label, field_order, created_at FROM $fields_table 
         WHERE component_id = %d 
         ORDER BY created_at ASC",
        $component->id
    ));
    
    if (empty($fields)) {
        echo "<p>No fields found for this component.</p>";
        continue;
    }
    
    echo "<ul>";
    $current_order = 0;
    $fixed_count = 0;
    
    foreach ($fields as $field) {
        $old_order = $field->field_order;
        
        // Update field order if it's 0 or if we need to reorder
        if ($old_order == 0 || $old_order != $current_order) {
            $wpdb->update(
                $fields_table,
                ['field_order' => $current_order],
                ['id' => $field->id],
                ['%d'],
                ['%d']
            );
            
            echo "<li>Field '{$field->label}' (ID: {$field->id}): {$old_order} → {$current_order}</li>";
            $fixed_count++;
        } else {
            echo "<li>Field '{$field->label}' (ID: {$field->id}): {$old_order} (no change needed)</li>";
        }
        
        $current_order++;
    }
    
    echo "</ul>";
    echo "<p><strong>Fixed {$fixed_count} fields for this component.</strong></p>";
    $total_fixed += $fixed_count;
}

echo "<h2>Summary</h2>";
echo "<p><strong>Total fields fixed: {$total_fixed}</strong></p>";
echo "<p>Field order has been corrected. New fields will now be added at the end of the list.</p>";

// Clean up
echo "<p><a href='" . admin_url('admin.php?page=custom-craft-component') . "'>Return to Plugin</a></p>";
?> 