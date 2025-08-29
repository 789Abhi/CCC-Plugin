<?php
namespace CCC\Frontend;

defined('ABSPATH') || exit;

class TemplateLoader {
    
    /**
     * Initialize the template loader
     */
    public function __construct() {
        // Hook into the template hierarchy with high priority
        add_filter('template_include', [$this, 'loadPostTypeTemplate'], 1);
        add_filter('single_template', [$this, 'loadSinglePostTypeTemplate'], 1);
        add_filter('get_single_template', [$this, 'getSingleTemplate'], 99);
        
        // Add theme_templates filter to register our custom templates
        add_filter('theme_templates', [$this, 'registerCustomTemplates'], 99);
        
        // Add content filter to inject CCC components (compatible with all themes)
        add_filter('the_content', [$this, 'injectCCCComponents'], 10);
        
        error_log("CCC TemplateLoader: Initialized with filters (PHP templates only)");
    }
    
    /**
     * Load post type template from ccc-single-page-templates directory
     */
    public function loadPostTypeTemplate($template) {
        global $post;
        
        if (!$post || !is_single()) {
            error_log("CCC TemplateLoader: Not a single post or no post object");
            return $template;
        }
        
        $post_type = $post->post_type;
        error_log("CCC TemplateLoader: Processing post type: $post_type for post ID: {$post->ID}");
        
        // Check if we have a custom template for this post type
        $custom_template = $this->getPostTypeTemplatePath($post_type);
        error_log("CCC TemplateLoader: Custom template path: $custom_template");
        
        if ($custom_template && file_exists($custom_template)) {
            error_log("CCC TemplateLoader: Loading custom template for post type: $post_type");
            return $custom_template;
        } else {
            error_log("CCC TemplateLoader: No custom template found for post type: $post_type");
        }
        
        return $template;
    }
    
    /**
     * Load single post type template
     */
    public function loadSinglePostTypeTemplate($template) {
        global $post;
        
        if (!$post) {
            error_log("CCC TemplateLoader: No post object in loadSinglePostTypeTemplate");
            return $template;
        }
        
        $post_type = $post->post_type;
        error_log("CCC TemplateLoader: loadSinglePostTypeTemplate called for post type: $post_type");
        error_log("CCC TemplateLoader: Current template: $template");
        
        // Check if we have a custom template for this post type
        $custom_template = $this->getPostTypeTemplatePath($post_type);
        error_log("CCC TemplateLoader: Custom template path: $custom_template");
        
        if ($custom_template && file_exists($custom_template)) {
            error_log("CCC TemplateLoader: Loading custom single template for post type: $post_type");
            error_log("CCC TemplateLoader: Returning custom template: $custom_template");
            return $custom_template;
        } else {
            error_log("CCC TemplateLoader: Custom template not found or doesn't exist for post type: $post_type");
        }
        
        return $template;
    }
    
    /**
     * Get single template from our custom directory
     */
    public function getSingleTemplate($templates) {
        global $post;
        
        if (!$post) {
            return $templates;
        }
        
        $post_type = $post->post_type;
        error_log("CCC TemplateLoader: getSingleTemplate called for post type: $post_type");
        
        // Check if we have a custom template for this post type
        $custom_template = $this->getPostTypeTemplatePath($post_type);
        
        if ($custom_template && file_exists($custom_template)) {
            error_log("CCC TemplateLoader: getSingleTemplate found custom template for post type: $post_type");
            return [$custom_template];
        }
        
        return $templates;
    }
    
    /**
     * Register our custom templates with WordPress
     */
    public function registerCustomTemplates($templates) {
        error_log("CCC TemplateLoader: registerCustomTemplates called");
        
        // Get all available post type templates
        $custom_templates = $this->getAvailablePostTypeTemplates();
        
        foreach ($custom_templates as $post_type) {
            $template_path = $this->getPostTypeTemplatePath($post_type);
            if ($template_path) {
                $templates[$template_path] = "CCC Custom Template - $post_type";
                error_log("CCC TemplateLoader: Registered custom template for post type: $post_type");
            }
        }
        
        return $templates;
    }
    
    /**
     * Inject CCC components into post content (compatible with all themes)
     */
    public function injectCCCComponents($content) {
        // Only inject on single post pages
        if (!is_single()) {
            return $content;
        }
        
        global $post;
        if (!$post) {
            return $content;
        }
        
        error_log("CCC TemplateLoader: injectCCCComponents called for post ID: {$post->ID}, post type: {$post->post_type}");
        
        // Check if this post has components assigned
        $assigned_components = get_post_meta($post->ID, '_ccc_components', true);
        $assigned_via_post_type = get_post_meta($post->ID, '_ccc_assigned_via_post_type', true);
        
        error_log("CCC TemplateLoader: Post {$post->ID} - assigned_components: " . json_encode($assigned_components) . ", assigned_via_post_type: " . json_encode($assigned_via_post_type));
        
        if (empty($assigned_components) && empty($assigned_via_post_type)) {
            error_log("CCC TemplateLoader: Post {$post->ID} has no components assigned, returning original content");
            return $content;
        }
        
        // Check if we have a custom template for this post type
        $custom_template_exists = $this->hasPostTypeTemplate($post->post_type);
        
        error_log("CCC TemplateLoader: Post {$post->ID} - custom template exists: " . ($custom_template_exists ? 'YES' : 'NO'));
        
        if ($custom_template_exists) {
            error_log("CCC TemplateLoader: Injecting CCC components into content for post type: {$post->post_type}");
            
            // Start output buffering
            ob_start();
            
            // Add CSS
            wp_enqueue_style('ccc-post-type-templates', plugins_url('inc/Views/post-type-templates.css', dirname(dirname(__FILE__))), [], '1.0.0');
            
            // Render components
            echo '<div class="ccc-components-container">';
            echo '<div class="ccc-components">';
            if (function_exists('render_ccc_components')) {
                error_log("CCC TemplateLoader: Calling render_ccc_components for post ID: {$post->ID}");
                render_ccc_components($post->ID);
            } else {
                error_log("CCC TemplateLoader: render_ccc_components function not found!");
            }
            echo '</div>'; // .ccc-components
            echo '</div>'; // .ccc-components-container
            
            // Get the rendered components
            $components_html = ob_get_clean();
            
            error_log("CCC TemplateLoader: Generated components HTML length: " . strlen($components_html));
            
            // Inject components before the original content
            $content = $components_html . $content;
        }
        
        return $content;
    }
    
    /**
     * Get the path to a post type template
     */
    private function getPostTypeTemplatePath($post_type) {
        $theme_dir = get_stylesheet_directory();
        
        // Check for PHP template only
        $php_template_path = $theme_dir . '/ccc-single-page-templates/single-' . $post_type . '.php';
        if (file_exists($php_template_path)) {
            return $php_template_path;
        }
        
        return false;
    }
    
    /**
     * Check if a post type has a custom template
     */
    public function hasPostTypeTemplate($post_type) {
        $template_path = $this->getPostTypeTemplatePath($post_type);
        return $template_path !== false;
    }
    
    /**
     * Get all available post type templates
     */
    public function getAvailablePostTypeTemplates() {
        $theme_dir = get_stylesheet_directory();
        $templates_dir = $theme_dir . '/ccc-single-page-templates';
        
        if (!file_exists($templates_dir)) {
            return [];
        }
        
        $templates = [];
        
        // Look for PHP templates only
        $files = glob($templates_dir . '/single-*.php');
        
        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match('/^single-(.+)\.php$/', $filename, $matches)) {
                $post_type = $matches[1];
                // Avoid duplicates
                if (!in_array($post_type, $templates)) {
                    $templates[] = $post_type;
                }
            }
        }
        
        return $templates;
    }
}
