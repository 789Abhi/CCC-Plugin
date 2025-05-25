<?php
/*
Template Name: CCC Component Template
*/

// Ensure helper functions are loaded
if (!function_exists('get_ccc_post_components')) {
    require_once plugin_dir_path(__FILE__) . 'inc/Helpers/TemplateHelpers.php';
}

$post_id = get_the_ID();
$components = get_ccc_post_components($post_id);

global $wpdb;
$theme_dir = get_stylesheet_directory();

get_header();

// Debugging: Log template loading
error_log("CCC Template loaded for post ID: $post_id");

if (have_posts()) :
    while (have_posts()) : the_post();
        ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <header class="entry-header">
                <h1 class="entry-title"><?php the_title(); ?></h1>
            </header>
            <div class="entry-content">
                <?php the_content(); ?>
                
                <!-- Debugging: Output component data -->
                <p>Debug: <?php echo count($components); ?> component(s) assigned to this page.</p>
                <p>Debug: Component order: 
                    <?php 
                    foreach ($components as $comp) {
                        echo $comp['name'] . ' (order: ' . ($comp['order'] ?? 0) . '), ';
                    }
                    ?>
                </p>

                <?php if (empty($components)) : ?>
                    <p>No components assigned to this page. Please assign components in the page editor.</p>
                <?php else : ?>
                    
                    <!-- Display Components in Order (Read-only view) -->
                    <div class="ccc-components-display">
                        <h3>Components Display (in order):</h3>
                        <?php
                        foreach ($components as $index => $component) {
                            $component_id = isset($component['id']) ? intval($component['id']) : 0;
                            if (!$component_id) {
                                echo '<p>Debug: Invalid component ID at index ' . esc_attr($index) . '</p>';
                                error_log("Invalid component ID at index $index for post ID: $post_id");
                                continue;
                            }

                            $handle_name = $component['handle_name'] ?? '';
                            if (!$handle_name) {
                                echo '<p>Debug: No handle found for component ID ' . esc_attr($component_id) . '</p>';
                                error_log("No handle found for component ID $component_id for post ID: $post_id");
                                continue;
                            }

                            $template_file = $theme_dir . '/ccc-templates/' . $handle_name . '.php';
                            if (file_exists($template_file)) {
                                echo '<div class="ccc-component ccc-component-' . esc_attr($handle_name) . '" data-component-order="' . esc_attr($component['order'] ?? $index) . '">';
                                echo '<div class="ccc-component-debug">Component: ' . esc_html($component['name']) . ' (Order: ' . esc_attr($component['order'] ?? $index) . ')</div>';
                                
                                // Set global variables for template use
                                global $ccc_current_component, $ccc_current_post_id;
                                $ccc_current_component = $component;
                                $ccc_current_post_id = $post_id;
                                
                                include $template_file;
                                echo '</div>';
                            } else {
                                echo '<p>Debug: Template file not found: ' . esc_html($template_file) . '</p>';
                                error_log("Template file not found: $template_file for post ID: $post_id");
                            }
                        }
                        ?>
                    </div>

                    <!-- Editable Form (for admin/editing) -->
                    <?php if (current_user_can('edit_posts')) : ?>
                        <hr>
                        <h3>Edit Component Values:</h3>
                        <form id="ccc-component-form" method="post" action="">
                            <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>" />
                            <?php wp_nonce_field('ccc_nonce', 'ccc_nonce'); ?>
                            
                            <?php
                            foreach ($components as $index => $component) {
                                $component_id = isset($component['id']) ? intval($component['id']) : 0;
                                if (!$component_id) continue;

                                $handle_name = $component['handle_name'] ?? '';
                                if (!$handle_name) continue;

                                echo '<div class="ccc-component-form ccc-component-form-' . esc_attr($handle_name) . '">';
                                echo '<h4>Edit: ' . esc_html($component['name']) . '</h4>';
                                
                                // Get fields for this component
                                global $wpdb;
                                $fields = $wpdb->get_results($wpdb->prepare(
                                    "SELECT id, label, name, type FROM {$wpdb->prefix}cc_fields WHERE component_id = %d ORDER BY field_order, created_at",
                                    $component_id
                                ));

                                if ($fields) {
                                    foreach ($fields as $field) {
                                        $value = get_ccc_field($field->name, $post_id, $component_id);
                                        ?>
                                        <div class='ccc-field ccc-field-<?php echo esc_attr($field->name); ?>'>
                                            <label for='ccc_field_<?php echo esc_attr($field->id); ?>'><?php echo esc_html($field->label); ?></label>
                                            <?php if ($field->type === 'text') { ?>
                                                <input type='text' id='ccc_field_<?php echo esc_attr($field->id); ?>' name='ccc_field_values[<?php echo esc_attr($field->id); ?>]' value='<?php echo esc_attr($value ?: ''); ?>' class='ccc-input' />
                                            <?php } elseif ($field->type === 'text-area') { ?>
                                                <textarea id='ccc_field_<?php echo esc_attr($field->id); ?>' name='ccc_field_values[<?php echo esc_attr($field->id); ?>]' class='ccc-textarea' rows='5'><?php echo esc_textarea($value ?: ''); ?></textarea>
                                            <?php } ?>
                                        </div>
                                        <?php
                                    }
                                } else {
                                    echo '<p>No fields defined for this component.</p>';
                                }
                                
                                echo '</div>';
                            }
                            ?>
                            <button type="submit" class="ccc-submit-button">Save Component Values</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </article>
        
        <style>
            .ccc-component {
                margin-bottom: 30px;
                padding: 20px;
                border: 2px solid #e5e7eb;
                border-radius: 8px;
                background-color: #f9fafb;
            }
            .ccc-component-debug {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                padding: 5px 10px;
                margin-bottom: 10px;
                border-radius: 4px;
                font-size: 12px;
                color: #856404;
            }
            .ccc-component-form {
                margin-bottom: 20px;
                padding: 15px;
                border: 1px solid #ddd;
                border-radius: 5px;
                background: #fff;
            }
            .ccc-field {
                margin-bottom: 15px;
            }
            .ccc-field label {
                display: block;
                font-weight: 500;
                margin-bottom: 5px;
                color: #374151;
            }
            .ccc-input, .ccc-textarea {
                width: 100%;
                padding: 8px;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                font-size: 14px;
            }
            .ccc-textarea {
                resize: vertical;
            }
            .ccc-submit-button {
                background-color: #10b981;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
            }
            .ccc-submit-button:hover {
                background-color: #059669;
            }
        </style>
        <?php
    endwhile;
else :
    ?>
    <p>No content found.</p>
    <?php
endif;

get_footer();
