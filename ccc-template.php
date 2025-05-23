<?php
/*
Template Name: CCC Component Template
*/

$post_id = get_the_ID();
$components = get_post_meta($post_id, '_ccc_components', true);

// Ensure $components is an array
if (!is_array($components)) {
    $components = [];
}

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
                <p>Debug: Components meta: <?php echo esc_html(json_encode($components)); ?></p>

                <?php if (empty($components)) : ?>
                    <p>No components assigned to this page. Please assign components in the page editor.</p>
                <?php else : ?>
                    <form id="ccc-component-form" method="post" action="">
                        <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>" />
                        <?php wp_nonce_field('ccc_nonce', 'ccc_nonce'); ?>
                        
                        <?php
                        foreach ($components as $index => $component) {
                            $component_id = isset($component['id']) ? intval($component['id']) : 0;
                            if (!$component_id) {
                                echo '<p>Debug: Invalid component ID at index ' . esc_attr($index) . '</p>';
                                error_log("Invalid component ID at index $index for post ID: $post_id");
                                continue;
                            }

                            $handle_name = $wpdb->get_var($wpdb->prepare(
                                "SELECT handle_name FROM {$wpdb->prefix}cc_components WHERE id = %d",
                                $component_id
                            ));

                            if (!$handle_name) {
                                echo '<p>Debug: No handle found for component ID ' . esc_attr($component_id) . '</p>';
                                error_log("No handle found for component ID $component_id for post ID: $post_id");
                                continue;
                            }

                            $template_file = $theme_dir . '/ccc-templates/' . $handle_name . '.php';
                            if (file_exists($template_file)) {
                                echo '<div class="ccc-component ccc-component-' . esc_attr($handle_name) . '" data-component-order="' . esc_attr($index) . '">';
                                include $template_file;
                                echo '</div>';
                            } else {
                                echo '<p>Debug: Template file not found: ' . esc_html($template_file) . '</p>';
                                error_log("Template file not found: $template_file for post ID: $post_id");
                            }
                        }
                        ?>
                        <button type="submit" class="ccc-submit-button">Save Component Values</button>
                    </form>
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

get_footer();