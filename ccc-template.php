<?php
/*
Template Name: CCC Component Template

USAGE EXAMPLES:

1. Get a single field value:
   <?php $title = get_ccc_field('title'); ?>

2. Get repeater items (automatically filters out hidden items):
   <?php 
   $team_members = get_ccc_repeater_items('team_members');
   foreach ($team_members as $member) {
       echo $member['name'];
       echo $member['role'];
   }
   ?>

3. Get all fields for current post:
   <?php $all_fields = get_ccc_fields(); ?>
*/

// Ensure helper functions are loaded FIRST
$global_helpers_file = plugin_dir_path(__FILE__) . 'inc/Helpers/GlobalHelpers.php';
if (file_exists($global_helpers_file) && !function_exists('get_ccc_field')) {
    require_once $global_helpers_file;
}

$helper_file = plugin_dir_path(__FILE__) . 'inc/Helpers/TemplateHelpers.php';
if (file_exists($helper_file)) {
    require_once $helper_file;
}

$post_id = get_the_ID();
$theme_dir = get_stylesheet_directory();

// Retrieve components directly from meta
$components = get_post_meta($post_id, '_ccc_components', true);

// Handle serialized data
if (is_string($components) && !empty($components)) {
    $components = maybe_unserialize($components);
}

// Ensure components is an array
if (!is_array($components)) {
    $components = [];
}

// Sort by order
usort($components, function($a, $b) {
    return ($a['order'] ?? 0) - ($b['order'] ?? 0);
});

// Fallback for missing header
if (!function_exists('get_header')) {
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <title><?php wp_title(); ?></title>
        <?php wp_head(); ?>
    </head>
    <body <?php body_class(); ?>>
    <?php
} else {
    get_header();
}

if (have_posts()) :
    while (have_posts()) : the_post();
        ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <div class="entry-content">
                <?php if (empty($components)) : ?>
                    <p>No components assigned to this page. Please assign components in the page editor.</p>
                <?php else : ?>
                    <!-- Display Components in Order -->
                    <div class="ccc-components-display">
                        <?php
                        foreach ($components as $index => $component) {
                            $component_id = isset($component['id']) ? intval($component['id']) : 0;
                            $handle_name = $component['handle_name'] ?? '';
                            $instance_id = $component['instance_id'] ?? ('legacy_' . $index);
                            // Skip hidden components
                            if (!empty($component['isHidden'])) {
                                continue;
                            }
                            if (!$component_id || !$handle_name) {
                                error_log("CCC: Invalid component data at index $index");
                                continue;
                            }

                            $template_file = $theme_dir . '/ccc-templates/' . $handle_name . '.php';
                            if (file_exists($template_file)) {
                                ?>
                                <div class="ccc-component ccc-component-<?php echo esc_attr($handle_name); ?>" 
                                     data-component-order="<?php echo esc_attr($component['order'] ?? $index); ?>"
                                     data-instance-id="<?php echo esc_attr($instance_id); ?>">
                                    <?php
                                    // Set global context for helper functions
                                    global $ccc_current_component, $ccc_current_post_id, $ccc_current_instance_id;
                                    $ccc_current_component = $component;
                                    $ccc_current_post_id = $post_id;
                                    $ccc_current_instance_id = $instance_id;
                                    
                                    // Ensure helper functions are available before including template
                                    if (!function_exists('get_ccc_field')) {
                                        error_log("CCC: Helper functions not available, loading again");
                                        $global_helpers_file = plugin_dir_path(__FILE__) . 'inc/Helpers/GlobalHelpers.php';
                                        if (file_exists($global_helpers_file)) {
                                            require_once $global_helpers_file;
                                        }
                                    }
                                    
                                    error_log("CCC: Including template: $template_file for component ID: $component_id, instance: $instance_id");
                                    include $template_file;
                                    ?>
                                </div>
                                <?php
                            } else {
                                error_log("CCC: Template file not found: $template_file for post ID: $post_id");
                                ?>
                                <div class="ccc-component-error">
                                    <p>Template file not found for component: <?php echo esc_html($handle_name); ?></p>
                                    <p>Expected location: <?php echo esc_html($template_file); ?></p>
                                </div>
                                <?php
                            }
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </article>
        <?php
    endwhile;
else :
    ?>
    <p>No content found.</p>
    <?php
endif;

// Fallback for missing footer
if (!function_exists('get_footer')) {
    ?>
    <?php wp_footer(); ?>
    </body>
    </html>
    <?php
} else {
    get_footer();
}
