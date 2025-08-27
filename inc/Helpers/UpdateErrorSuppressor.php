<?php
/**
 * Update Error Suppressor
 * Prevents update error messages from appearing in WordPress admin
 */

namespace CCC\Helpers;

class UpdateErrorSuppressor {
    
    public static function init() {
        // Suppress update error messages to prevent red alerts
        add_filter('pre_site_transient_update_plugins', [self::class, 'suppress_plugin_errors'], 10, 1);
        add_filter('site_transient_update_plugins', [self::class, 'suppress_plugin_errors'], 10, 1);
        
        // Handle specific update checker errors
        add_filter('puc_request_info_result-custom-craft-component', [self::class, 'handle_update_checker_errors'], 10, 2);
        
        // Handle HTTP request failures gracefully
        add_filter('http_request_args', [self::class, 'handle_http_requests'], 10, 2);
        
        // Suppress specific cURL timeout errors
        add_filter('puc_request_info_result-custom-craft-component', [self::class, 'handle_curl_errors'], 15, 2);
        
        // Suppress automatic update check results (only show manual check results)
        add_filter('site_transient_update_plugins', [self::class, 'suppress_automatic_update_results'], 10, 1);
        add_filter('pre_site_transient_update_plugins', [self::class, 'suppress_automatic_update_results'], 10, 1);
    }
    
    public static function suppress_plugin_errors($transient) {
        if (isset($transient->response['custom-craft-component'])) {
            // Remove any error responses to prevent alerts
            if (isset($transient->response['custom-craft-component']->errors)) {
                unset($transient->response['custom-craft-component']->errors);
            }
        }
        return $transient;
    }
    
    public static function handle_update_checker_errors($result, $url) {
        if (is_wp_error($result)) {
            // Return null instead of error to prevent alerts
            return null;
        }
        return $result;
    }
    
    public static function handle_http_requests($args, $url) {
        if (strpos($url, 'githubusercontent.com') !== false) {
            $args['timeout'] = 15;
            $args['sslverify'] = false;
        }
        return $args;
    }
    
    public static function handle_curl_errors($result, $url) {
        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();
            if (in_array($error_code, ['http_request_failed', 'cURL error 28'])) {
                // Return null instead of error to prevent alerts
                return null;
            }
        }
        return $result;
    }
}
