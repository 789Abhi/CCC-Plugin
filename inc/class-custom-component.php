<?php
defined('ABSPATH') || exit;

class Custom_Craft_Component {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_admin_pages() {
        add_menu_page(
            'Custom Craft Component',
            'Custom Components',
            'manage_options',
            'custom-craft-component',
            [$this, 'render_components_page'],
            'dashicons-admin-customizer',
            20
        );

        add_submenu_page('custom-craft-component', 'Components', 'Components', 'manage_options', 'custom-craft-component', [$this, 'render_components_page']);
        add_submenu_page('custom-craft-component', 'Post Types', 'Post Types', 'manage_options', 'custom-craft-posttypes', [$this, 'render_posttypes_page']);
        add_submenu_page('custom-craft-component', 'Taxonomies', 'Taxonomies', 'manage_options', 'custom-craft-taxonomies', [$this, 'render_taxonomies_page']);
        add_submenu_page('custom-craft-component', 'Import-Export', 'Import-Export', 'manage_options', 'custom-craft-importexport', [$this, 'render_importexport_page']);
        add_submenu_page('custom-craft-component', 'Settings', 'Settings', 'manage_options', 'custom-craft-settings', [$this, 'render_settings_page']);
    }

    public function enqueue_assets($hook) {
        $plugin_pages = [
            'toplevel_page_custom-craft-component',
            'custom-craft-component_page_custom-craft-posttypes',
            'custom-craft-component_page_custom-craft-taxonomies',
            'custom-craft-component_page_custom-craft-importexport',
            'custom-craft-component_page_custom-craft-settings'
        ];

        if (!in_array($hook, $plugin_pages)) return;

        $build_dir = plugin_dir_path(__FILE__) . '../build/assets/';
        $build_url = plugin_dir_url(__FILE__) . '../build/assets/';

        $js_file = '';
        $css_file = '';

        foreach (glob($build_dir . '*.js') as $file) {
            if (strpos($file, 'index') !== false) {
                $js_file = basename($file);
                break;
            }
        }

        foreach (glob($build_dir . '*.css') as $file) {
            if (strpos($file, 'index') !== false) {
                $css_file = basename($file);
                break;
            }
        }

        if ($js_file) {
            wp_enqueue_script('ccc-react', $build_url . $js_file, [], '1.0', true);

            $current_page = $hook;
            if (strpos($hook, 'custom-craft-component_page_') !== false) {
                $current_page = str_replace('custom-craft-component_page_', '', $hook);
            } elseif ($hook === 'toplevel_page_custom-craft-component') {
                $current_page = 'custom-craft-component';
            }

            wp_localize_script('ccc-react', 'cccData', [
                'currentPage' => $current_page,
                'baseUrl' => $build_url
            ]);
        }

        if ($css_file) {
            wp_enqueue_style('ccc-style', $build_url . $css_file, [], '1.0');
        }
    }

    public function render_components_page() {
        echo '<div id="root" data-page="components"></div>';
    }

    public function render_posttypes_page() {
        echo '<div id="root" data-page="posttypes"></div>';
    }

    public function render_taxonomies_page() {
        echo '<div id="root" data-page="taxonomies"></div>';
    }

    public function render_importexport_page() {
        echo '<div id="root" data-page="importexport"></div>';
    }

    public function render_settings_page() {
        echo '<div id="root" data-page="settings"></div>';
    }
}
