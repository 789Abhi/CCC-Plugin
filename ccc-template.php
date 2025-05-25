<?php
/*
Template Name: CCC Component Template
*/

// Debugging setup
error_log("Template loaded for post ID: " . get_the_ID());

$post_id = get_the_ID();
$theme_dir = get_stylesheet_directory();
error_log("Active theme directory: $theme_dir");

// Retrieve components directly from meta
$components = get_post_meta($post_id, '_ccc_components', true);
error_log("_ccc_components meta for post ID $post_id: " . print_r($components, true));

// Handle serialized data
if (is_string($components) && !empty($components)) {
    $components = maybe_unserialize($components);
}

// Ensure components is an array
if (!is_array($components)) {
    $components = [];
    error_log("Components normalized to empty array for post ID $post_id");
}

// Sort by order
usort($components, function($a, $b) {
    return ($a['order'] ?? 0) - ($b['order'] ?? 0);
});
error_log("Processed components for post ID $post_id: " . print_r($components, true));

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
                            
                            if (!$component_id || !$handle_name) {
                                error_log("Skipping component at index $index for post ID $post_id: Invalid ID or handle_name");
                                continue;
                            }

                            $template_file = $theme_dir . '/ccc-templates/' . $handle_name . '.php';
                            if (file_exists($template_file)) {
                                error_log("Including template file: $template_file");
                                ?>
                                <div class="ccc-component ccc-component-<?php echo esc_attr($handle_name); ?>" data-component-order="<?php echo esc_attr($component['order'] ?? $index); ?>">
                                    <?php
                                    global $ccc_current_component, $ccc_current_post_id;
                                    $ccc_current_component = $component;
                                    $ccc_current_post_id = $post_id;
                                    include $template_file;
                                    ?>
                                </div>
                                <?php
                            } else {
                                error_log("Template file not found: $template_file for post ID: $post_id");
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