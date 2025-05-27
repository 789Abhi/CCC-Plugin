<?php
namespace CCC\Helpers;

defined('ABSPATH') || exit;

class TemplateHelpers {
    public static function getCccPostComponents($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        $components = get_post_meta($post_id, '_ccc_components', true);
        if (!is_array($components)) {
            $components = [];
        }
        
        // Sort by order
        usort($components, function($a, $b) {
            return ($a['order'] ?? 0) - ($b['order'] ?? 0);
        });
        
        return $components;
    }
}

// Load global helpers if not already loaded
$global_helpers_file = plugin_dir_path(__FILE__) . 'GlobalHelpers.php';
if (file_exists($global_helpers_file) && !function_exists('get_ccc_field')) {
    require_once $global_helpers_file;
}
