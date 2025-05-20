<?php
defined('ABSPATH') || exit;

class Custom_Craft_Component {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('add_meta_boxes', [$this, 'add_component_meta_box']);
        add_action('save_post', [$this, 'save_component_order'], 10, 2);

        add_action('wp_ajax_ccc_create_component', [$this, 'handle_create_component']);
        add_action('wp_ajax_nopriv_ccc_create_component', [$this, 'handle_create_component']);
        add_action('wp_ajax_ccc_get_components', [$this, 'get_components']);
        add_action('wp_ajax_ccc_add_field', [$this, 'add_field_callback']);
        add_action('wp_ajax_ccc_get_posts', [$this, 'get_posts']);
        add_action('wp_ajax_ccc_delete_component', [$this, 'delete_component']);
        add_action('wp_ajax_ccc_delete_field', [$this, 'delete_field']);
    }

    public function add_field_callback() {
        try {
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ccc_nonce')) {
                wp_send_json_error(['message' => 'Security check failed.']);
                return;
            }

            $label = isset($_POST['label']) ? sanitize_text_field($_POST['label']) : '';
            $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
            $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
            $component_id = isset($_POST['component_id']) ? intval($_POST['component_id']) : 0;

            if (empty($label) || empty($name) || empty($type) || empty($component_id)) {
                wp_send_json_error(['message' => 'Missing required fields.']);
                return;
            }

            global $wpdb;
            $fields_table = $wpdb->prefix . 'cc_fields';

            $component_exists = $wpdb->get_var(
                $wpdb->prepare("SELECT id FROM {$wpdb->prefix}cc_components WHERE id = %d", $component_id)
            );

            if (!$component_exists) {
                wp_send_json_error(['message' => 'Invalid component ID.']);
                return;
            }

            $result = $wpdb->insert(
                $fields_table,
                [
                    'component_id' => $component_id,
                    'label' => $label,
                    'name' => $name,
                    'type' => $type,
                    'config' => '{}',
                    'field_order' => 0,
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%s', '%s', '%s', '%s', '%d', '%s']
            );

            if ($result === false) {
                error_log("Database error in add_field_callback: " . $wpdb->last_error);
                wp_send_json_error(['message' => 'Failed to save field: Database error.']);
            } else {
                wp_send_json_success(['message' => 'Field added successfully.']);
            }
        } catch (Exception $e) {
            error_log("Exception in add_field_callback: " . $e->getMessage());
            wp_send_json_error(['message' => 'Server error: ' . $e->getMessage()]);
        }
    }

    public function get_components() {
        check_ajax_referer('ccc_nonce', 'nonce');

        global $wpdb;
        $components_table = $wpdb->prefix . 'cc_components';
        $fields_table = $wpdb->prefix . 'cc_fields';
        $field_values_table = $wpdb->prefix . 'cc_field_values';

        $results = $wpdb->get_results(
            "SELECT id, name, handle_name FROM $components_table",
            ARRAY_A
        );

        if ($wpdb->last_error) {
            error_log("Database error in get_components: " . $wpdb->last_error);
            wp_send_json_error(['message' => 'Failed to fetch components: Database error.']);
            return;
        }

        foreach ($results as &$component) {
            $fields = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, label, name, type FROM $fields_table WHERE component_id = %d ORDER BY field_order, created_at",
                    $component['id']
                ),
                ARRAY_A
            );

            foreach ($fields as &$field) {
                $field['values'] = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT id, post_id, value FROM $field_values_table WHERE field_id = %d",
                        $field['id']
                    ),
                    ARRAY_A
                );
            }
            $component['fields'] = $fields ? $fields : [];
        }

        wp_send_json_success(['components' => $results]);
    }

    public function get_posts() {
        check_ajax_referer('ccc_nonce', 'nonce');

        $post_type = sanitize_text_field($_POST['post_type'] ?? 'page');
        $posts = get_posts([
            'post_type' => $post_type,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);

        $post_list = array_map(function ($post) {
            return [
                'id' => $post->ID,
                'title' => $post->post_title
            ];
        }, $posts);

        wp_send_json_success(['posts' => $post_list]);
    }

    public function delete_component() {
        check_ajax_referer('ccc_nonce', 'nonce');

        global $wpdb;
        $component_id = isset($_POST['component_id']) ? intval($_POST['component_id']) : 0;

        if (!$component_id) {
            wp_send_json_error(['message' => 'Invalid component ID.']);
            return;
        }

        $result = $wpdb->delete(
            $wpdb->prefix . 'cc_components',
            ['id' => $component_id],
            ['%d']
        );

        if ($result === false) {
            error_log("Database error in delete_component: " . $wpdb->last_error);
            wp_send_json_error(['message' => 'Failed to delete component: Database error.']);
            return;
        }

        wp_send_json_success(['message' => 'Component deleted successfully.']);
    }

    public function delete_field() {
        check_ajax_referer('ccc_nonce', 'nonce');

        global $wpdb;
        $field_id = isset($_POST['field_id']) ? intval($_POST['field_id']) : 0;

        if (!$field_id) {
            wp_send_json_error(['message' => 'Invalid field ID.']);
            return;
        }

        $result = $wpdb->delete(
            $wpdb->prefix . 'cc_fields',
            ['id' => $field_id],
            ['%d']
        );

        if ($result === false) {
            error_log("Database error in delete_field: " . $wpdb->last_error);
            wp_send_json_error(['message' => 'Failed to delete field: Database error.']);
            return;
        }

        wp_send_json_success(['message' => 'Field deleted successfully.']);
    }

    public function handle_create_component() {
        check_ajax_referer('ccc_nonce', 'nonce');

        $name = sanitize_text_field($_POST['name'] ?? '');
        $handle = sanitize_title($_POST['handle'] ?? '');

        if (empty($name) || empty($handle)) {
            wp_send_json_error(['message' => 'Missing required fields']);
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cc_components';

        $handle_exists = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $table WHERE handle_name = %s", $handle)
        );

        if ($handle_exists) {
            wp_send_json_error(['message' => 'Handle already exists. Please choose a different one.']);
            return;
        }

        $result = $wpdb->insert($table, [
            'name' => $name,
            'handle_name' => $handle,
            'instruction' => '',
            'hidden' => 0,
            'created_at' => current_time('mysql')
        ]);

        if ($result === false) {
            wp_send_json_error(['message' => 'Failed to create component']);
            return;
        }

        wp_send_json_success(['message' => 'Component created successfully']);
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

    public function add_component_meta_box() {
        add_meta_box(
            'ccc_component_selector',
            'Custom Components',
            [$this, 'render_component_meta_box'],
            ['post', 'page'],
            'normal',
            'high'
        );
    }

    public function render_component_meta_box($post) {
        wp_nonce_field('ccc_component_meta_box', 'ccc_component_nonce');
        echo '<div id="ccc-component-selector" data-post-id="' . esc_attr($post->ID) . '"></div>';
    }

    public function save_component_order($post_id, $post) {
        if (!isset($_POST['ccc_component_nonce']) || !wp_verify_nonce($_POST['ccc_component_nonce'], 'ccc_component_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $components = isset($_POST['ccc_components']) ? json_decode(wp_unslash($_POST['ccc_components']), true) : [];
        update_post_meta($post_id, '_ccc_components', $components);

        $field_values = isset($_POST['ccc_field_values']) ? json_decode(wp_unslash($_POST['ccc_field_values']), true) : [];
        
        global $wpdb;
        $field_values_table = $wpdb->prefix . 'cc_field_values';
        
        $wpdb->delete($field_values_table, ['post_id' => $post_id], ['%d']);

        foreach ($field_values as $value) {
            $wpdb->insert(
                $field_values_table,
                [
                    'post_id' => $post_id,
                    'field_id' => intval($value['field_id']),
                    'value' => wp_kses_post($value['value']),
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%d', '%s', '%s']
            );
        }
    }

    public function enqueue_assets($hook) {
        $plugin_pages = [
            'toplevel_page_custom-craft-component',
            'custom-craft-component_page_custom-craft-posttypes',
            'custom-craft-component_page_custom-craft-taxonomies',
            'custom-craft-component_page_custom-craft-importexport',
            'custom-craft-component_page_custom-craft-settings',
            'post.php',
            'post-new.php'
        ];

        if (!in_array($hook, $plugin_pages)) return;

        $build_dir = plugin_dir_path(__FILE__) . '../build/assets/';
        $build_url = plugin_dir_url(__FILE__) . '../build/assets/';

        $js_file = '';
        $css_file = '';

        foreach (glob($build_dir . '*.js') as $file) {
            $js_file = basename($file);
            break;
        }

        foreach (glob($build_dir . '*.css') as $file) {
            if (strpos($file, 'index') !== false || strpos($file, 'main') !== false) {
                $css_file = basename($file);
                break;
            }
        }

        if ($js_file) {
            wp_enqueue_script('ccc-react', $build_url . $js_file, ['wp-api'], '1.0', true);

            $current_page = $hook;
            if (strpos($hook, 'custom-craft-component_page_') !== false) {
                $current_page = str_replace('custom-craft-component_page_', '', $hook);
            } elseif ($hook === 'toplevel_page_custom-craft-component') {
                $current_page = 'custom-craft-component';
            }

            wp_localize_script('ccc-react', 'cccData', [
                'currentPage' => $current_page,
                'baseUrl' => $build_url,
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ccc_nonce'),
            ]);
        }

        if ($css_file) {
            wp_enqueue_style('ccc-style', $build_url . $css_file, [], '1.0');
        }

        wp_enqueue_script('react-beautiful-dnd', 'https://unpkg.com/react-beautiful-dnd@13.1.1/dist/react-beautiful-dnd.min.js', ['react', 'react-dom'], '13.1.1', true);
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