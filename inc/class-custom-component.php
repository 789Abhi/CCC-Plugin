<?php
defined('ABSPATH') || exit;

class Custom_Craft_Component {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('init', [$this, 'register_cc_component_post_type']);

        add_action('wp_ajax_ccc_save_component', [$this, 'ajax_save_component']);
        add_action('wp_ajax_ccc_create_component', [$this, 'handle_create_component']);
        add_action('wp_ajax_nopriv_ccc_create_component', [$this, 'handle_create_component']); // optional for guest users
        
        add_action('wp_ajax_ccc_get_components', [$this, 'get_components']);

        add_action('wp_ajax_ccc_add_field', 'ccc_add_field_callback');
    }



    function ccc_add_field_callback() {
        check_ajax_referer('ccc_nonce', 'nonce');
    
        $label = sanitize_text_field($_POST['label']);
        $name = sanitize_title($_POST['name']);
        $type = sanitize_text_field($_POST['type']);
        $component_id = intval($_POST['component_id']);
    
        if (!$label || !$name || !$type || !$component_id) {
            wp_send_json_error(['message' => 'Missing required values.']);
        }
    
        $type_map = [
            'text' => 'Text',
            'text-area' => 'Text_Area'
        ];
    
        if (!isset($type_map[$type])) {
            wp_send_json_error(['message' => 'Invalid field type.']);
        }
    
        $class_name = $type_map[$type];
        $file_path = plugin_dir_path(__FILE__) . "inc/Fields/{$type}.php";
    
        if (!file_exists($file_path)) {
            wp_send_json_error(['message' => 'Field file not found.']);
        }
    
        require_once $file_path;
        $full_class = "CCC\\Fields\\$class_name";
        $field = new $full_class($label, $name, $component_id);
        $field->save();
    
        wp_send_json_success(['message' => 'Field saved.']);
    }

    public function get_components() {
        check_ajax_referer('ccc_nonce', 'nonce');
    
        global $wpdb;
        $table = $wpdb->prefix . 'cc_components';
        $results = $wpdb->get_results("SELECT name, handle_name FROM $table", ARRAY_A);
    
        wp_send_json_success(['components' => $results]);
    }
    

    public function handle_create_component() {
        check_ajax_referer('ccc_nonce', 'nonce');

        $name = sanitize_text_field($_POST['name'] ?? '');
        $handle = sanitize_title($_POST['handle'] ?? '');

        if (empty($name) || empty($handle)) {
            wp_send_json_error(['message' => 'Missing required fields']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cc_components';
        $post_id = wp_insert_post([
            'post_title'  => $name,
            'post_type'   => 'cc_component',
            'post_status' => 'publish'
        ]);
        
        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => 'Failed to create post']);
        }
        
        $wpdb->insert($table, [
            'post_id'     => $post_id,
            'name'        => $name,
            'handle_name' => $handle,
            'instruction' => '',
            'hidden'      => 0,
            'created_at'  => current_time('mysql')
        ]);

     

        wp_send_json_success(['message' => 'Component created successfully']);
    }

    public function ajax_save_component() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $name = sanitize_text_field($_POST['name']);
        $handle = sanitize_title($name);

        $post_id = wp_insert_post([
            'post_title'  => $name,
            'post_type'   => 'cc_component',
            'post_status' => 'publish'
        ]);

        if (is_wp_error($post_id)) {
            wp_send_json_error('Could not create post');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cc_components';
        $wpdb->insert($table, [
            'name'   => $name,
            'handle'=> str_replace('-', '_', $handle)
        ]);

        wp_send_json_success(['post_id' => $post_id]);
    }

    public function register_cc_component_post_type() {
        register_post_type('cc_component', [
            'label'         => 'Components',
            'public'        => false,
            'show_ui'       => true,
            'supports'      => ['title'],
            'menu_icon'     => 'dashicons-screenoptions',
            'capability_type' => 'post',
            'hierarchical'  => false,
            'has_archive'   => false,
        ]);
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
                'baseUrl'     => $build_url,
                'ajaxUrl'     => admin_url('admin-ajax.php'),
                'nonce'       => wp_create_nonce('ccc_nonce'), // ✅ Added nonce here
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
