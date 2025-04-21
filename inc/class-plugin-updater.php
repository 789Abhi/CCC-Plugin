<?php
class Custom_Craft_Component_Updater {
    private $remote_manifest_url = 'https://raw.githubusercontent.com/789Abhi/CCC-Plugin/Master/manifest.json';
    private $plugin_slug = 'custom-craft-component';
    private $version_option = 'ccc_plugin_build_version';
    private $last_check_option = 'ccc_plugin_last_update_check';
    private $plugin_file = 'custom-craft-component/custom-craft-component.php';  // Add this

    public function __construct() {
        // Hook into WordPress update system
        add_filter('site_transient_update_plugins', [$this, 'modify_update_transient']);
        add_filter('plugin_row_meta', [$this, 'plugin_row_meta'], 10, 2);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        
        // Your existing hooks
        add_action('admin_init', [$this, 'schedule_update_check']);
        add_action('ccc_plugin_update_check', [$this, 'check_for_updates']);
        add_action('admin_notices', [$this, 'update_notice']);
        add_action('wp_ajax_ccc_plugin_manual_update', [$this, 'manual_update_handler']);
        
        if (!wp_next_scheduled('ccc_plugin_update_check')) {
            wp_schedule_event(time(), 'hourly', 'ccc_plugin_update_check');
        }
    }

    public function modify_update_transient($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote_version = $this->get_remote_manifest();
        
        if (!$remote_version) {
            return $transient;
        }

        $current_version = get_plugin_data(WP_PLUGIN_DIR . '/' . $this->plugin_file)['Version'];
        
        if (version_compare($current_version, $remote_version['version'], '<')) {
            $plugin_slug = $this->plugin_file;
            $transient->response[$plugin_slug] = (object) [
                'new_version' => $remote_version['version'],
                'package' => $remote_version['download_url'],
                'slug' => $this->plugin_slug,
                'url' => 'https://github.com/789Abhi/CCC-Plugin',
                'plugin' => $plugin_slug
            ];
        }

        return $transient;
    }

    public function plugin_row_meta($plugin_meta, $plugin_file) {
        if ($plugin_file !== $this->plugin_file) {
            return $plugin_meta;
        }

        $plugin_meta[] = sprintf(
            '<a href="%s">%s</a>',
            'https://github.com/789Abhi/CCC-Plugin',
            __('View on GitHub', 'custom-craft-component')
        );

        return $plugin_meta;
    }

    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || $args->slug !== $this->plugin_slug) {
            return $result;
        }

        $remote_version = $this->get_remote_manifest();
        
        if (!$remote_version) {
            return $result;
        }

        $plugin_info = [
            'name' => 'Custom Craft Component',
            'slug' => $this->plugin_slug,
            'version' => $remote_version['version'],
            'author' => 'Your Name',
            'download_link' => $remote_version['download_url'],
            'sections' => [
                'description' => 'Create custom frontend components with fields.',
                'changelog' => '<ul>' . implode("\n", array_map(function($item) {
                    return "<li>$item</li>";
                }, $remote_version['changelog'])) . '</ul>'
            ]
        ];

        return (object) $plugin_info;
    }

    public function check_for_updates() {
        $current_version = get_plugin_data(WP_PLUGIN_DIR . '/' . $this->plugin_file)['Version'];
        $remote_data = $this->get_remote_manifest();

        if (!$remote_data) {
            return;
        }

        if (version_compare($current_version, $remote_data['version'], '<')) {
            update_option('ccc_plugin_update_available', true);
            update_option('ccc_plugin_new_version', $remote_data['version']);
            
            // Trigger WordPress to check for updates
            delete_site_transient('update_plugins');
        } else {
            delete_option('ccc_plugin_update_available');
            delete_option('ccc_plugin_new_version');
        }

        update_option($this->last_check_option, time());
    }

    public function get_remote_manifest() {
        $response = wp_remote_get($this->remote_manifest_url, [
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            error_log('CCC Plugin Update Check Failed: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    public function manual_update_handler() {
        check_ajax_referer('ccc_update_nonce', 'nonce');

        if (!current_user_can('update_plugins')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Use WordPress's built-in updater instead
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        
        $plugin_upgrader = new Plugin_Upgrader();
        $result = $plugin_upgrader->upgrade($this->plugin_file);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            delete_option('ccc_plugin_update_available');
            delete_option('ccc_plugin_new_version');
            wp_send_json_success('Plugin updated successfully');
        }
    }
}