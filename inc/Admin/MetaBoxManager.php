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
                
                <p><em>Use the dropdown above to add components, then drag to reorder them. Component values are saved automatically when you save the page.</em></p>
                <p><strong>Note:</strong> The order you set here will be reflected on the frontend. Components at the top will appear first on your page.</p>
                
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

                // Add component functionality
                $('#ccc-add-component-btn').on('click', function() {
                    var $dropdown = $('#ccc-component-dropdown');
                    var componentId = $dropdown.val();
                    var componentName = $dropdown.find(':selected').data('name');
                    var componentHandle = $dropdown.find(':selected').data('handle');
                    
                    if (!componentId) {
                        alert('Please select a component to add.');
                        return;
                    }
                    
                    // Check if component already exists
                    var exists = componentsData.some(function(comp) {
                        return comp.id == componentId;
                    });
                    
                    if (exists) {
                        alert('This component is already added.');
                        return;
                    }
                    
                    // Add component to data
                    var newComponent = {
                        id: parseInt(componentId),
                        name: componentName,
                        handle_name: componentHandle,
                        order: componentsData.length
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
                    var index = $(this).data('index');
                    var $accordion = $(this).closest('.ccc-component-accordion');
                    
                    if (confirm('Are you sure you want to remove this component?')) {
                        componentsData.splice(index, 1);
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
                        var componentId = $(this).data('component-id');
                        var componentIndex = componentsData.findIndex(function(comp) {
                            return comp.id == componentId;
                        });
                        
                        if (componentIndex !== -1) {
                            componentsData[componentIndex].order = index;
                        }
                        
                        // Update visual order indicator
                        $(this).find('.ccc-component-order').text('Order: ' + (index + 1));
                        
                        // Update remove button index
                        $(this).find('.ccc-remove-btn').data('index', componentIndex);
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
                            post_id: postId
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
                    var accordionHtml = `
                        <div class="ccc-component-accordion" data-component-id="${component.id}">
                            <div class="ccc-component-header">
                                <div class="ccc-component-title">
                                    <span class="ccc-drag-handle dashicons dashicons-menu"></span>
                                    ${component.name}
                                    <span class="ccc-component-order">Order: ${index + 1}</span>
                                </div>
                                <div class="ccc-component-actions">
                                    <button type="button" class="ccc-toggle-btn">Expand</button>
                                    <button type="button" class="ccc-remove-btn" data-index="${index}">Remove</button>
                                </div>
                            </div>
                            <div class="ccc-component-content">
                                ${renderComponentFields(component, index)}
                            </div>
                        </div>
                    `;
                    
                    $('#ccc-components-list').append(accordionHtml);
                }

                // Render component fields
                function renderComponentFields(component, index) {
                    if (!component.fields || component.fields.length === 0) {
                        return '<p>No fields defined for this component.</p>';
                    }
                    
                    var fieldsHtml = '';
                    component.fields.forEach(function(field) {
                        fieldsHtml += `
                            <div class="ccc-field-input">
                                <label for="ccc_field_${field.id}">${field.label}</label>
                        `;
                        
                        if (field.type === 'text') {
                            fieldsHtml += `
                                <input type="text" 
                                       id="ccc_field_${field.id}" 
                                       name="ccc_field_values[${field.id}]" 
                                       value="${field.value || ''}" />
                            `;
                        } else if (field.type === 'text-area') {
                            fieldsHtml += `
                                <textarea id="ccc_field_${field.id}" 
                                          name="ccc_field_values[${field.id}]" 
                                          rows="5">${field.value || ''}</textarea>
                            `;
                        }
                        
                        fieldsHtml += '</div>';
                    });
                    
                    return fieldsHtml;
                }

                // Initialize existing accordions
                $('.ccc-component-accordion').each(function(index) {
                    $(this).find('.ccc-toggle-btn').text('Expand');
                    $(this).find('.ccc-component-order').text('Order: ' + (index + 1));
                });
            });
        </script>
        <?php
    }

    private function renderComponentAccordion($comp, $index, $field_values) {
        $fields = Field::findByComponent($comp['id']);
        ?>
        <div class="ccc-component-accordion" data-component-id="<?php echo esc_attr($comp['id']); ?>">
            <div class="ccc-component-header">
                <div class="ccc-component-title">
                    <span class="ccc-drag-handle dashicons dashicons-menu"></span>
                    <?php echo esc_html($comp['name']); ?>
                    <span class="ccc-component-order">Order: <?php echo esc_attr($index + 1); ?></span>
                </div>
                <div class="ccc-component-actions">
                    <button type="button" class="ccc-toggle-btn">Expand</button>
                    <button type="button" class="ccc-remove-btn" data-index="<?php echo esc_attr($index); ?>">Remove</button>
                </div>
            </div>
            <div class="ccc-component-content">
                <?php if ($fields) : ?>
                    <?php foreach ($fields as $field) : ?>
                        <div class="ccc-field-input">
                            <label for="ccc_field_<?php echo esc_attr($field->getId()); ?>">
                                <?php echo esc_html($field->getLabel()); ?>
                            </label>
                            <?php if ($field->getType() === 'text') : ?>
                                <input type="text" 
                                       id="ccc_field_<?php echo esc_attr($field->getId()); ?>"
                                       name="ccc_field_values[<?php echo esc_attr($field->getId()); ?>]"
                                       value="<?php echo esc_attr($field_values[$field->getId()] ?: ''); ?>" />
                            <?php elseif ($field->getType() === 'text-area') : ?>
                                <textarea id="ccc_field_<?php echo esc_attr($field->getId()); ?>"
                                          name="ccc_field_values[<?php echo esc_attr($field->getId()); ?>]"
                                          rows="5"><?php echo esc_textarea($field_values[$field->getId()] ?: ''); ?></textarea>
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

        // Save component assignments with order
        $components_data = isset($_POST['ccc_components_data']) ? json_decode(wp_unslash($_POST['ccc_components_data']), true) : [];
        
        // Ensure proper structure and sort by order
        $components = [];
        foreach ($components_data as $comp) {
            $components[] = [
                'id' => intval($comp['id']),
                'name' => sanitize_text_field($comp['name']),
                'handle_name' => sanitize_text_field($comp['handle_name']),
                'order' => intval($comp['order'] ?? 0)
            ];
        }
        
        // Sort by order
        usort($components, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        
        update_post_meta($post_id, '_ccc_components', $components);

        // Save field values - this preserves all field values
        $field_values = isset($_POST['ccc_field_values']) && is_array($_POST['ccc_field_values']) 
            ? $_POST['ccc_field_values'] 
            : [];

        // Don't delete existing values, just update/add new ones
        foreach ($field_values as $field_id => $value) {
            $field_id = intval($field_id);
            $value = wp_kses_post($value);
            
            global $wpdb;
            $field_values_table = $wpdb->prefix . 'cc_field_values';
            
            // Check if value exists
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $field_values_table WHERE field_id = %d AND post_id = %d",
                    $field_id, $post_id
                )
            );
            
            if ($existing) {
                // Update existing value
                $wpdb->update(
                    $field_values_table,
                    ['value' => $value],
                    ['field_id' => $field_id, 'post_id' => $post_id],
                    ['%s'],
                    ['%d', '%d']
                );
            } else {
                // Insert new value
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
        }
    }

    private function getFieldValues($components, $post_id) {
        $field_values = [];
        
        foreach ($components as $component) {
            $fields = Field::findByComponent($component->getId());
            foreach ($fields as $field) {
                $field_values[$field->getId()] = $field->getValue($post_id);
            }
        }
        
        return $field_values;
    }
}
