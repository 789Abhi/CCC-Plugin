<?php
namespace CCC\Admin;

use CCC\Models\Component;
use CCC\Models\Field;
use CCC\Models\FieldValue;

defined('ABSPATH') || exit;

class MetaBoxManager {
    public function init() {
        add_action('add_meta_boxes', [$this, 'addComponentMetaBox']);
        add_action('save_post', [$this, 'saveComponentData'], 10, 2);
    }

    public function addComponentMetaBox() {
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

        // Sort current components by order
        usort($current_components, function($a, $b) {
            return ($a['order'] ?? 0) - ($b['order'] ?? 0);
        });

        $field_values = $this->getFieldValues($components, $post->ID);
        
        ?>
        <div id="ccc-component-manager" data-post-id="<?php echo esc_attr($post->ID); ?>">
            <?php if (empty($components)) : ?>
                <p>No components available. Please create components in the <a href="<?php echo admin_url('admin.php?page=custom-craft-component'); ?>">Custom Components</a> section.</p>
            <?php else : ?>
                
                <!-- Component Selection Dropdown -->
                <div class="ccc-add-component-section">
                    <label for="ccc-component-dropdown">Add Component:</label>
                    <select id="ccc-component-dropdown" style="width: 300px; margin-right: 10px;">
                        <option value="">Select a component to add...</option>
                        <?php foreach ($components as $component) : ?>
                            <option value="<?php echo esc_attr($component->getId()); ?>" 
                                    data-name="<?php echo esc_attr($component->getName()); ?>"
                                    data-handle="<?php echo esc_attr($component->getHandleName()); ?>">
                                <?php echo esc_html($component->getName()); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="ccc-add-component-btn" class="button">Add Component</button>
                </div>

                <!-- Selected Components (Sortable Accordion) -->
                <div id="ccc-selected-components" class="ccc-sortable-components">
                    <h4>Selected Components (drag to reorder):</h4>
                    <div id="ccc-components-list">
                        <?php foreach ($current_components as $index => $comp) : ?>
                            <?php $this->renderComponentAccordion($comp, $index, $field_values); ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Hidden input to store component data -->
                <input type="hidden" id="ccc-components-data" name="ccc_components_data" value="<?php echo esc_attr(json_encode($current_components)); ?>" />
                
                <p><em>Use the dropdown above to add components, then drag to reorder them. You can add the same component multiple times with different values. Component values are saved automatically when you save the page.</em></p>
                <p><strong>Note:</strong> Each component instance can have different field values. The order you set here will be reflected on the frontend.</p>
                
            <?php endif; ?>
        </div>

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
            .ccc-field-input textarea {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
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

        <script>
            jQuery(document).ready(function ($) {
                var postId = $('#ccc-component-manager').data('post-id');
                var componentsData = JSON.parse($('#ccc-components-data').val() || '[]');

                // Initialize sortable
                $('#ccc-components-list').sortable({
                    handle: '.ccc-component-header',
                    placeholder: 'ccc-sortable-placeholder',
                    update: function(event, ui) {
                        updateComponentOrder();
                    }
                });

                // Add component functionality - ALLOW DUPLICATES with unique instances
                $('#ccc-add-component-btn').on('click', function() {
                    var $dropdown = $('#ccc-component-dropdown');
                    var componentId = $dropdown.val();
                    var componentName = $dropdown.find(':selected').data('name');
                    var componentHandle = $dropdown.find(':selected').data('handle');
                    
                    if (!componentId) {
                        alert('Please select a component to add.');
                        return;
                    }
                    
                    // Generate unique instance ID for this component instance
                    var instanceId = Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                    
                    // Add component to data (allow duplicates)
                    var newComponent = {
                        id: parseInt(componentId),
                        name: componentName,
                        handle_name: componentHandle,
                        order: componentsData.length,
                        instance_id: instanceId // Unique identifier for this instance
                    };
                    
                    componentsData.push(newComponent);
                    
                    // Fetch component fields and render
                    fetchComponentFields(newComponent, function(component) {
                        renderComponentAccordion(component);
                        updateComponentOrder();
                    });
                    
                    // Reset dropdown
                    $dropdown.val('');
                });

                // Remove component functionality
                $(document).on('click', '.ccc-remove-btn', function() {
                    var instanceId = $(this).data('instance-id');
                    var $accordion = $(this).closest('.ccc-component-accordion');
                    
                    if (confirm('Are you sure you want to remove this component instance?')) {
                        // Find and remove by instance_id
                        componentsData = componentsData.filter(function(comp) {
                            return comp.instance_id !== instanceId;
                        });
                        $accordion.remove();
                        updateComponentOrder();
                    }
                });

                // Toggle accordion
                $(document).on('click', '.ccc-toggle-btn', function() {
                    var $content = $(this).closest('.ccc-component-accordion').find('.ccc-component-content');
                    $content.toggleClass('active');
                    $(this).text($content.hasClass('active') ? 'Collapse' : 'Expand');
                });

                // Update component order
                function updateComponentOrder() {
                    $('#ccc-components-list .ccc-component-accordion').each(function(index) {
                        var instanceId = $(this).data('instance-id');
                        var componentIndex = componentsData.findIndex(function(comp) {
                            return comp.instance_id === instanceId;
                        });
                        
                        if (componentIndex !== -1) {
                            componentsData[componentIndex].order = index;
                        }
                        
                        // Update visual order indicator
                        $(this).find('.ccc-component-order').text('Order: ' + (index + 1));
                        
                        // Update remove button instance ID
                        $(this).find('.ccc-remove-btn').data('instance-id', instanceId);
                    });
                    
                    // Update hidden input
                    $('#ccc-components-data').val(JSON.stringify(componentsData));
                }

                // Fetch component fields
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
                                callback(component);
                            }
                        },
                        error: function() {
                            console.error('Failed to fetch component fields.');
                        }
                    });
                }

                // Render component accordion
                function renderComponentAccordion(component) {
                    var index = componentsData.length - 1;
                    var instanceCount = componentsData.filter(c => c.id === component.id).length;
                    
                    var accordionHtml = `
                        <div class="ccc-component-accordion" data-instance-id="${component.instance_id}">
                            <div class="ccc-component-header">
                                <div class="ccc-component-title">
                                    <span class="ccc-drag-handle dashicons dashicons-menu"></span>
                                    ${component.name}
                                    <span class="ccc-component-order">Order: ${index + 1}</span>
                                    <span class="ccc-component-instance">Instance #${instanceCount}</span>
                                </div>
                                <div class="ccc-component-actions">
                                    <button type="button" class="ccc-toggle-btn">Expand</button>
                                    <button type="button" class="ccc-remove-btn" data-instance-id="${component.instance_id}">Remove</button>
                                </div>
                            </div>
                            <div class="ccc-component-content">
                                ${renderComponentFields(component, index)}
                            </div>
                        </div>
                    `;
                    
                    $('#ccc-components-list').append(accordionHtml);
                }

                // Render component fields with instance-specific naming
                function renderComponentFields(component, index) {
                    if (!component.fields || component.fields.length === 0) {
                        return '<p>No fields defined for this component.</p>';
                    }
                    
                    var fieldsHtml = '';
                    component.fields.forEach(function(field) {
                        // Use instance-specific field naming
                        var fieldName = `ccc_field_values[${component.instance_id}][${field.id}]`;
                        
                        fieldsHtml += `
                            <div class="ccc-field-input">
                                <label for="ccc_field_${component.instance_id}_${field.id}">${field.label}</label>
                        `;
                        
                        if (field.type === 'text') {
                            fieldsHtml += `
                                <input type="text" 
                                       id="ccc_field_${component.instance_id}_${field.id}" 
                                       name="${fieldName}" 
                                       value="${field.value || ''}" />
                            `;
                        } else if (field.type === 'text-area') {
                            fieldsHtml += `
                                <textarea id="ccc_field_${component.instance_id}_${field.id}" 
                                          name="${fieldName}" 
                                          rows="5">${field.value || ''}</textarea>
                            `;
                        }
                        
                        fieldsHtml += '</div>';
                    });
                    
                    return fieldsHtml;
                }

                // Initialize existing accordions with instance IDs
                $('.ccc-component-accordion').each(function(index) {
                    var $this = $(this);
                    if (!$this.data('instance-id') && componentsData[index]) {
                        // Add instance_id to existing components if missing
                        if (!componentsData[index].instance_id) {
                            componentsData[index].instance_id = Date.now() + '_' + index;
                        }
                        $this.attr('data-instance-id', componentsData[index].instance_id);
                        $this.find('.ccc-remove-btn').attr('data-instance-id', componentsData[index].instance_id);
                    }
                    
                    $this.find('.ccc-toggle-btn').text('Expand');
                    $this.find('.ccc-component-order').text('Order: ' + (index + 1));
                });
            });
        </script>
        <?php
    }

    private function renderComponentAccordion($comp, $index, $field_values) {
        $fields = Field::findByComponent($comp['id']);
        $instance_id = $comp['instance_id'] ?? ('legacy_' . $index);
        
        // Count instances of this component
        $current_components = get_post_meta(get_the_ID(), '_ccc_components', true);
        if (!is_array($current_components)) {
            $current_components = [];
        }
        $instance_count = count(array_filter($current_components, function($c) use ($comp) {
            return $c['id'] === $comp['id'];
        }));
        
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
                <?php if ($fields) : ?>
                    <?php foreach ($fields as $field) : ?>
                        <div class="ccc-field-input">
                            <label for="ccc_field_<?php echo esc_attr($instance_id . '_' . $field->getId()); ?>">
                                <?php echo esc_html($field->getLabel()); ?>
                            </label>
                            <?php 
                            // Instance-specific field naming
                            $field_name = "ccc_field_values[{$instance_id}][{$field->getId()}]";
                            $field_value = $field_values[$instance_id][$field->getId()] ?? '';
                            ?>
                            <?php if ($field->getType() === 'text') : ?>
                                <input type="text" 
                                       id="ccc_field_<?php echo esc_attr($instance_id . '_' . $field->getId()); ?>"
                                       name="<?php echo esc_attr($field_name); ?>"
                                       value="<?php echo esc_attr($field_value); ?>" />
                            <?php elseif ($field->getType() === 'text-area') : ?>
                                <textarea id="ccc_field_<?php echo esc_attr($instance_id . '_' . $field->getId()); ?>"
                                          name="<?php echo esc_attr($field_name); ?>"
                                          rows="5"><?php echo esc_textarea($field_value); ?></textarea>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p>No fields defined for this component.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
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

        // Save component assignments with order and instance IDs
        $components_data = isset($_POST['ccc_components_data']) ? json_decode(wp_unslash($_POST['ccc_components_data']), true) : [];
        
        // Ensure proper structure and sort by order
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
        
        // Sort by order
        usort($components, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        
        update_post_meta($post_id, '_ccc_components', $components);

        // Save field values with instance support
        $field_values = isset($_POST['ccc_field_values']) && is_array($_POST['ccc_field_values']) 
            ? $_POST['ccc_field_values'] 
            : [];

        global $wpdb;
        $field_values_table = $wpdb->prefix . 'cc_field_values';
        
        // Clear existing values for this post
        $wpdb->delete($field_values_table, ['post_id' => $post_id], ['%d']);

        // Save new values with instance support
        foreach ($field_values as $instance_id => $instance_fields) {
            if (!is_array($instance_fields)) continue;
            
            foreach ($instance_fields as $field_id => $value) {
                $field_id = intval($field_id);
                $value = wp_kses_post($value);
                
                if ($value !== '') {
                    $result = $wpdb->insert(
                        $field_values_table,
                        [
                            'post_id' => $post_id,
                            'field_id' => $field_id,
                            'instance_id' => $instance_id,
                            'value' => $value,
                            'created_at' => current_time('mysql')
                        ],
                        ['%d', '%d', '%s', '%s', '%s']
                    );
                    
                    if ($result === false) {
                        error_log("CCC: Failed to save field value for field_id: $field_id, instance: $instance_id, post_id: $post_id, error: " . $wpdb->last_error);
                    } else {
                        error_log("CCC: Successfully saved field value for field_id: $field_id, instance: $instance_id, post_id: $post_id, value: $value");
                    }
                }
            }
        }
    }

    private function getFieldValues($components, $post_id) {
        global $wpdb;
        $field_values_table = $wpdb->prefix . 'cc_field_values';
        
        // Get all field values for this post with instance support
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
        
        error_log("CCC: Retrieved field values for post $post_id: " . count($values) . " total values");
        
        return $field_values;
    }
}
