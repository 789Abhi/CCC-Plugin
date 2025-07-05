<?php

namespace CCC\Admin;

use CCC\Models\Component;
use CCC\Models\Field;
use CCC\Admin\MetaBoxFields\TextFieldRenderer;
use CCC\Admin\MetaBoxFields\TextareaFieldRenderer;
use CCC\Admin\MetaBoxFields\ImageFieldRenderer;
use CCC\Admin\MetaBoxFields\ColorFieldRenderer;
use CCC\Admin\MetaBoxFields\RepeaterFieldRenderer;
use CCC\Admin\MetaBoxFields\SelectFieldRenderer;
use CCC\Admin\MetaBoxFields\CheckboxFieldRenderer;
use CCC\Admin\MetaBoxFields\RadioFieldRenderer;
use CCC\Admin\MetaBoxFields\WysiwygFieldRenderer;

defined('ABSPATH') || exit;

class MetaBoxManager {
    private $field_renderers = [];

    public function __construct() {
        $this->registerFieldRenderers();
    }

    public function init() {
        add_action('add_meta_boxes', [$this, 'addComponentMetaBox'], 10, 2);
        add_action('save_post', [$this, 'saveComponentData'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueueMediaLibrary']);
    }

    private function registerFieldRenderers() {
        $this->field_renderers = [
            'text' => TextFieldRenderer::class,
            'textarea' => TextareaFieldRenderer::class,
            'image' => ImageFieldRenderer::class,
            'color' => ColorFieldRenderer::class,
            'repeater' => RepeaterFieldRenderer::class,
            'select' => SelectFieldRenderer::class,
            'checkbox' => CheckboxFieldRenderer::class,
            'radio' => RadioFieldRenderer::class,
            'wysiwyg' => WysiwygFieldRenderer::class,
        ];
    }

    public function enqueueMediaLibrary($hook) {
        if ('post.php' == $hook || 'post-new.php' == $hook) {
            wp_enqueue_media();
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            
            // Enqueue TinyMCE for WYSIWYG fields
            wp_enqueue_editor();
        }
    }

    public function addComponentMetaBox($post_type, $post = null) {
        if (!$post || !($post instanceof \WP_Post) || empty($post->ID)) {
            return;
        }

        // Always render the meta box, even if there are no components assigned
        add_meta_box(
            'ccc_component_selector',
            'Custom Components',
            [$this, 'renderComponentMetaBox'],
            ['post', 'page'],
            'normal',
            'high'
        );
    }

    public function renderComponentMetaBox($post) {
        wp_nonce_field('ccc_component_meta_box', 'ccc_component_nonce');
        
        $components = Component::all();
        $current_components = get_post_meta($post->ID, '_ccc_components', true);
        if (!is_array($current_components)) {
            $current_components = [];
        }

        usort($current_components, function($a, $b) {
            return ($a['order'] ?? 0) - ($b['order'] ?? 0);
        });

        $field_values = $this->getFieldValues($components, $post->ID);
        
        ?>
        <div id="ccc-component-manager" data-post-id="<?php echo esc_attr($post->ID); ?>">
            <?php if (empty($components)): ?>
                <p>No components available. Please create components in the <a href="<?php echo admin_url('admin.php?page=custom-craft-component'); ?>">Custom Components</a> section.</p>
            <?php else: ?>
                
                <div class="ccc-add-component-section">
                    <label for="ccc-component-dropdown">Add Component:</label>
                    <select id="ccc-component-dropdown" style="width: 300px; margin-right: 10px;">
                        <option value="">Select a component to add...</option>
                        <?php foreach ($components as $component): ?>
                            <option value="<?php echo esc_attr($component->getId()); ?>" 
                                    data-name="<?php echo esc_attr($component->getName()); ?>"
                                    data-handle="<?php echo esc_attr($component->getHandleName()); ?>">
                                <?php echo esc_html($component->getName()); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="ccc-add-component-btn" class="button">Add Component</button>
                </div>

                <div id="ccc-selected-components" class="ccc-sortable-components">
                    <h4>Selected Components (drag to reorder):</h4>
                    <div id="ccc-components-list">
                        <?php foreach ($current_components as $index => $comp): ?>
                            <?php $this->renderComponentAccordion($comp, $index, $field_values); ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <input type="hidden" id="ccc-components-data" name="ccc_components_data" value="<?php echo esc_attr(json_encode($current_components)); ?>" />
                
                <p><em>Use the dropdown above to add components, then drag to reorder them. You can add the same component multiple times with different values. Component values are saved automatically when you save the page.</em></p>
                <p><strong>Note:</strong> Each component instance can have different field values. The order you set here will be reflected on the frontend.</p>
                
            <?php endif; ?>
        </div>

        <?php $this->renderMetaBoxStyles(); ?>
        <?php $this->renderMetaBoxScript(); ?>
        <?php
    }

    public function renderComponentAccordion($comp, $index, $field_values) {
        $fields = Field::findByComponent($comp['id']);
        $instance_id = $comp['instance_id'] ?? ('legacy_' . $index);
        $instance_count = 1;
        ?>
        <div class="ccc-component-accordion" data-instance-id="<?php echo esc_attr($instance_id); ?>">
            <div class="ccc-component-header">
                <div class="ccc-component-title">
                    <span class="ccc-drag-handle dashicons dashicons-menu"></span>
                    <?php echo esc_html($comp['name']); ?>
                    <span class="ccc-component-order">Order: <?php echo esc_attr($index + 1); ?></span>
                    <span class="ccc-component-instance">Instance #<?php echo esc_attr($instance_count); ?></span>
                </div>
                <div class="ccc-component-actions">
                    <button type="button" class="ccc-toggle-btn">Expand</button>
                    <button type="button" class="ccc-remove-btn" data-instance-id="<?php echo esc_attr($instance_id); ?>">Remove</button>
                </div>
            </div>
            <div class="ccc-component-content">
                <?php if ($fields): ?>
                    <?php foreach ($fields as $field): ?>
                        <?php echo $this->renderField($field, $instance_id, $field_values, null); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No fields defined for this component.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    protected function renderField($field, $instance_id, $field_values, $post_id) {
        $field_type = $field->getType();
        $field_value = $field_values[$instance_id][$field->getId()] ?? '';
        
        if (!isset($this->field_renderers[$field_type])) {
            return '<p>Unsupported field type: ' . esc_html($field_type) . '</p>';
        }
        
        $renderer_class = $this->field_renderers[$field_type];
        $renderer = new $renderer_class($field, $instance_id, $field_value, $post_id);
        
        return $renderer->render();
    }

    public function saveComponentData($post_id, $post) {
        if (!isset($_POST['ccc_component_nonce']) || !wp_verify_nonce($_POST['ccc_component_nonce'], 'ccc_component_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $components_data = isset($_POST['ccc_components_data']) ? json_decode(wp_unslash($_POST['ccc_components_data']), true) : [];
        
        $components = [];
        foreach ($components_data as $comp) {
            $components[] = [
                'id' => intval($comp['id']),
                'name' => sanitize_text_field($comp['name']),
                'handle_name' => sanitize_text_field($comp['handle_name']),
                'order' => intval($comp['order'] ?? 0),
                'instance_id' => sanitize_text_field($comp['instance_id'] ?? '')
            ];
        }
        
        usort($components, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        
        update_post_meta($post_id, '_ccc_components', $components);

        $field_values = isset($_POST['ccc_field_values']) && is_array($_POST['ccc_field_values']) 
            ? $_POST['ccc_field_values'] 
            : [];

        global $wpdb;
        $field_values_table = $wpdb->prefix . 'cc_field_values';
        
        $wpdb->delete($field_values_table, ['post_id' => $post_id], ['%d']);

        foreach ($field_values as $instance_id => $instance_fields) {
            if (!is_array($instance_fields)) continue;
            
            foreach ($instance_fields as $field_id => $value) {
                $field_id = intval($field_id);
                
                $field_obj = Field::find($field_id);
                if (!$field_obj) {
                    error_log("CCC: Field object not found for field_id: $field_id during save.");
                    continue;
                }

                $value_to_save = $this->sanitizeFieldValue($value, $field_obj);
                
                if ($value_to_save !== '' && $value_to_save !== '[]') {
                    $result = $wpdb->insert(
                        $field_values_table,
                        [
                            'post_id' => $post_id,
                            'field_id' => $field_id,
                            'instance_id' => $instance_id,
                            'value' => $value_to_save,
                            'created_at' => current_time('mysql')
                        ],
                        ['%d', '%d', '%s', '%s', '%s']
                    );
                    
                    if ($result === false) {
                        error_log("CCC: Failed to save field value for field_id: $field_id, instance: $instance_id, post_id: $post_id, error: " . $wpdb->last_error);
                    }
                }
            }
        }
    }

    private function sanitizeFieldValue($value, $field_obj) {
        $field_type = $field_obj->getType();
        $value_to_save = wp_unslash($value);
        
        switch ($field_type) {
            case 'repeater':
                $decoded_value = json_decode($value_to_save, true);
                $sanitized_decoded_value = $this->sanitizeRepeaterData($decoded_value, $field_obj->getConfig());
                return json_encode($sanitized_decoded_value);
                
            case 'image':
                $field_config = json_decode($field_obj->getConfig(), true);
                $return_type = $field_config['return_type'] ?? 'url';
                if ($return_type === 'url') {
                    $decoded_value = json_decode($value_to_save, true);
                    if (is_array($decoded_value) && isset($decoded_value['url'])) {
                        return esc_url_raw($decoded_value['url']);
                    } else {
                        return esc_url_raw($value_to_save);
                    }
                } else {
                    $decoded_value = json_decode($value_to_save, true);
                    return json_encode($decoded_value);
                }
                
            case 'color':
                return sanitize_hex_color($value_to_save) ?: sanitize_text_field($value_to_save);
                
            case 'checkbox':
                if (is_array($value_to_save)) {
                    return implode(',', array_map('sanitize_text_field', $value_to_save));
                }
                return sanitize_text_field($value_to_save);
                
            case 'select':
                if (is_array($value_to_save)) {
                    return implode(',', array_map('sanitize_text_field', $value_to_save));
                }
                return sanitize_text_field($value_to_save);
                
            case 'radio':
                return sanitize_text_field($value_to_save);
                
            case 'wysiwyg':
                return wp_kses_post($value_to_save);
                
            default:
                return wp_kses_post($value_to_save);
        }
    }

    private function sanitizeRepeaterData($data, $config_json) {
        // Check if this is the new format with data and state
        if (is_array($data) && isset($data['data']) && isset($data['state'])) {
            $repeater_data = $data['data'];
            $repeater_state = $data['state'];
            
            // Sanitize the data part
            $sanitized_data = [];
            $config = json_decode($config_json, true);
            $nested_field_definitions = $config['nested_fields'] ?? [];

            foreach ($repeater_data as $item) {
                $sanitized_item = [];
                foreach ($nested_field_definitions as $nested_field_def) {
                    $field_name = $nested_field_def['name'];
                    $field_type = $nested_field_def['type'];
                    $nested_field_config = $nested_field_def['config'] ?? [];

                    if (isset($item[$field_name])) {
                        $value = $item[$field_name];
                        
                        switch ($field_type) {
                            case 'image':
                                $return_type = $nested_field_config['return_type'] ?? 'url';
                                if ($return_type === 'url') {
                                    $decoded_value = json_decode($value, true);
                                    if (is_array($decoded_value) && isset($decoded_value['url'])) {
                                        $sanitized_item[$field_name] = esc_url_raw($decoded_value['url']);
                                    } else {
                                        $sanitized_item[$field_name] = esc_url_raw($value);
                                    }
                                } else {
                                    $decoded_value = json_decode($value, true);
                                    $sanitized_item[$field_name] = json_encode($decoded_value);
                                }
                                break;
                                
                            case 'repeater':
                                $decoded_value = is_array($value) ? $value : json_decode($value, true);
                                $sanitized_item[$field_name] = $this->sanitizeRepeaterData($decoded_value, json_encode($nested_field_config));
                                break;
                                
                            case 'color':
                                $sanitized_item[$field_name] = sanitize_hex_color($value) ?: sanitize_text_field($value);
                                break;
                                
                            case 'checkbox':
                            case 'select':
                                if (is_array($value)) {
                                    $sanitized_item[$field_name] = implode(',', array_map('sanitize_text_field', $value));
                                } else {
                                    $sanitized_item[$field_name] = sanitize_text_field($value);
                                }
                                break;
                                
                            case 'wysiwyg':
                                $sanitized_item[$field_name] = wp_kses_post($value);
                                break;
                                
                            default:
                                $sanitized_item[$field_name] = wp_kses_post($value);
                                break;
                        }
                    }
                }
                $sanitized_data[] = $sanitized_item;
            }

            // Sanitize the state part (ensure it's an array of booleans)
            $sanitized_state = [];
            foreach ($repeater_state as $index => $is_expanded) {
                $sanitized_state[$index] = (bool) $is_expanded;
            }

            // Return the new format with both data and state
            return [
                'data' => $sanitized_data,
                'state' => $sanitized_state
            ];
        } else {
            // Legacy format - just sanitize the data array
            $sanitized_data = [];
            $config = json_decode($config_json, true);
            $nested_field_definitions = $config['nested_fields'] ?? [];

            foreach ($data as $item) {
                $sanitized_item = [];
                foreach ($nested_field_definitions as $nested_field_def) {
                    $field_name = $nested_field_def['name'];
                    $field_type = $nested_field_def['type'];
                    $nested_field_config = $nested_field_def['config'] ?? [];

                    if (isset($item[$field_name])) {
                        $value = $item[$field_name];
                        
                        switch ($field_type) {
                            case 'image':
                                $return_type = $nested_field_config['return_type'] ?? 'url';
                                if ($return_type === 'url') {
                                    $decoded_value = json_decode($value, true);
                                    if (is_array($decoded_value) && isset($decoded_value['url'])) {
                                        $sanitized_item[$field_name] = esc_url_raw($decoded_value['url']);
                                    } else {
                                        $sanitized_item[$field_name] = esc_url_raw($value);
                                    }
                                } else {
                                    $decoded_value = json_decode($value, true);
                                    $sanitized_item[$field_name] = json_encode($decoded_value);
                                }
                                break;
                                
                            case 'repeater':
                                $decoded_value = is_array($value) ? $value : json_decode($value, true);
                                $sanitized_item[$field_name] = $this->sanitizeRepeaterData($decoded_value, json_encode($nested_field_config));
                                break;
                                
                            case 'color':
                                $sanitized_item[$field_name] = sanitize_hex_color($value) ?: sanitize_text_field($value);
                                break;
                                
                            case 'checkbox':
                            case 'select':
                                if (is_array($value)) {
                                    $sanitized_item[$field_name] = implode(',', array_map('sanitize_text_field', $value));
                                } else {
                                    $sanitized_item[$field_name] = sanitize_text_field($value);
                                }
                                break;
                                
                            case 'wysiwyg':
                                $sanitized_item[$field_name] = wp_kses_post($value);
                                break;
                                
                            default:
                                $sanitized_item[$field_name] = wp_kses_post($value);
                                break;
                        }
                    }
                }
                $sanitized_data[] = $sanitized_item;
            }

            return $sanitized_data;
        }
    }

    private function getFieldValues($components, $post_id) {
        global $wpdb;
        $field_values_table = $wpdb->prefix . 'cc_field_values';
        
        $values = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT field_id, instance_id, value FROM $field_values_table WHERE post_id = %d",
                $post_id
            ),
            ARRAY_A
        );
        
        $field_values = [];
        foreach ($values as $value) {
            $instance_id = $value['instance_id'] ?: 'default';
            $field_values[$instance_id][$value['field_id']] = $value['value'];
        }
        
        return $field_values;
    }

    private function renderMetaBoxStyles() {
        ?>
        <style>
            .ccc-add-component-section {
                margin-bottom: 20px;
                padding: 15px;
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            
            .ccc-component-accordion {
                margin-bottom: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: #fff;
            }
            
            .ccc-component-header {
                padding: 12px 15px;
                background: #f7f7f7;
                border-bottom: 1px solid #ddd;
                cursor: move;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .ccc-component-header:hover {
                background: #f0f0f0;
            }
            
            .ccc-component-title {
                font-weight: 600;
                display: flex;
                align-items: center;
            }
            
            .ccc-drag-handle {
                margin-right: 10px;
                color: #666;
                cursor: move;
            }
            
            .ccc-component-order {
                background: #0073aa;
                color: white;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 11px;
                margin-left: 10px;
            }
            
            .ccc-component-instance {
                background: #28a745;
                color: white;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 11px;
                margin-left: 5px;
            }
            
            .ccc-component-actions {
                display: flex;
                gap: 5px;
            }
            
            .ccc-toggle-btn, .ccc-remove-btn {
                padding: 4px 8px;
                font-size: 12px;
                border: none;
                border-radius: 3px;
                cursor: pointer;
            }
            
            .ccc-toggle-btn {
                background: #0073aa;
                color: white;
            }
            
            .ccc-remove-btn {
                background: #dc3232;
                color: white;
            }
            
            .ccc-component-content {
                padding: 15px;
                display: none;
            }
            
            .ccc-component-content.active {
                display: block;
            }
            
            .ccc-field-input {
                margin-bottom: 15px;
            }
            
            .ccc-field-input label {
                display: block;
                font-weight: 500;
                margin-bottom: 5px;
            }
            
            .ccc-field-input input,
            .ccc-field-input textarea,
            .ccc-field-input select {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            
            .ccc-checkbox-options,
            .ccc-radio-options {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            
            .ccc-checkbox-option,
            .ccc-radio-option {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .ccc-checkbox-option input,
            .ccc-radio-option input {
                width: auto;
                margin: 0;
            }
            
            .ccc-wysiwyg-field .wp-editor-wrap {
                margin-top: 5px;
            }
            
            .ccc-sortable-placeholder {
                height: 60px;
                background: #f0f0f0;
                border: 2px dashed #ccc;
                margin-bottom: 10px;
                border-radius: 4px;
            }
            
            .ui-sortable-helper {
                box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            }
        </style>
        <?php
    }

    private function renderMetaBoxScript() {
        ?>
        <script>
            jQuery(document).ready(function ($) {
                var postId = $('#ccc-component-manager').data('post-id');
                var componentsData = JSON.parse($('#ccc-components-data').val() || '[]');

                // --- Persist expand/collapse state using localStorage ---
                function getAccordionState() {
                    var key = 'ccc_accordion_state_' + postId;
                    try {
                        return JSON.parse(localStorage.getItem(key) || '{}');
                    } catch (e) { return {}; }
                }
                function setAccordionState(state) {
                    var key = 'ccc_accordion_state_' + postId;
                    localStorage.setItem(key, JSON.stringify(state));
                }

                // Initialize sortable for main components
                $('#ccc-components-list').sortable({
                    handle: '.ccc-component-header',
                    placeholder: 'ccc-sortable-placeholder',
                    update: function(event, ui) {
                        updateComponentOrder();
                    }
                });

                // Add component functionality (no restriction on duplicates)
                $('#ccc-add-component-btn').on('click', function() {
                    var $dropdown = $('#ccc-component-dropdown');
                    var componentId = $dropdown.val();
                    var componentName = $dropdown.find(':selected').data('name');
                    var componentHandle = $dropdown.find(':selected').data('handle');
                    if (!componentId) {
                        alert('Please select a component to add.');
                        return;
                    }
                    var instanceId = Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                    var newComponent = {
                        id: parseInt(componentId),
                        name: componentName,
                        handle_name: componentHandle,
                        order: componentsData.length,
                        instance_id: instanceId
                    };
                    componentsData.push(newComponent);
                    fetchComponentFields(newComponent, function(component, html) {
                        renderComponentAccordion(component, html);
                        updateComponentOrder();
                    });
                    $dropdown.val('');
                });

                // Remove component functionality
                $(document).on('click', '.ccc-remove-btn', function() {
                    var instanceId = $(this).data('instance-id');
                    var $accordion = $(this).closest('.ccc-component-accordion');
                    if (confirm('Are you sure you want to remove this component instance?')) {
                        componentsData = componentsData.filter(function(comp) {
                            return comp.instance_id !== instanceId;
                        });
                        $accordion.remove();
                        updateComponentOrder();
                        // Remove state from localStorage
                        var state = getAccordionState();
                        delete state[instanceId];
                        setAccordionState(state);
                    }
                });

                // --- Expand/collapse logic ---
                // Toggle on .ccc-toggle-btn only (stop propagation)
                $(document).on('click', '.ccc-toggle-btn', function(e) {
                    e.stopPropagation(); // Prevent event from bubbling to header
                    var $header = $(this).closest('.ccc-component-header');
                    var $accordion = $header.closest('.ccc-component-accordion');
                    var $content = $accordion.find('.ccc-component-content');
                    var instanceId = $accordion.data('instance-id');
                    var state = getAccordionState();
                    $content.toggleClass('active');
                    $header.find('.ccc-toggle-btn').text($content.hasClass('active') ? 'Collapse' : 'Expand');
                    state[instanceId] = $content.hasClass('active');
                    setAccordionState(state);
                });
                // Toggle on .ccc-component-header (but not if clicking the button)
                $(document).on('click', '.ccc-component-header', function(e) {
                    if ($(e.target).hasClass('ccc-toggle-btn')) return;
                    var $header = $(this);
                    var $accordion = $header.closest('.ccc-component-accordion');
                    var $content = $accordion.find('.ccc-component-content');
                    var instanceId = $accordion.data('instance-id');
                    var state = getAccordionState();
                    $content.toggleClass('active');
                    $header.find('.ccc-toggle-btn').text($content.hasClass('active') ? 'Collapse' : 'Expand');
                    state[instanceId] = $content.hasClass('active');
                    setAccordionState(state);
                });

                // On page load, restore expand/collapse state
                var state = getAccordionState();
                $('.ccc-component-accordion').each(function() {
                    var $accordion = $(this);
                    var $content = $accordion.find('.ccc-component-content');
                    var $header = $accordion.find('.ccc-component-header');
                    var instanceId = $accordion.data('instance-id');
                    if (state[instanceId]) {
                        $content.addClass('active');
                        $header.find('.ccc-toggle-btn').text('Collapse');
                    } else {
                        $content.removeClass('active');
                        $header.find('.ccc-toggle-btn').text('Expand');
                    }
                });

                // Save expand/collapse state on form submit (so it persists after save)
                $('form').on('submit', function() {
                    var state = {};
                    $('.ccc-component-accordion').each(function() {
                        var $accordion = $(this);
                        var $content = $accordion.find('.ccc-component-content');
                        var instanceId = $accordion.data('instance-id');
                        state[instanceId] = $content.hasClass('active');
                    });
                    setAccordionState(state);
                });

                // Initialize color pickers
                function initializeColorPickers() {
                    $('.ccc-color-picker').wpColorPicker();
                }
                initializeColorPickers();
                // Use MutationObserver instead of DOMNodeInserted
                const observer = new MutationObserver(function(mutationsList) {
                    for (const mutation of mutationsList) {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1 && $(node).find('.ccc-color-picker').length) {
                                $(node).find('.ccc-color-picker').wpColorPicker();
                            }
                        });
                    }
                });
                observer.observe(document.body, { childList: true, subtree: true });

                function updateComponentOrder() {
                    $('#ccc-components-list .ccc-component-accordion').each(function(index) {
                        var instanceId = $(this).data('instance-id');
                        var componentIndex = componentsData.findIndex(function(comp) {
                            return comp.instance_id === instanceId;
                        });
                        if (componentIndex !== -1) {
                            componentsData[componentIndex].order = index;
                        }
                        $(this).find('.ccc-component-order').text('Order: ' + (index + 1));
                        $(this).find('.ccc-remove-btn').data('instance-id', instanceId);
                    });
                    $('#ccc-components-data').val(JSON.stringify(componentsData));
                }

                function fetchComponentFields(component, callback) {
                    $.ajax({
                        url: cccData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'ccc_get_component_fields',
                            nonce: cccData.nonce,
                            component_id: component.id,
                            post_id: postId,
                            instance_id: component.instance_id
                        },
                        success: function(response) {
                            if (response.success) {
                                component.fields = response.data.fields || [];
                                callback(component, response.data.html || '');
                            }
                        },
                        error: function() {
                            console.error('Failed to fetch component fields.');
                        }
                    });
                }

                function renderComponentAccordion(component, html) {
                    // If HTML is provided (from AJAX), use it; otherwise, build a minimal accordion
                    var accordionHtml = html;
                    if (!accordionHtml) {
                        accordionHtml = `
                        <div class="ccc-component-accordion" data-instance-id="${component.instance_id}">
                            <div class="ccc-component-header">
                                <div class="ccc-component-title">
                                    <span class="ccc-drag-handle dashicons dashicons-menu"></span>
                                    ${component.name}
                                    <span class="ccc-component-order">Order: ${component.order + 1}</span>
                                    <span class="ccc-component-instance">Instance #</span>
                                </div>
                                <div class="ccc-component-actions">
                                    <button type="button" class="ccc-toggle-btn">Expand</button>
                                    <button type="button" class="ccc-remove-btn" data-instance-id="${component.instance_id}">Remove</button>
                                </div>
                            </div>
                            <div class="ccc-component-content"></div>
                        </div>`;
                    }
                    $('#ccc-components-list').append(accordionHtml);
                    updateComponentOrder();
                }

                // Initialize existing accordions
                $('.ccc-component-accordion').each(function(index) {
                    var $this = $(this);
                    $this.find('.ccc-toggle-btn').text('Expand');
                    $this.find('.ccc-component-order').text('Order: ' + (index + 1));
                });
            });
        </script>
        <?php
    }
}
