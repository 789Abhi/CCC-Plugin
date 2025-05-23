<?php
defined('ABSPATH') || exit;

class Custom_Craft_Component {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('add_meta_boxes', [$this, 'add_component_meta_box']);
        add_action('save_post', [$this, 'save_component_data'], 10, 2);
        add_filter('theme_page_templates', [$this, 'add_ccc_template']);
        add_filter('template_include', [$this, 'load_ccc_template']);

        add_action('wp_ajax_ccc_create_component', [$this, 'handle_create_component']);
        add_action('wp_ajax_ccc_get_components', [$this, 'get_components']);
        add_action('wp_ajax_ccc_add_field', [$this, 'add_field_callback']);
        add_action('wp_ajax_ccc_get_posts', [$this, 'get_posts']);
        add_action('wp_ajax_ccc_delete_component', [$this, 'delete_component']);
        add_action('wp_ajax_ccc_delete_field', [$this, 'delete_field']);
        add_action('wp_ajax_ccc_save_assignments', [$this, 'save_assignments']);
        add_action('wp_ajax_ccc_save_field_values', [$this, 'save_field_values']);
        add_action('wp_ajax_nopriv_ccc_save_field_values', [$this, 'save_field_values']);
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
            $components = get_post_meta($post->ID, '_ccc_components', true);
            return [
                'id' => $post->ID,
                'title' => $post->post_title,
                'has_components' => !empty($components)
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

        $handle_name = $wpdb->get_var(
            $wpdb->prepare("SELECT handle_name FROM {$wpdb->prefix}cc_components WHERE id = %d", $component_id)
        );

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

        $theme_dir = get_stylesheet_directory();
        $template_file = $theme_dir . '/ccc-templates/' . $handle_name . '.php';
        if (file_exists($template_file)) {
            unlink($template_file);
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

        $component_id = $wpdb->insert_id;
        $theme_dir = get_stylesheet_directory();
        $templates_dir = $theme_dir . '/ccc-templates';
        if (!file_exists($templates_dir)) {
            wp_mkdir_p($templates_dir);
        }

        $template_file = $templates_dir . '/' . $handle . '.php';
        $template_content = "<?php\n"
            . "/* Template for component: $name */\n"
            . "global \$wpdb;\n"
            . "\$post_id = get_the_ID();\n"
            . "\$component_id = $component_id;\n"
            . "\$fields = \$wpdb->get_results(\$wpdb->prepare(\n"
            . "    \"SELECT id, label, name, type FROM {$wpdb->prefix}cc_fields WHERE component_id = %d ORDER BY field_order, created_at\",\n"
            . "    \$component_id\n"
            . "));\n"
            . "if (\$fields) {\n"
            . "    echo '<h3>' . esc_html('$name') . '</h3>';\n"
            . "    foreach (\$fields as \$field) {\n"
            . "        \$value = \$wpdb->get_var(\$wpdb->prepare(\n"
            . "            \"SELECT value FROM {$wpdb->prefix}cc_field_values WHERE field_id = %d AND post_id = %d\",\n"
            . "            \$field->id, \$post_id\n"
            . "        ));\n"
            . "        \$field_id = \$field->id;\n"
            . "        \$field_name = \$field->name;\n"
            . "        \$field_label = \$field->label;\n"
            . "        \$field_type = \$field->type;\n"
            . "?>\n"
            . "<div class='ccc-field ccc-field-<?php echo esc_attr(\$field_name); ?>'>\n"
            . "    <label for='ccc_field_<?php echo esc_attr(\$field_id); ?>'><?php echo esc_html(\$field_label); ?></label>\n"
            . "    <?php if (\$field_type === 'text') { ?>\n"
            . "        <input type='text' id='ccc_field_<?php echo esc_attr(\$field_id); ?>' name='ccc_field_values[<?php echo esc_attr(\$field_id); ?>]' value='<?php echo esc_attr(\$value ?: ''); ?>' class='ccc-input' />\n"
            . "    <?php } elseif (\$field_type === 'text-area') { ?>\n"
            . "        <textarea id='ccc_field_<?php echo esc_attr(\$field_id); ?>' name='ccc_field_values[<?php echo esc_attr(\$field_id); ?>]' class='ccc-textarea' rows='5'><?php echo esc_textarea(\$value ?: ''); ?></textarea>\n"
            . "    <?php } ?>\n"
            . "</div>\n"
            . "<?php }\n"
            . "} else {\n"
            . "    echo '<p>No fields defined for this component.</p>';\n"
            . "}\n"
            . "?>\n";

        if (!file_put_contents($template_file, $template_content)) {
            error_log("Failed to write component template: $template_file");
            wp_send_json_error(['message' => 'Failed to create component template file']);
            return;
        }

        wp_send_json_success(['message' => 'Component created successfully']);
    }

    public function save_assignments() {
        check_ajax_referer('ccc_nonce', 'nonce');

        $post_ids = isset($_POST['post_ids']) ? array_map('intval', (array)$_POST['post_ids']) : [];
        $components = isset($_POST['components']) ? json_decode(wp_unslash($_POST['components']), true) : [];

        foreach ($post_ids as $post_id) {
            if ($post_id === 0) {
                $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'page';
                $posts = get_posts([
                    'post_type' => $post_type,
                    'post_status' => 'publish',
                    'numberposts' => -1
                ]);
                foreach ($posts as $post) {
                    update_post_meta($post->ID, '_ccc_components', $components);
                }
            } else {
                update_post_meta($post_id, '_ccc_components', $components);
            }
        }

        wp_send_json_success(['message' => 'Assignments saved successfully']);
    }

    public function save_field_values() {
        check_ajax_referer('ccc_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $field_values = isset($_POST['ccc_field_values']) ? $_POST['ccc_field_values'] : [];

        if (!$post_id) {
            wp_send_json_error(['message' => 'Invalid post ID.']);
            return;
        }

        global $wpdb;
        $field_values_table = $wpdb->prefix . 'cc_field_values';

        $wpdb->delete($field_values_table, ['post_id' => $post_id], ['%d']);

        foreach ($field_values as $field_id => $value) {
            $field_id = intval($field_id);
            $value = wp_kses_post($value);
            $wpdb->insert(
                $field_values_table,
                [
                    'post_id' => $post_id,
                    'field_id' => $field_id,
                    'value' => $value,
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%d', '%s', '%s']
            );
        }

        wp_send_json_success(['message' => 'Field values saved successfully']);
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

    public function save_component_data($post_id, $post) {
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

    public function add_ccc_template($templates) {
        $templates['ccc-template.php'] = 'CCC Component Template';
        return $templates;
    }

    public function load_ccc_template($template) {
        if (is_singular()) {
            $post_id = get_the_ID();
            $page_template = get_post_meta($post_id, '_wp_page_template', true);
            if ($page_template === 'ccc-template.php') {
                $plugin_template = plugin_dir_path(__FILE__) . '../ccc-template.php';
                if (file_exists($plugin_template)) {
                    return $plugin_template;
                }
            }
        }
        return $template;
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

        if (!in_array($hook, $plugin_pages)) {
            // Enqueue front-end scripts and styles inline
            if (is_singular()) {
                // Enqueue jQuery explicitly
                wp_enqueue_script('jquery');

                // Register a script handle for inline JS
                wp_enqueue_script('ccc-frontend', plugins_url('js/frontend.js', __FILE__), ['jquery'], '1.2.7', true);
                $js_code = "
                    console.log('hello');
                    jQuery(document).ready(function ($) {
                        $('#ccc-component-form').on('submit', function (e) {
                            e.preventDefault();
                            var formData = $(this).serializeArray();
                            var data = {
                                action: 'ccc_save_field_values',
                                nonce: cccData.nonce,
                                post_id: $('input[name=\"post_id\"]').val(),
                                ccc_field_values: {}
                            };
                            $.each(formData, function (index, field) {
                                if (field.name.startsWith('ccc_field_values[')) {
                                    var fieldId = field.name.match(/\\[(\\d+)\\]/)[1];
                                    data.ccc_field_values[fieldId] = field.value;
                                }
                            });
                            $.ajax({
                                url: cccData.ajaxUrl,
                                type: 'POST',
                                data: data,
                                success: function (response) {
                                    if (response.success) {
                                        alert(response.data.message || 'Field values saved successfully.');
                                    } else {
                                        alert(response.data.message || 'Failed to save field values.');
                                    }
                                },
                                error: function () {
                                    alert('Error connecting to server. Please try again.');
                                }
                            });
                        });
                    });
                ";
                wp_add_inline_script('ccc-frontend', $js_code);

                wp_localize_script('ccc-frontend', 'cccData', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('ccc_nonce'),
                ]);

                // Register a style handle for inline CSS
                wp_enqueue_style('ccc-frontend-style', plugins_url('css/frontend.css', __FILE__), [], '1.2.7');
                $css_code = "
                    .ccc-component {
                        margin-bottom: 20px;
                        padding: 15px;
                        border: 1px solid #e5e7eb;
                        border-radius: 5px;
                        background-color: #f9fafb;
                    }
                    .ccc-field {
                        margin-bottom: 15px;
                    }
                    .ccc-field label {
                        display: block;
                        font-weight: 500;
                        margin-bottom: 5px;
                        color: #374151;
                    }
                    .ccc-input {
                        width: 100%;
                        padding: 8px;
                        border: 1px solid #d1d5db;
                        border-radius: 4px;
                        font-size: 14px;
                    }
                    .ccc-textarea {
                        width: 100%;
                        padding: 8px;
                        border: 1px solid #d1d5db;
                        border-radius: 4px;
                        font-size: 14px;
                        resize: vertical;
                    }
                    .ccc-submit-button {
                        background-color: #10b981;
                        color: white;
                        padding: 10px 20px;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                        font-size: 14px;
                    }
                    .ccc-submit-button:hover {
                        background-color: #059669;
                    }
                ";
                wp_add_inline_style('ccc-frontend-style', $css_code);
            }
            return;
        }

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
            wp_enqueue_script('ccc-react', $build_url . $js_file, ['wp-api'], '1.2.7', true);

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
            wp_enqueue_style('ccc-style', $build_url . $css_file, [], '1.2.7');
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