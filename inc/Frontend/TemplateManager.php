<?php
namespace CCC\Frontend;

defined('ABSPATH') || exit;

class TemplateManager {
    public function init() {
        add_filter('theme_page_templates', [$this, 'addCccTemplate']);
        add_filter('template_include', [$this, 'loadCccTemplate']);
    }

    public function addCccTemplate($templates) {
        // Only add CCC template option if we're editing a page/post
        global $post;
        
        // Check if we're in the admin area and editing a post/page
        if (is_admin() && $post && $post->ID) {
            // Check if this post has components assigned
            $components = get_post_meta($post->ID, '_ccc_components', true);
            
            if (is_array($components) && !empty($components)) {
                $templates['ccc-template.php'] = 'CCC Component Template';
            }
        }
        
        return $templates;
    }

    public function loadCccTemplate($template) {
        if (is_singular()) {
            $post_id = get_the_ID();
            $page_template = get_post_meta($post_id, '_wp_page_template', true);
            
            error_log("Checking template for post ID $post_id: $page_template");
            
            if ($page_template === 'ccc-template.php') {
                $plugin_template = plugin_dir_path(__FILE__) . '../../ccc-template.php';
                if (file_exists($plugin_template)) {
                    error_log("Loading CCC template: $plugin_template");
                    return $plugin_template;
                } else {
                    error_log("CCC template not found: $plugin_template");
                }
            }
        }
        return $template;
    }
}
