<?php
namespace CCC\Helpers;

defined('ABSPATH') || exit;

class TemplateHelpers {
    // Placeholder to avoid breaking other plugin functionality
    public static function getCccPostComponents($post_id = null) {
        return [];
    }
}

// Global helper function
if (!function_exists('get_ccc_post_components')) {
    function get_ccc_post_components($post_id = null) {
        return \CCC\Helpers\TemplateHelpers::getCccPostComponents($post_id);
    }
}