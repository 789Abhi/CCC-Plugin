<?php
namespace CCC\Services;

defined('ABSPATH') || exit;

class PostTypeTemplateService {
    
    /**
     * Create post type template
     */
    public function createPostTypeTemplate($post_type, $components = []) {
        $theme_dir = get_stylesheet_directory();
        $templates_dir = $theme_dir . '/ccc-single-page-templates';
        
        if (!file_exists($templates_dir)) {
            wp_mkdir_p($templates_dir);
        }
        
        $this->createHtaccessFile($templates_dir);
        $this->createIndexFile($templates_dir);
        
        // Store the components assigned to this post type
        if (!empty($components) && is_array($components)) {
            $component_ids = array_map(function($component) {
                return is_array($component) ? $component['id'] : $component->id;
            }, $components);
            update_option('_ccc_post_type_components_' . $post_type, $component_ids);
            error_log("CCC PostTypeTemplateService: Stored components for post type $post_type: " . json_encode($component_ids));
        }
        
        // Create only PHP template (no HTML templates)
        $php_template_file = $templates_dir . '/single-' . $post_type . '.php';
        
        // Create PHP template
        $php_template_content = $this->generatePostTypeTemplateContent($post_type, $components);
        if (!file_put_contents($php_template_file, $php_template_content)) {
            error_log("CCC PostTypeTemplateService: Failed to write PHP post type template: $php_template_file");
            throw new \Exception('Failed to create post type template file');
        } else {
            error_log("CCC PostTypeTemplateService: Successfully created PHP template for post type: $post_type");
        }
        
        return $php_template_file;
    }
    
    /**
     * Remove post type template
     */
    public function removePostTypeTemplate($post_type) {
        $theme_dir = get_stylesheet_directory();
        $templates_dir = $theme_dir . '/ccc-single-page-templates';
        
        // Clean up stored components for this post type
        delete_option('_ccc_post_type_components_' . $post_type);
        error_log("CCC PostTypeTemplateService: Cleaned up stored components for post type: $post_type");
        
        if (!file_exists($templates_dir)) {
            error_log("CCC PostTypeTemplateService: Templates directory does not exist: $templates_dir");
            return false;
        }
        
        $deleted_files = [];
        
        // Remove only PHP template
        $php_template_file = $templates_dir . '/single-' . $post_type . '.php';
        if (file_exists($php_template_file)) {
            if (unlink($php_template_file)) {
                $deleted_files[] = 'PHP';
                error_log("CCC PostTypeTemplateService: Successfully deleted PHP template for post type: $post_type");
            } else {
                error_log("CCC PostTypeTemplateService: Failed to delete PHP template for post type: $post_type");
            }
        }
        
        // Check if templates directory is empty and remove it if so
        $remaining_files = array_diff(scandir($templates_dir), ['.', '..']);
        if (empty($remaining_files)) {
            // Remove .htaccess and index.php first
            $htaccess_file = $templates_dir . '/.htaccess';
            $index_file = $templates_dir . '/index.php';
            
            if (file_exists($htaccess_file)) {
                unlink($htaccess_file);
            }
            if (file_exists($index_file)) {
                unlink($index_file);
            }
            
            // Remove the directory
            if (rmdir($templates_dir)) {
                error_log("CCC PostTypeTemplateService: Removed empty templates directory: $templates_dir");
            }
        }
        
        if (!empty($deleted_files)) {
            error_log("CCC PostTypeTemplateService: Deleted " . implode(' and ', $deleted_files) . " templates for post type: $post_type");
            return true;
        }
        
        error_log("CCC PostTypeTemplateService: No templates found to delete for post type: $post_type");
        return false;
    }
    
    /**
     * Generate the content for a post type template file
     */
    private function generatePostTypeTemplateContent($post_type, $components = []) {
        $post_type_object = get_post_type_object($post_type);
        $post_type_label = $post_type_object ? $post_type_object->labels->singular_name : ucfirst(str_replace(['_', '-'], ' ', $post_type));
        
        $components_list = '';
        if (!empty($components)) {
            $components_list = "\n * Assigned Components:\n";
            foreach ($components as $component) {
                $components_list .= " * - " . $component['name'] . " (" . $component['handle_name'] . ")\n";
            }
        }
        
        return "<?php\n"
            . "/**\n"
            . " * Post Type Template: $post_type_label\n"
            . " * Post Type: $post_type\n"
            . " * \n"
            . " * This template is automatically generated by Custom Craft Component\n"
            . " * when components are assigned to this post type.\n"
            . " * \n"
            . " * You can customize this template to match your design needs.\n"
            . " * \n"
            . " * Template Hierarchy:\n"
            . " * 1. single-{post_type}.php (this file)\n"
            . " * 2. single.php\n"
            . " * 3. index.php\n"
            . $components_list
            . " */\n\n"
            . "// Prevent direct access\n"
            . "if (!defined('ABSPATH')) {\n"
            . "    exit;\n"
            . "}\n\n"
            . "// Enqueue CCC template styles\n"
            . "wp_enqueue_style('ccc-post-type-templates', plugins_url('inc/Views/post-type-templates.css', dirname(dirname(__FILE__))), [], '1.0.0');\n\n"
            . "get_header();\n\n"
            . "// Get the current post\n"
            . "\$post = get_post();\n"
            . "if (!\$post || \$post->post_type !== '$post_type') {\n"
            . "    wp_die('Invalid post type.');\n"
            . "}\n\n"
            . "// Debug logging\n"
            . "error_log('CCC Template: Post ID: ' . \$post->ID);\n\n"
            . "// Always render components for this post type (as requested by user)\n"
            . "if (function_exists('render_ccc_components')) {\n"
            . "    error_log('CCC Template: Calling render_ccc_components for post ID: ' . \$post->ID);\n"
            . "    render_ccc_components(\$post->ID);\n"
            . "    error_log('CCC Template: render_ccc_components completed');\n"
            . "} else {\n"
            . "    error_log('CCC Template: render_ccc_components function not found!');\n"
            . "    echo '<p>CCC Components function not available.</p>';\n"
            . "}\n\n"
            . "get_footer();\n";
    }
    
    /**
     * Create .htaccess file for URL rewriting
     */
    private function createHtaccessFile($templates_dir) {
        $htaccess_file = $templates_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "RewriteEngine On\nRewriteBase /\n";
            file_put_contents($htaccess_file, $htaccess_content);
        }
    }
    
    /**
     * Create index.php file to prevent directory listing
     */
    private function createIndexFile($templates_dir) {
        $index_file = $templates_dir . '/index.php';
        if (!file_exists($index_file)) {
            $index_content = "<?php\n// Silence is golden.\n";
            file_put_contents($index_file, $index_content);
        }
    }
}
