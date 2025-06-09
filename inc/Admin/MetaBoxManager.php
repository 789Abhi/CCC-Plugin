<?php
namespace CCC\Admin;

use CCC\Models\Component;
use CCC\Models\Field;
use CCC\Models\FieldValue; // Ensure this is correctly imported
use CCC\Fields\TextField;
use CCC\Fields\TextareaField;
use CCC\Fields\WysiwygField;
use CCC\Fields\CheckboxField;
use CCC\Fields\SelectField;
use CCC\Fields\RadioField;
use CCC\Fields\ButtonGroupField;
use CCC\Fields\ColorField;
use CCC\Fields\VideoField;
use CCC\Fields\OEmbedField;
use CCC\Fields\RelationshipField;
use CCC\Fields\PageLinkField;
use CCC\Fields\TaxonomyTermField;
use CCC\Fields\TabField;
use CCC\Fields\ToggleField;
use CCC\Fields\ImageField;
use CCC\Fields\RepeaterField;

defined('ABSPATH') || exit;

class MetaBoxManager {
    private $post_types = [];
    
    public function __construct($post_types = ['page', 'post']) {
        $this->post_types = $post_types;
    }
    
    public function init() {
        add_action('add_meta_boxes', [$this, 'registerMetaBoxes']);
        add_action('save_post', [$this, 'saveMetaBoxes'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        
        // AJAX handlers for field types
        add_action('wp_ajax_ccc_get_video_embed', [$this, 'getVideoEmbed']);
        add_action('wp_ajax_ccc_get_oembed', [$this, 'getOembed']);
        add_action('wp_ajax_ccc_get_relationship_posts', [$this, 'getRelationshipPosts']);
    }
    
    public function enqueueAssets($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }
        
        $screen = get_current_screen();
        if (!in_array($screen->post_type, $this->post_types)) {
            return;
        }
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-tabs');
        
        // Use plugin_dir_url to get the correct plugin URL
        $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));
        $plugin_version = get_file_data(dirname(dirname(dirname(__FILE__))) . '/custom-craft-component.php', ['Version' => 'Version'], 'plugin');
        $version = !empty($plugin_version['Version']) ? $plugin_version['Version'] : '1.0.0';
        
        // Check if CSS and JS files exist before enqueuing
        $css_path = dirname(dirname(dirname(__FILE__))) . '/assets/css/admin.css';
        $js_path = dirname(dirname(dirname(__FILE__))) . '/assets/js/admin.js';
        
        if (file_exists($css_path)) {
            wp_enqueue_style('ccc-admin-styles', $plugin_url . 'assets/css/admin.css', [], $version);
        }
        
        if (file_exists($js_path)) {
            wp_enqueue_script('ccc-admin-script', $plugin_url . 'assets/js/admin.js', ['jquery', 'wp-color-picker'], $version, true);
        }
        
        // Add inline styles for field types
        wp_add_inline_style('wp-admin', '
        .ccc-components-container {
            margin: 20px 0;
        }
        .ccc-component-wrapper {
            border: 1px solid #ddd;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .ccc-component-header {
            background: #f5f5f5;
            padding: 15px;
            border-bottom: 1px solid #ddd;
        }
        .ccc-component-header h3 {
            margin: 0;
            font-size: 16px;
        }
        .ccc-component-fields {
            padding: 20px;
        }
        .ccc-field-wrapper {
            margin-bottom: 20px;
        }
        .ccc-field-wrapper label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .ccc-field-wrapper input[type="text"],
        .ccc-field-wrapper input[type="url"],
        .ccc-field-wrapper input[type="email"],
        .ccc-field-wrapper textarea,
        .ccc-field-wrapper select {
            width: 100%;
            max-width: 500px;
        }
        .ccc-tabs-container {
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .ccc-tabs-nav {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            background: #f5f5f5;
            border-bottom: 1px solid #ddd;
        }
        .ccc-tabs-nav li {
            margin: 0;
        }
        .ccc-tabs-nav li a {
            display: block;
            padding: 12px 20px;
            text-decoration: none;
            color: #666;
            border-right: 1px solid #ddd;
        }
        .ccc-tabs-nav li.active a {
            background: #fff;
            color: #333;
            border-bottom: 1px solid #fff;
            margin-bottom: -1px;
        }
        .ccc-tab-content {
            display: none;
            padding: 20px;
        }
        .ccc-tab-content.active {
            display: block;
        }
        .ccc-color-picker {
            width: 100px !important;
        }
        .ccc-toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        .ccc-toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .ccc-toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        .ccc-toggle-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .ccc-toggle-slider {
            background-color: #2196F3;
        }
        input:checked + .ccc-toggle-slider:before {
            transform: translateX(26px);
        }
        .ccc-checkbox-group,
        .ccc-radio-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .ccc-checkbox-item,
        .ccc-radio-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .ccc-button-group {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .ccc-button-group-item {
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: #f5f5f5;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.2s;
        }
        .ccc-button-group-item:hover {
            background: #e0e0e0;
        }
        .ccc-button-group-item.active {
            background: #0073aa;
            color: white;
            border-color: #0073aa;
        }
        .ccc-image-preview {
            margin-top: 10px;
        }
        .ccc-image-preview img {
            max-width: 200px;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .ccc-repeater-item {
            border: 1px solid #ddd;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        .ccc-repeater-header {
            background: #f5f5f5;
            padding: 10px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ccc-repeater-content {
            padding: 15px;
        }
        .ccc-video-preview,
        .ccc-oembed-preview {
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f9f9f9;
        }
    ');
    
    wp_localize_script('jquery', 'cccData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ccc_nonce')
    ]);
}
    
    public function registerMetaBoxes() {
        $post_id = isset($_GET['post']) ? intval($_GET['post']) : 0;
        
        if ($post_id) {
            $components = get_post_meta($post_id, '_ccc_components', true);
            
            if (!empty($components) && is_array($components)) {
                foreach ($this->post_types as $post_type) {
                    add_meta_box(
                        'ccc_components_meta_box',
                        'Custom Craft Components',
                        [$this, 'renderMetaBox'],
                        $post_type,
                        'normal',
                        'high'
                    );
                }
            }
        }
    }
    
    public function renderMetaBox($post) {
        wp_nonce_field('ccc_meta_box', 'ccc_meta_box_nonce');
        
        $post_id = $post->ID;
        $components = get_post_meta($post_id, '_ccc_components', true);
        
        if (empty($components) || !is_array($components)) {
            echo '<p>No components assigned to this post.</p>';
            return;
        }
        
        echo '<div class="ccc-components-container">';
        
        foreach ($components as $component_data) {
            $component_id = $component_data['id'];
            $instance_id = $component_data['instance_id'];
            
            $component = Component::find($component_id);
            if (!$component) {
                continue;
            }
            
            $fields = Field::findByComponent($component_id);
            
            echo '<div class="ccc-component-wrapper">';
            echo '<div class="ccc-component-header">';
            echo '<h3>' . esc_html($component->getName()) . '</h3>';
            echo '</div>';
            
            echo '<div class="ccc-component-fields">';
            
            // Check if we have any tab fields
            $has_tabs = false;
            $tab_fields = [];
            foreach ($fields as $field) {
                if ($field->getType() === 'tab') {
                    $has_tabs = true;
                    $tab_fields[] = $field;
                }
            }
            
            if ($has_tabs) {
                // Render tabs UI
                echo '<div class="ccc-tabs-container">';
                echo '<ul class="ccc-tabs-nav">';
                
                $first_tab = true;
                foreach ($tab_fields as $tab_field) {
                    $tab_id = $tab_field->getName();
                    echo '<li class="' . ($first_tab ? 'active' : '') . '">';
                    echo '<a href="#ccc-tab-' . esc_attr($tab_id) . '-' . esc_attr($instance_id) . '">';
                    echo esc_html($tab_field->getLabel());
                    echo '</a>';
                    echo '</li>';
                    $first_tab = false;
                }
                
                echo '</ul>';
                
                // Render tab content
                $current_tab = null;
                $first_tab = true;
                
                foreach ($fields as $field) {
                    if ($field->getType() === 'tab') {
                        if ($current_tab !== null) {
                            echo '</div>'; // Close previous tab
                        }
                        
                        $current_tab = $field->getName();
                        echo '<div id="ccc-tab-' . esc_attr($current_tab) . '-' . esc_attr($instance_id) . '" class="ccc-tab-content' . ($first_tab ? ' active' : '') . '">';
                        $first_tab = false;
                        continue;
                    }
                    
                    $this->renderField($field, $post_id, $instance_id);
                }
                
                if ($current_tab !== null) {
                    echo '</div>'; // Close last tab
                }
                
                echo '</div>'; // Close tabs container
            } else {
                // Render fields normally
                foreach ($fields as $field) {
                    $this->renderField($field, $post_id, $instance_id);
                }
            }
            
            echo '</div>'; // Close component fields
            echo '</div>'; // Close component wrapper
        }
        
        echo '</div>'; // Close components container
        
        // Add JavaScript for tabs functionality
        if ($has_tabs) {
            ?>
            <script>
            jQuery(document).ready(function($) {
                $('.ccc-tabs-nav a').on('click', function(e) {
                    e.preventDefault();
                    
                    var $tab = $(this);
                    var $tabsContainer = $tab.closest('.ccc-tabs-container');
                    var targetId = $tab.attr('href');
                    
                    // Update nav
                    $tabsContainer.find('.ccc-tabs-nav li').removeClass('active');
                    $tab.parent().addClass('active');
                    
                    // Update content
                    $tabsContainer.find('.ccc-tab-content').removeClass('active');
                    $(targetId).addClass('active');
                });
            });
            </script>
            <?php
        }
    }
    
    private function renderField($field, $post_id, $instance_id) {
        // Call the static method getValue
        $field_value = FieldValue::getValue($field->getId(), $post_id, $instance_id);
        
        $field_instance = $this->createFieldInstance($field, $field_value);
        
        if ($field_instance) {
            echo $field_instance->render($post_id, $instance_id, $field_value);
        }
    }
    
    private function createFieldInstance($field, $value = '') {
        $type = $field->getType();
        $label = $field->getLabel();
        $name = $field->getName();
        $component_id = $field->getComponentId();
        $required = $field->getRequired(); // Use getRequired() as it's a method
        $placeholder = $field->getPlaceholder();
        $config = $field->getConfig();
        
        switch ($type) {
            case 'text':
                return new TextField($label, $name, $component_id, $required, $placeholder, $config);
            case 'textarea':
                return new TextareaField($label, $name, $component_id, $required, $placeholder, $config);
            case 'wysiwyg':
                return new WysiwygField($label, $name, $component_id, $required, $placeholder, $config);
            case 'checkbox':
                return new CheckboxField($label, $name, $component_id, $required, $placeholder, $config);
            case 'select':
                return new SelectField($label, $name, $component_id, $required, $placeholder, $config);
            case 'radio':
                return new RadioField($label, $name, $component_id, $required, $placeholder, $config);
            case 'button_group':
                return new ButtonGroupField($label, $name, $component_id, $required, $placeholder, $config);
            case 'color':
                return new ColorField($label, $name, $component_id, $required, $placeholder, $config);
            case 'video':
                return new VideoField($label, $name, $component_id, $required, $placeholder, $config);
            case 'oembed':
                return new OEmbedField($label, $name, $component_id, $required, $placeholder, $config);
            case 'relationship':
                return new RelationshipField($label, $name, $component_id, $required, $placeholder, $config);
            case 'page_link':
                return new PageLinkField($label, $name, $component_id, $required, $placeholder, $config);
            case 'taxonomy_term':
                return new TaxonomyTermField($label, $name, $component_id, $required, $placeholder, $config);
            case 'tab':
                return new TabField($label, $name, $component_id, $required, $placeholder, $config);
            case 'toggle':
                return new ToggleField($label, $name, $component_id, $required, $placeholder, $config);
            case 'image':
                return new ImageField($label, $name, $component_id, $required, $placeholder, $config);
            case 'repeater':
                return new RepeaterField($label, $name, $component_id, $required, $placeholder, $config);
            default:
                return null;
        }
    }
    
    public function saveMetaBoxes($post_id, $post) {
        // Verify nonce
        if (!isset($_POST['ccc_meta_box_nonce']) || !wp_verify_nonce($_POST['ccc_meta_box_nonce'], 'ccc_meta_box')) {
            return;
        }
        
        // Check if user has permission to edit the post
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Don't save on autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check if this is a revision
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        // Check if this post type should have components
        if (!in_array($post->post_type, $this->post_types)) {
            return;
        }
        
        // Save field values
        if (isset($_POST['ccc_field_values']) && is_array($_POST['ccc_field_values'])) {
            // Call the static method saveMultiple
            FieldValue::saveMultiple($post_id, $_POST['ccc_field_values']);
        }
    }
    
    // AJAX handlers
    public function getVideoEmbed() {
        check_ajax_referer('ccc_video_embed', 'nonce');
        
        $url = sanitize_url($_POST['url']);
        
        if (empty($url)) {
            wp_send_json_error('Invalid URL');
        }
        
        $embed = wp_oembed_get($url, ['width' => 400]);
        
        if ($embed) {
            wp_send_json_success($embed);
        } else {
            wp_send_json_error('Unable to embed video');
        }
    }
    
    public function getOembed() {
        check_ajax_referer('ccc_oembed', 'nonce');
        
        $url = sanitize_url($_POST['url']);
        
        if (empty($url)) {
            wp_send_json_error('Invalid URL');
        }
        
        $embed = wp_oembed_get($url, ['width' => 400]);
        
        if ($embed) {
            wp_send_json_success($embed);
        } else {
            wp_send_json_error('Unable to embed content');
        }
    }
    
    public function getRelationshipPosts() {
        check_ajax_referer('ccc_relationship', 'nonce');
        
        $search = sanitize_text_field($_POST['search']);
        $post_type = isset($_POST['post_type']) ? $_POST['post_type'] : ['post', 'page'];
        $selected = isset($_POST['selected']) ? array_map('intval', $_POST['selected']) : [];
        
        if (is_string($post_type)) {
            $post_type = [$post_type];
        }
        
        $args = [
            'post_type' => $post_type,
            'posts_per_page' => 20,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ];
        
        if (!empty($search)) {
            $args['s'] = $search;
        }
        
        if (!empty($selected)) {
            $args['post__not_in'] = $selected;
        }
        
        $posts = get_posts($args);
        
        $results = [];
        foreach ($posts as $post) {
            $post_type_obj = get_post_type_object($post->post_type);
            $results[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'type' => $post->post_type,
                'type_label' => $post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type,
            ];
        }
        
        wp_send_json_success($results);
    }
}
