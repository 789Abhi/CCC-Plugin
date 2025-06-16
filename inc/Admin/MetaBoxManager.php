<?php
namespace CCC\Admin;

use CCC\Models\Component;
use CCC\Models\Field;
use CCC\Models\FieldValue;
use CCC\Helpers\GlobalHelpers;

defined('ABSPATH') || exit;

class MetaBoxManager {
    public function __construct() {
        // Constructor no longer registers actions directly.
    }

    public function init() {
        add_action('add_meta_boxes', [$this, 'addComponentMetaBox'], 10, 2);
        add_action('save_post', [$this, 'saveComponentData'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueueMediaLibrary']);
    }
    
    public function enqueueMediaLibrary($hook) {
        if ('post.php' == $hook || 'post-new.php' == $hook) {
            wp_enqueue_media();
        }
    }

    public function addComponentMetaBox($post_type, $post = null) {
        if (!$post || !($post instanceof \WP_Post) || empty($post->ID)) {
            return;
        }

        $current_components = get_post_meta($post->ID, '_ccc_components', true);
        if (empty($current_components) || !is_array($current_components)) {
            return;
        }

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
            <?php if (empty($components)) : ?>
                <p>No components available. Please create components in the <a href="<?php echo admin_url('admin.php?page=custom-craft-component'); ?>">Custom Components</a> section.</p>
            <?php else : ?>
                
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

                <div id="ccc-selected-components" class="ccc-sortable-components">
                    <h4>Selected Components (drag to reorder):</h4>
                    <div id="ccc-components-list">
                        <?php foreach ($current_components as $index => $comp) : ?>
                            <?php $this->renderComponentAccordion($comp, $index, $field_values); ?>
                        <?php endforeach; ?>
                    </div>
                </div>

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
            
            /* Image field styles */
            .ccc-image-field {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .ccc-image-field input[type="hidden"] {
                display: none;
            }
            .ccc-image-preview-container {
                flex-grow: 1;
            }
            .ccc-image-preview {
                max-width: 150px;
                max-height: 150px;
                margin-top: 10px;
                border: 1px solid #ddd;
                padding: 5px;
                background: #f9f9f9;
            }
            
            .ccc-image-preview img {
                max-width: 100%;
                height: auto;
            }
            
            /* Repeater field styles */
            .ccc-repeater-container {
                border: 1px solid #ddd;
                padding: 10px;
                margin-bottom: 10px;
                background: #f9f9f9;
                border-radius: 4px;
            }
            
            .ccc-repeater-items {
                min-height: 50px;
            }

            .ccc-repeater-item {
                border: 1px solid #e0e0e0;
                padding: 10px;
                margin-bottom: 10px;
                background: #fff;
                border-radius: 3px;
                position: relative;
            }
            
            .ccc-repeater-item-header {
                padding: 8px 10px;
                background: #f0f0f0;
                border-bottom: 1px solid #e0e0e0;
                cursor: move;
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
            }
            
            /* Enhanced styles for deeply nested repeaters */
            .ccc-deeply-nested-repeater {
                background: #f5f5f5;
                border: 2px solid #ccc;
                margin: 10px 0;
            }
            
            .ccc-triple-nested-repeater {
                background: #eeeeee;
                border: 2px solid #999;
                margin: 8px 0;
            }
            
            .ccc-deeply-nested-item {
                background: #fafafa;
                border: 1px solid #bbb;
            }
            
            .ccc-triple-nested-item {
                background: #f0f0f0;
                border: 1px solid #888;
            }
            
            .ccc-repeater-controls {
                display: flex;
                gap: 5px;
            }
            
            .ccc-repeater-add {
                background: #0073aa;
                color: white;
                padding: 5px 10px;
                border: none;
                border-radius: 3px;
                cursor: pointer;
                margin-top: 10px;
            }
            
            .ccc-repeater-remove {
                background: #dc3232;
                color: white;
                padding: 2px 5px;
                border: none;
                border-radius: 3px;
                cursor: pointer;
                font-size: 11px;
            }
            
            .ccc-nested-field {
                margin-bottom: 10px;
            }
            
            .ccc-nested-field-label {
                display: block;
                font-weight: 500;
                margin-bottom: 5px;
            }
            
            .ccc-nested-field-input {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            
            .ccc-nested-image-field {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .ccc-nested-image-field input[type="hidden"] {
                display: none;
            }
            .ccc-nested-image-preview-container {
                flex-grow: 1;
            }
            .ccc-nested-image-preview {
                max-width: 100px;
                max-height: 100px;
                margin-top: 5px;
                border: 1px solid #ddd;
                padding: 2px;
                background: #f9f9f9;
            }
            
            .ccc-nested-image-preview img {
                max-width: 100%;
                height: auto;
            }
        </style>

        <script>
            jQuery(document).ready(function ($) {
                var postId = $('#ccc-component-manager').data('post-id');
                var componentsData = JSON.parse($('#ccc-components-data').val() || '[]');

                // Initialize sortable for main components
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
                    
                    var instanceId = Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                    
                    var newComponent = {
                        id: parseInt(componentId),
                        name: componentName,
                        handle_name: componentHandle,
                        order: componentsData.length,
                        instance_id: instanceId
                    };
                    
                    componentsData.push(newComponent);
                    
                    fetchComponentFields(newComponent, function(component) {
                        renderComponentAccordion(component);
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
                        
                        $(this).find('.ccc-component-order').text('Order: ' + (index + 1));
                        $(this).find('.ccc-remove-btn').data('instance-id', instanceId);
                    });
                    
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
                    
                    initializeMediaUploaders(component.instance_id);
                    initializeRepeaterFields(component.instance_id);
                }

                // Render component fields with instance-specific naming
                function renderComponentFields(component, index) {
                    if (!component.fields || component.fields.length === 0) {
                        return '<p>No fields defined for this component.</p>';
                    }
                    
                    var fieldsHtml = '';
                    component.fields.forEach(function(field) {
                        var fieldName = `ccc_field_values[${component.instance_id}][${field.id}]`;
                        var fieldConfig = field.config || {};
                        var imageReturnType = fieldConfig.return_type || 'url';

                        fieldsHtml += `
                            <div class="ccc-field-input" data-field-type="${field.type}">
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
                        } else if (field.type === 'image') {
                            var imagePreview = field.value ? `
                                <div class="ccc-image-preview">
                                    <img src="${imageReturnType === 'url' ? field.value : (JSON.parse(field.value || '{}').url || '')}" alt="Selected image" />
                                </div>
                            ` : '';
                            
                            fieldsHtml += `
                                <div class="ccc-image-field">
                                    <input type="hidden" 
                                           class="ccc-image-field-input" 
                                           id="ccc_field_${component.instance_id}_${field.id}" 
                                           name="${fieldName}" 
                                           value="${field.value || ''}"
                                           data-return-type="${imageReturnType}" />
                                    <button type="button" 
                                            class="button ccc-upload-image-btn" 
                                            data-field-id="${field.id}"
                                            data-instance-id="${component.instance_id}"
                                            data-return-type="${imageReturnType}">
                                        Select Image
                                    </button>
                                    <button type="button" 
                                            class="button ccc-remove-image-btn" 
                                            data-field-id="${field.id}"
                                            data-instance-id="${component.instance_id}"
                                            data-return-type="${imageReturnType}"
                                            style="display: ${field.value ? 'inline-block' : 'none'}">
                                        Remove Image
                                    </button>
                                    <div class="ccc-image-preview-container" id="ccc_image_preview_${component.instance_id}_${field.id}">
                                        ${imagePreview}
                                    </div>
                                </div>
                            `;
                        } else if (field.type === 'repeater') {
                            var repeaterValue = field.value ? JSON.parse(field.value) : [];
                            var maxSets = fieldConfig.max_sets ? parseInt(fieldConfig.max_sets) : 0;
                            var nestedFieldDefinitions = fieldConfig.nested_fields ? fieldConfig.nested_fields : [];
                            
                            fieldsHtml += `
                                <div class="ccc-repeater-container" 
                                     data-field-id="${field.id}"
                                     data-instance-id="${component.instance_id}"
                                     data-max-sets="${maxSets}"
                                     data-nested-field-definitions='${JSON.stringify(nestedFieldDefinitions)}'>
                                    <div class="ccc-repeater-items">
                            `;
                            
                            if (repeaterValue.length > 0) {
                                repeaterValue.forEach(function(item, itemIndex) {
                                    fieldsHtml += renderRepeaterItem(component.instance_id, field.id, itemIndex, item, nestedFieldDefinitions, 1);
                                });
                            } else {
                                if (maxSets === 0 || maxSets > 0) {
                                    fieldsHtml += renderRepeaterItem(component.instance_id, field.id, 0, {}, nestedFieldDefinitions, 1);
                                }
                            }
                            
                            fieldsHtml += `
                                    </div>
                                    <button type="button" 
                                            class="ccc-repeater-add button"
                                            data-field-id="${field.id}"
                                            data-instance-id="${component.instance_id}">
                                        Add Item
                                    </button>
                                    <input type="hidden" 
                                           class="ccc-repeater-main-input"
                                           id="ccc_field_${component.instance_id}_${field.id}" 
                                           name="${fieldName}" 
                                           value="${field.value || '[]'}" />
                                </div>
                            `;
                        }
                        
                        fieldsHtml += '</div>';
                    });
                    
                    return fieldsHtml;
                }
                
                // Enhanced renderRepeaterItem with nesting level support
                function renderRepeaterItem(parentInstanceId, parentFieldId, itemIndex, itemData, nestedFieldDefinitions, nestingLevel) {
                    nestingLevel = nestingLevel || 1;
                    var fieldsHtml = '';
                    
                    nestedFieldDefinitions.forEach(function(nestedField) {
                        var nestedFieldValue = itemData[nestedField.name] || '';
                        var nestedFieldConfig = nestedField.config || {};
                        var nestedImageReturnType = nestedFieldConfig.return_type || 'url';

                        fieldsHtml += `
                            <div class="ccc-nested-field" data-nested-field-name="${nestedField.name}" data-nested-field-type="${nestedField.type}">
                                <label class="ccc-nested-field-label">${nestedField.label}</label>
                        `;
                        
                        if (nestedField.type === 'text') {
                            fieldsHtml += `
                                <input type="text" 
                                       class="ccc-nested-field-input" 
                                       data-nested-field-type="text"
                                       value="${nestedFieldValue}" />
                            `;
                        } else if (nestedField.type === 'textarea') {
                            fieldsHtml += `
                                <textarea class="ccc-nested-field-input" 
                                          data-nested-field-type="textarea"
                                          rows="3">${nestedFieldValue}</textarea>
                            `;
                        } else if (nestedField.type === 'image') {
                            var imageSrc = '';
                            var displayRemoveBtn = 'none';
                            if (nestedFieldValue) {
                                if (nestedImageReturnType === 'url') {
                                    imageSrc = nestedFieldValue;
                                } else {
                                    try {
                                        var imageData = JSON.parse(nestedFieldValue);
                                        imageSrc = imageData.url || nestedFieldValue;
                                    } catch (e) {
                                        imageSrc = nestedFieldValue;
                                    }
                                }
                                displayRemoveBtn = 'inline-block';
                            }

                            var imagePreview = imageSrc ? `
                                <div class="ccc-nested-image-preview">
                                    <img src="${imageSrc}" alt="Selected image" />
                                </div>
                            ` : '';
                            
                            fieldsHtml += `
                                <div class="ccc-nested-image-field">
                                    <input type="hidden" 
                                           class="ccc-nested-field-input" 
                                           data-nested-field-type="image"
                                           value="${nestedFieldValue}"
                                           data-return-type="${nestedImageReturnType}" />
                                    <button type="button" class="button ccc-nested-upload-image" data-return-type="${nestedImageReturnType}">Select Image</button>
                                    <button type="button" class="button ccc-nested-remove-image" data-return-type="${nestedImageReturnType}" style="display: ${displayRemoveBtn}">Remove</button>
                                    <div class="ccc-nested-image-preview-container">
                                        ${imagePreview}
                                    </div>
                                </div>
                            `;
                        } else if (nestedField.type === 'repeater') {
                            // ENHANCED: Support for triple nested repeaters
                            var deeplyNestedRepeaterValue = Array.isArray(nestedFieldValue) ? nestedFieldValue : (nestedFieldValue ? JSON.parse(nestedFieldValue) : []);
                            var deeplyNestedMaxSets = nestedFieldConfig.max_sets ? parseInt(nestedFieldConfig.max_sets) : 0;
                            var deeplyNestedFieldDefinitions = nestedFieldConfig.nested_fields ? nestedFieldConfig.nested_fields : [];
                            
                            var containerClass = nestingLevel === 1 ? 'ccc-deeply-nested-repeater' : 'ccc-triple-nested-repeater';
                            var itemClass = nestingLevel === 1 ? 'ccc-deeply-nested-item' : 'ccc-triple-nested-item';

                            fieldsHtml += `
                                <div class="ccc-repeater-container ${containerClass}" 
                                     data-field-id="${nestedField.id || nestedField.name}" 
                                     data-instance-id="${parentInstanceId}"
                                     data-parent-field-id="${parentFieldId}"
                                     data-parent-item-index="${itemIndex}"
                                     data-nested-field-name="${nestedField.name}"
                                     data-max-sets="${deeplyNestedMaxSets}"
                                     data-nesting-level="${nestingLevel + 1}"
                                     data-nested-field-definitions='${JSON.stringify(deeplyNestedFieldDefinitions)}'>
                                    <label class="ccc-nested-field-label">${nestedField.label} (Level ${nestingLevel + 1})</label>
                                    <div class="ccc-repeater-items">
                            `;

                            if (deeplyNestedRepeaterValue.length > 0) {
                                deeplyNestedRepeaterValue.forEach(function(deepItem, deepItemIndex) {
                                    fieldsHtml += renderDeeplyNestedRepeaterItem(parentInstanceId, parentFieldId, itemIndex, nestedField.name, deepItemIndex, deepItem, deeplyNestedFieldDefinitions, nestingLevel + 1);
                                });
                            } else {
                                if (deeplyNestedMaxSets === 0 || deeplyNestedMaxSets > 0) {
                                    fieldsHtml += renderDeeplyNestedRepeaterItem(parentInstanceId, parentFieldId, itemIndex, nestedField.name, 0, {}, deeplyNestedFieldDefinitions, nestingLevel + 1);
                                }
                            }

                            fieldsHtml += `
                                    </div>
                                    <button type="button" 
                                            class="ccc-repeater-add button"
                                            data-field-id="${nestedField.id || nestedField.name}"
                                            data-instance-id="${parentInstanceId}"
                                            data-parent-field-id="${parentFieldId}"
                                            data-parent-item-index="${itemIndex}"
                                            data-nested-field-name="${nestedField.name}"
                                            data-nesting-level="${nestingLevel + 1}">
                                        Add Level ${nestingLevel + 1} Item
                                    </button>
                                    <input type="hidden" 
                                           class="ccc-nested-field-input ccc-repeater-main-input"
                                           data-nested-field-type="repeater"
                                           value='${JSON.stringify(deeplyNestedRepeaterValue)}' />
                                </div>
                            `;
                        }
                        
                        fieldsHtml += `</div>`;
                    });
                    
                    var itemClass = nestingLevel === 1 ? 'ccc-repeater-item' : (nestingLevel === 2 ? 'ccc-deeply-nested-item' : 'ccc-triple-nested-item');
                    
                    return `
                        <div class="ccc-repeater-item ${itemClass}" data-index="${itemIndex}" data-nesting-level="${nestingLevel}">
                            <div class="ccc-repeater-item-header">
                                <span class="ccc-drag-handle dashicons dashicons-menu"></span>
                                <strong>Level ${nestingLevel} Item #${itemIndex + 1}</strong>
                                <div class="ccc-repeater-controls">
                                    <button type="button" class="ccc-repeater-remove button">Remove</button>
                                </div>
                            </div>
                            <div class="ccc-repeater-item-fields">
                                ${fieldsHtml}
                            </div>
                        </div>
                    `;
                }

                // Enhanced renderDeeplyNestedRepeaterItem with nesting level support
                function renderDeeplyNestedRepeaterItem(grandparentInstanceId, grandparentFieldId, parentItemIndex, nestedRepeaterName, deepItemIndex, deepItemData, deeplyNestedFieldDefinitions, nestingLevel) {
                    nestingLevel = nestingLevel || 2;
                    var fieldsHtml = '';
                    
                    deeplyNestedFieldDefinitions.forEach(function(deepNestedField) {
                        var deepNestedFieldValue = deepItemData[deepNestedField.name] || '';
                        var deepNestedFieldConfig = deepNestedField.config || {};
                        var deepNestedImageReturnType = deepNestedFieldConfig.return_type || 'url';

                        fieldsHtml += `
                            <div class="ccc-nested-field" data-nested-field-name="${deepNestedField.name}" data-nested-field-type="${deepNestedField.type}">
                                <label class="ccc-nested-field-label">${deepNestedField.label}</label>
                        `;
                        
                        if (deepNestedField.type === 'text') {
                            fieldsHtml += `
                                <input type="text" 
                                       class="ccc-nested-field-input" 
                                       data-nested-field-type="text"
                                       value="${deepNestedFieldValue}" />
                            `;
                        } else if (deepNestedField.type === 'textarea') {
                            fieldsHtml += `
                                <textarea class="ccc-nested-field-input" 
                                          data-nested-field-type="textarea"
                                          rows="3">${deepNestedFieldValue}</textarea>
                            `;
                        } else if (deepNestedField.type === 'image') {
                            var imageSrc = '';
                            var displayRemoveBtn = 'none';
                            if (deepNestedFieldValue) {
                                if (deepNestedImageReturnType === 'url') {
                                    imageSrc = deepNestedFieldValue;
                                } else {
                                    try {
                                        var imageData = JSON.parse(deepNestedFieldValue);
                                        imageSrc = imageData.url || deepNestedFieldValue;
                                    } catch (e) {
                                        imageSrc = deepNestedFieldValue;
                                    }
                                }
                                displayRemoveBtn = 'inline-block';
                            }

                            var imagePreview = imageSrc ? `
                                <div class="ccc-nested-image-preview">
                                    <img src="${imageSrc}" alt="Selected image" />
                                </div>
                            ` : '';
                            
                            fieldsHtml += `
                                <div class="ccc-nested-image-field">
                                    <input type="hidden" 
                                           class="ccc-nested-field-input" 
                                           data-nested-field-type="image"
                                           value="${deepNestedFieldValue}"
                                           data-return-type="${deepNestedImageReturnType}" />
                                    <button type="button" class="button ccc-nested-upload-image" data-return-type="${deepNestedImageReturnType}">Select Image</button>
                                    <button type="button" class="button ccc-nested-remove-image" data-return-type="${deepNestedImageReturnType}" style="display: ${displayRemoveBtn}">Remove</button>
                                    <div class="ccc-nested-image-preview-container">
                                        ${imagePreview}
                                    </div>
                                </div>
                            `;
                        } else if (deepNestedField.type === 'repeater' && nestingLevel < 4) {
                            // ENHANCED: Support for even deeper nesting (up to level 4)
                            var tripleNestedRepeaterValue = Array.isArray(deepNestedFieldValue) ? deepNestedFieldValue : (deepNestedFieldValue ? JSON.parse(deepNestedFieldValue) : []);
                            var tripleNestedMaxSets = deepNestedFieldConfig.max_sets ? parseInt(deepNestedFieldConfig.max_sets) : 0;
                            var tripleNestedFieldDefinitions = deepNestedFieldConfig.nested_fields ? deepNestedFieldConfig.nested_fields : [];

                            fieldsHtml += `
                                <div class="ccc-repeater-container ccc-triple-nested-repeater" 
                                     data-field-id="${deepNestedField.id || deepNestedField.name}" 
                                     data-instance-id="${grandparentInstanceId}"
                                     data-grandparent-field-id="${grandparentFieldId}"
                                     data-parent-item-index="${parentItemIndex}"
                                     data-nested-field-name="${nestedRepeaterName}"
                                     data-deep-item-index="${deepItemIndex}"
                                     data-deep-nested-field-name="${deepNestedField.name}"
                                     data-max-sets="${tripleNestedMaxSets}"
                                     data-nesting-level="${nestingLevel + 1}"
                                     data-nested-field-definitions='${JSON.stringify(tripleNestedFieldDefinitions)}'>
                                    <label class="ccc-nested-field-label">${deepNestedField.label} (Level ${nestingLevel + 1})</label>
                                    <div class="ccc-repeater-items">
                            `;

                            if (tripleNestedRepeaterValue.length > 0) {
                                tripleNestedRepeaterValue.forEach(function(tripleItem, tripleItemIndex) {
                                    fieldsHtml += renderTripleNestedRepeaterItem(grandparentInstanceId, grandparentFieldId, parentItemIndex, nestedRepeaterName, deepItemIndex, deepNestedField.name, tripleItemIndex, tripleItem, tripleNestedFieldDefinitions, nestingLevel + 1);
                                });
                            } else {
                                if (tripleNestedMaxSets === 0 || tripleNestedMaxSets > 0) {
                                    fieldsHtml += renderTripleNestedRepeaterItem(grandparentInstanceId, grandparentFieldId, parentItemIndex, nestedRepeaterName, deepItemIndex, deepNestedField.name, 0, {}, tripleNestedFieldDefinitions, nestingLevel + 1);
                                }
                            }

                            fieldsHtml += `
                                    </div>
                                    <button type="button" 
                                            class="ccc-repeater-add button"
                                            data-field-id="${deepNestedField.id || deepNestedField.name}"
                                            data-instance-id="${grandparentInstanceId}"
                                            data-grandparent-field-id="${grandparentFieldId}"
                                            data-parent-item-index="${parentItemIndex}"
                                            data-nested-field-name="${nestedRepeaterName}"
                                            data-deep-item-index="${deepItemIndex}"
                                            data-deep-nested-field-name="${deepNestedField.name}"
                                            data-nesting-level="${nestingLevel + 1}">
                                        Add Level ${nestingLevel + 1} Item
                                    </button>
                                    <input type="hidden" 
                                           class="ccc-nested-field-input ccc-repeater-main-input"
                                           data-nested-field-type="repeater"
                                           value='${JSON.stringify(tripleNestedRepeaterValue)}' />
                                </div>
                            `;
                        } else if (deepNestedField.type === 'repeater') {
                            fieldsHtml += `<p><em>Maximum nesting level (4) reached for repeater fields</em></p>`;
                        }
                        
                        fieldsHtml += `</div>`;
                    });
                    
                    var itemClass = nestingLevel === 2 ? 'ccc-deeply-nested-item' : 'ccc-triple-nested-item';
                    
                    return `
                        <div class="ccc-repeater-item ${itemClass}" data-index="${deepItemIndex}" data-nesting-level="${nestingLevel}">
                            <div class="ccc-repeater-item-header">
                                <span class="ccc-drag-handle dashicons dashicons-menu"></span>
                                <strong>Level ${nestingLevel} Item #${deepItemIndex + 1}</strong>
                                <div class="ccc-repeater-controls">
                                    <button type="button" class="ccc-repeater-remove button">Remove</button>
                                </div>
                            </div>
                            <div class="ccc-repeater-item-fields">
                                ${fieldsHtml}
                            </div>
                        </div>
                    `;
                }

                // NEW: Function for triple nested repeater items
                function renderTripleNestedRepeaterItem(greatGrandparentInstanceId, greatGrandparentFieldId, grandparentItemIndex, grandparentNestedFieldName, parentItemIndex, parentNestedFieldName, tripleItemIndex, tripleItemData, tripleNestedFieldDefinitions, nestingLevel) {
                    nestingLevel = nestingLevel || 3;
                    var fieldsHtml = '';
                    
                    tripleNestedFieldDefinitions.forEach(function(tripleNestedField) {
                        var tripleNestedFieldValue = tripleItemData[tripleNestedField.name] || '';
                        var tripleNestedFieldConfig = tripleNestedField.config || {};
                        var tripleNestedImageReturnType = tripleNestedFieldConfig.return_type || 'url';

                        fieldsHtml += `
                            <div class="ccc-nested-field" data-nested-field-name="${tripleNestedField.name}" data-nested-field-type="${tripleNestedField.type}">
                                <label class="ccc-nested-field-label">${tripleNestedField.label}</label>
                        `;
                        
                        if (tripleNestedField.type === 'text') {
                            fieldsHtml += `
                                <input type="text" 
                                       class="ccc-nested-field-input" 
                                       data-nested-field-type="text"
                                       value="${tripleNestedFieldValue}" />
                            `;
                        } else if (tripleNestedField.type === 'textarea') {
                            fieldsHtml += `
                                <textarea class="ccc-nested-field-input" 
                                          data-nested-field-type="textarea"
                                          rows="3">${tripleNestedFieldValue}</textarea>
                            `;
                        } else if (tripleNestedField.type === 'image') {
                            var imageSrc = '';
                            var displayRemoveBtn = 'none';
                            if (tripleNestedFieldValue) {
                                if (tripleNestedImageReturnType === 'url') {
                                    imageSrc = tripleNestedFieldValue;
                                } else {
                                    try {
                                        var imageData = JSON.parse(tripleNestedFieldValue);
                                        imageSrc = imageData.url || tripleNestedFieldValue;
                                    } catch (e) {
                                        imageSrc = tripleNestedFieldValue;
                                    }
                                }
                                displayRemoveBtn = 'inline-block';
                            }

                            var imagePreview = imageSrc ? `
                                <div class="ccc-nested-image-preview">
                                    <img src="${imageSrc}" alt="Selected image" />
                                </div>
                            ` : '';
                            
                            fieldsHtml += `
                                <div class="ccc-nested-image-field">
                                    <input type="hidden" 
                                           class="ccc-nested-field-input" 
                                           data-nested-field-type="image"
                                           value="${tripleNestedFieldValue}"
                                           data-return-type="${tripleNestedImageReturnType}" />
                                    <button type="button" class="button ccc-nested-upload-image" data-return-type="${tripleNestedImageReturnType}">Select Image</button>
                                    <button type="button" class="button ccc-nested-remove-image" data-return-type="${tripleNestedImageReturnType}" style="display: ${displayRemoveBtn}">Remove</button>
                                    <div class="ccc-nested-image-preview-container">
                                        ${imagePreview}
                                    </div>
                                </div>
                            `;
                        } else if (tripleNestedField.type === 'repeater') {
                            fieldsHtml += `<p><em>Maximum nesting level (4) reached for repeater fields</em></p>`;
                        }
                        
                        fieldsHtml += `</div>`;
                    });
                    
                    return `
                        <div class="ccc-repeater-item ccc-triple-nested-item" data-index="${tripleItemIndex}" data-nesting-level="${nestingLevel}">
                            <div class="ccc-repeater-item-header">
                                <span class="ccc-drag-handle dashicons dashicons-menu"></span>
                                <strong>Level ${nestingLevel} Item #${tripleItemIndex + 1}</strong>
                                <div class="ccc-repeater-controls">
                                    <button type="button" class="ccc-repeater-remove button">Remove</button>
                                </div>
                            </div>
                            <div class="ccc-repeater-item-fields">
                                ${fieldsHtml}
                            </div>
                        </div>
                    `;
                }
                
                // Enhanced initializeRepeaterFields with multi-level support
                function initializeRepeaterFields(instanceId) {
                    // Make all repeater items sortable regardless of nesting level
                    $(`.ccc-repeater-container .ccc-repeater-items`).sortable({
                        handle: '.ccc-repeater-item-header',
                        placeholder: 'ccc-sortable-placeholder',
                        update: function(event, ui) {
                            var $container = $(this).closest('.ccc-repeater-container');
                            var nestingLevel = parseInt($container.data('nesting-level')) || 1;
                            
                            if (nestingLevel === 1) {
                                var fieldId = $container.data('field-id');
                                updateRepeaterValue(instanceId, fieldId);
                            } else if (nestingLevel === 2) {
                                var parentFieldId = $container.data('parent-field-id');
                                var parentItemIndex = $container.data('parent-item-index');
                                var nestedFieldName = $container.data('nested-field-name');
                                updateDeeplyNestedRepeaterValue(instanceId, parentFieldId, parentItemIndex, nestedFieldName);
                            } else if (nestingLevel === 3) {
                                var grandparentFieldId = $container.data('grandparent-field-id');
                                var parentItemIndex = $container.data('parent-item-index');
                                var grandparentNestedFieldName = $container.data('nested-field-name');
                                var parentItemIndex2 = $container.data('deep-item-index');
                                var deepNestedFieldName = $container.data('deep-nested-field-name');
                                updateTripleNestedRepeaterValue(instanceId, grandparentFieldId, parentItemIndex, grandparentNestedFieldName, parentItemIndex2, deepNestedFieldName);
                            }
                        }
                    });
                    
                    // Enhanced add repeater item functionality
                    $(document).off('click', `.ccc-repeater-add`).on('click', `.ccc-repeater-add`, function() {
                        var $button = $(this);
                        var $container = $button.closest('.ccc-repeater-container');
                        var $items = $container.find('.ccc-repeater-items').first();
                        var maxSets = parseInt($container.data('max-sets')) || 0;
                        var currentCount = $items.children().length;
                        var nestedFieldDefinitions = JSON.parse($container.attr('data-nested-field-definitions') || '[]');
                        var nestingLevel = parseInt($container.data('nesting-level')) || 1;
                        
                        if (maxSets > 0 && currentCount >= maxSets) {
                            alert(`Maximum number of items (${maxSets}) reached.`);
                            return;
                        }
                        
                        var newIndex = currentCount;
                        var newItem;

                        if (nestingLevel === 1) {
                            var fieldId = $button.data('field-id');
                            var instanceId = $button.data('instance-id');
                            newItem = $(renderRepeaterItem(instanceId, fieldId, newIndex, {}, nestedFieldDefinitions, nestingLevel));
                        } else if (nestingLevel === 2) {
                            var instanceId = $button.data('instance-id');
                            var parentFieldId = $button.data('parent-field-id');
                            var parentItemIndex = $button.data('parent-item-index');
                            var nestedFieldName = $button.data('nested-field-name');
                            newItem = $(renderDeeplyNestedRepeaterItem(instanceId, parentFieldId, parentItemIndex, nestedFieldName, newIndex, {}, nestedFieldDefinitions, nestingLevel));
                        } else if (nestingLevel === 3) {
                            var instanceId = $button.data('instance-id');
                            var grandparentFieldId = $button.data('grandparent-field-id');
                            var parentItemIndex = $button.data('parent-item-index');
                            var grandparentNestedFieldName = $button.data('nested-field-name');
                            var deepItemIndex = $button.data('deep-item-index');
                            var deepNestedFieldName = $button.data('deep-nested-field-name');
                            newItem = $(renderTripleNestedRepeaterItem(instanceId, grandparentFieldId, parentItemIndex, grandparentNestedFieldName, deepItemIndex, deepNestedFieldName, newIndex, {}, nestedFieldDefinitions, nestingLevel));
                        }
                        
                        $items.append(newItem);
                        initializeNestedMediaUploaders(newItem);
                        
                        // Update the appropriate repeater value
                        if (nestingLevel === 1) {
                            updateRepeaterValue($button.data('instance-id'), $button.data('field-id'));
                        } else if (nestingLevel === 2) {
                            updateDeeplyNestedRepeaterValue($button.data('instance-id'), $button.data('parent-field-id'), $button.data('parent-item-index'), $button.data('nested-field-name'));
                        } else if (nestingLevel === 3) {
                            updateTripleNestedRepeaterValue($button.data('instance-id'), $button.data('grandparent-field-id'), $button.data('parent-item-index'), $button.data('nested-field-name'), $button.data('deep-item-index'), $button.data('deep-nested-field-name'));
                        }
                    });
                    
                    // Enhanced remove repeater item functionality
                    $(document).off('click', `.ccc-repeater-remove`).on('click', `.ccc-repeater-remove`, function() {
                        var $item = $(this).closest('.ccc-repeater-item');
                        var $container = $item.closest('.ccc-repeater-container');
                        var $items = $container.find('.ccc-repeater-items').first();
                        var nestingLevel = parseInt($item.data('nesting-level')) || 1;
                        
                        if (confirm('Are you sure you want to remove this item?')) {
                            $item.remove();
                            
                            // Update indices
                            $items.children().each(function(idx) {
                                $(this).attr('data-index', idx);
                                $(this).find('.ccc-repeater-item-header strong').first().text(`Level ${nestingLevel} Item #${idx + 1}`);
                            });
                            
                            // Update the appropriate repeater value
                            if (nestingLevel === 1) {
                                var instanceId = $container.data('instance-id');
                                var fieldId = $container.data('field-id');
                                updateRepeaterValue(instanceId, fieldId);
                            } else if (nestingLevel === 2) {
                                var instanceId = $container.data('instance-id');
                                var parentFieldId = $container.data('parent-field-id');
                                var parentItemIndex = $container.data('parent-item-index');
                                var nestedFieldName = $container.data('nested-field-name');
                                updateDeeplyNestedRepeaterValue(instanceId, parentFieldId, parentItemIndex, nestedFieldName);
                            } else if (nestingLevel === 3) {
                                var instanceId = $container.data('instance-id');
                                var grandparentFieldId = $container.data('grandparent-field-id');
                                var parentItemIndex = $container.data('parent-item-index');
                                var grandparentNestedFieldName = $container.data('nested-field-name');
                                var deepItemIndex = $container.data('deep-item-index');
                                var deepNestedFieldName = $container.data('deep-nested-field-name');
                                updateTripleNestedRepeaterValue(instanceId, grandparentFieldId, parentItemIndex, grandparentNestedFieldName, deepItemIndex, deepNestedFieldName);
                            }
                        }
                    });
                    
                    // Update repeater value when nested fields change
                    $(document).off('change keyup', `.ccc-nested-field-input`).on('change keyup', `.ccc-nested-field-input`, function() {
                        var $fieldInput = $(this);
                        var $container = $fieldInput.closest('.ccc-repeater-container');
                        var nestingLevel = parseInt($container.data('nesting-level')) || 1;
                        
                        if (nestingLevel === 1) {
                            var instanceId = $container.data('instance-id');
                            var fieldId = $container.data('field-id');
                            updateRepeaterValue(instanceId, fieldId);
                        } else if (nestingLevel === 2) {
                            var instanceId = $container.data('instance-id');
                            var parentFieldId = $container.data('parent-field-id');
                            var parentItemIndex = $container.data('parent-item-index');
                            var nestedFieldName = $container.data('nested-field-name');
                            updateDeeplyNestedRepeaterValue(instanceId, parentFieldId, parentItemIndex, nestedFieldName);
                        } else if (nestingLevel === 3) {
                            var instanceId = $container.data('instance-id');
                            var grandparentFieldId = $container.data('grandparent-field-id');
                            var parentItemIndex = $container.data('parent-item-index');
                            var grandparentNestedFieldName = $container.data('nested-field-name');
                            var deepItemIndex = $container.data('deep-item-index');
                            var deepNestedFieldName = $container.data('deep-nested-field-name');
                            updateTripleNestedRepeaterValue(instanceId, grandparentFieldId, parentItemIndex, grandparentNestedFieldName, deepItemIndex, deepNestedFieldName);
                        }
                    });

                    // Initialize media uploaders for all existing nested image fields
                    $(`.ccc-repeater-container .ccc-nested-image-field`).each(function() {
                        initializeNestedMediaUploaders($(this).closest('.ccc-repeater-item'));
                    });
                }

                // Initialize media uploader for image fields (top level)
                function initializeMediaUploaders(instanceId) {
                    $(document).off('click', `.ccc-upload-image-btn[data-instance-id="${instanceId}"]`).on('click', `.ccc-upload-image-btn[data-instance-id="${instanceId}"]`, function(e) {
                        e.preventDefault();
                        var $button = $(this);
                        var $input = $button.siblings('.ccc-image-field-input');
                        var $previewContainer = $button.siblings('.ccc-image-preview-container');
                        var $removeButton = $button.siblings('.ccc-remove-image-btn');
                        var returnType = $input.data('return-type') || 'url';

                        var frame = wp.media({
                            title: 'Select Image',
                            button: { text: 'Use this image' },
                            multiple: false
                        });

                        frame.on('select', function() {
                            var attachment = frame.state().get('selection').first().toJSON();
                            
                            if (returnType === 'url') {
                                $input.val(attachment.url);
                            } else {
                                $input.val(JSON.stringify({
                                    id: attachment.id,
                                    url: attachment.url,
                                    alt: attachment.alt,
                                    title: attachment.title
                                }));
                            }
                            $previewContainer.html('<div class="ccc-image-preview"><img src="' + attachment.url + '" alt="Selected image" /></div>');
                            $removeButton.show();
                        });

                        frame.open();
                    });

                    $(document).off('click', `.ccc-remove-image-btn[data-instance-id="${instanceId}"]`).on('click', `.ccc-remove-image-btn[data-instance-id="${instanceId}"]`, function(e) {
                        e.preventDefault();
                        var $button = $(this);
                        var $input = $button.siblings('.ccc-image-field-input');
                        var $previewContainer = $button.siblings('.ccc-image-preview-container');
                        
                        $input.val('');
                        $previewContainer.empty();
                        $button.hide();
                    });
                }

                // Initialize media uploader for nested image fields
                function initializeNestedMediaUploaders($item) {
                    $item.find('.ccc-nested-upload-image').off('click').on('click', function(e) {
                        e.preventDefault();
                        var $button = $(this);
                        var $input = $button.siblings('.ccc-nested-field-input');
                        var $previewContainer = $button.siblings('.ccc-nested-image-preview-container');
                        var $removeButton = $button.siblings('.ccc-nested-remove-image');
                        var returnType = $input.data('return-type') || 'url';
                        
                        var frame = wp.media({
                            title: 'Select Image',
                            button: { text: 'Use this image' },
                            multiple: false
                        });
                        
                        frame.on('select', function() {
                            var attachment = frame.state().get('selection').first().toJSON();
                            
                            if (returnType === 'url') {
                                $input.val(attachment.url);
                            } else {
                                $input.val(JSON.stringify({
                                    id: attachment.id,
                                    url: attachment.url,
                                    alt: attachment.alt,
                                    title: attachment.title
                                }));
                            }
                            $previewContainer.html(`<div class="ccc-nested-image-preview"><img src="${attachment.url}" alt="${attachment.alt}" /></div>`);
                            $removeButton.show();
                            
                            $input.trigger('change');
                        });
                        
                        frame.open();
                    });
                    
                    $item.find('.ccc-nested-remove-image').off('click').on('click', function(e) {
                        e.preventDefault();
                        var $button = $(this);
                        var $input = $button.siblings('.ccc-nested-field-input');
                        var $previewContainer = $button.siblings('.ccc-nested-image-preview-container');
                        
                        $input.val('');
                        $previewContainer.empty();
                        $button.hide();
                        
                        $input.trigger('change');
                    });
                }
                
                // Update repeater value (for top-level repeaters)
                function updateRepeaterValue(instanceId, fieldId) {
                    var $container = $(`.ccc-repeater-container[data-instance-id="${instanceId}"][data-field-id="${fieldId}"]`);
                    var $input = $container.find('.ccc-repeater-main-input');
                    var $items = $container.find('.ccc-repeater-items');
                    var repeaterData = [];
                    var nestedFieldDefinitions = JSON.parse($container.attr('data-nested-field-definitions') || '[]');
                    
                    $items.children('.ccc-repeater-item').each(function() {
                        var $item = $(this);
                        var itemData = {};
                        
                        nestedFieldDefinitions.forEach(function(nestedField) {
                            var $nestedInput = $item.find(`.ccc-nested-field[data-nested-field-name="${nestedField.name}"] .ccc-nested-field-input`);
                            if ($nestedInput.length) {
                                var fieldValue = $nestedInput.val();
                                itemData[nestedField.name] = fieldValue;
                            } else if (nestedField.type === 'repeater') {
                                var $deeplyNestedRepeaterContainer = $item.find(`.ccc-deeply-nested-repeater[data-nested-field-name="${nestedField.name}"]`);
                                var $deeplyNestedRepeaterInput = $deeplyNestedRepeaterContainer.find('.ccc-repeater-main-input');
                                if ($deeplyNestedRepeaterInput.length) {
                                    itemData[nestedField.name] = JSON.parse($deeplyNestedRepeaterInput.val() || '[]');
                                }
                            }
                        });
                        
                        repeaterData.push(itemData);
                    });
                    
                    $input.val(JSON.stringify(repeaterData));
                }

                // Update deeply nested repeater value
                function updateDeeplyNestedRepeaterValue(parentInstanceId, parentFieldId, parentItemIndex, nestedRepeaterName) {
                    var $parentRepeaterContainer = $(`.ccc-repeater-container[data-instance-id="${parentInstanceId}"][data-field-id="${parentFieldId}"]`);
                    var $parentRepeaterItem = $parentRepeaterContainer.find(`.ccc-repeater-item[data-index="${parentItemIndex}"]`);
                    var $deeplyNestedRepeaterContainer = $parentRepeaterItem.find(`.ccc-deeply-nested-repeater[data-nested-field-name="${nestedRepeaterName}"]`);
                    var $deeplyNestedRepeaterInput = $deeplyNestedRepeaterContainer.find('.ccc-repeater-main-input');
                    var $deeplyNestedItems = $deeplyNestedRepeaterContainer.find('.ccc-repeater-items');
                    var deeplyNestedRepeaterData = [];
                    var deeplyNestedFieldDefinitions = JSON.parse($deeplyNestedRepeaterContainer.attr('data-nested-field-definitions') || '[]');

                    $deeplyNestedItems.children('.ccc-repeater-item').each(function() {
                        var $deepItem = $(this);
                        var deepItemData = {};

                        deeplyNestedFieldDefinitions.forEach(function(deepNestedField) {
                            var $deepNestedInput = $deepItem.find(`.ccc-nested-field[data-nested-field-name="${deepNestedField.name}"] .ccc-nested-field-input`);
                            if ($deepNestedInput.length) {
                                deepItemData[deepNestedField.name] = $deepNestedInput.val();
                            } else if (deepNestedField.type === 'repeater') {
                                var $tripleNestedRepeaterContainer = $deepItem.find(`.ccc-triple-nested-repeater[data-deep-nested-field-name="${deepNestedField.name}"]`);
                                var $tripleNestedRepeaterInput = $tripleNestedRepeaterContainer.find('.ccc-repeater-main-input');
                                if ($tripleNestedRepeaterInput.length) {
                                    deepItemData[deepNestedField.name] = JSON.parse($tripleNestedRepeaterInput.val() || '[]');
                                }
                            }
                        });
                        deeplyNestedRepeaterData.push(deepItemData);
                    });

                    $deeplyNestedRepeaterInput.val(JSON.stringify(deeplyNestedRepeaterData));
                    
                    // Trigger change on the parent repeater's main input
                    $parentRepeaterContainer.find('.ccc-repeater-main-input').trigger('change');
                }

                // NEW: Update triple nested repeater value
                function updateTripleNestedRepeaterValue(grandparentInstanceId, grandparentFieldId, grandparentItemIndex, grandparentNestedFieldName, parentItemIndex, deepNestedFieldName) {
                    var $grandparentRepeaterContainer = $(`.ccc-repeater-container[data-instance-id="${grandparentInstanceId}"][data-field-id="${grandparentFieldId}"]`);
                    var $grandparentRepeaterItem = $grandparentRepeaterContainer.find(`.ccc-repeater-item[data-index="${grandparentItemIndex}"]`);
                    var $parentRepeaterContainer = $grandparentRepeaterItem.find(`.ccc-deeply-nested-repeater[data-nested-field-name="${grandparentNestedFieldName}"]`);
                    var $parentRepeaterItem = $parentRepeaterContainer.find(`.ccc-repeater-item[data-index="${parentItemIndex}"]`);
                    var $tripleNestedRepeaterContainer = $parentRepeaterItem.find(`.ccc-triple-nested-repeater[data-deep-nested-field-name="${deepNestedFieldName}"]`);
                    var $tripleNestedRepeaterInput = $tripleNestedRepeaterContainer.find('.ccc-repeater-main-input');
                    var $tripleNestedItems = $tripleNestedRepeaterContainer.find('.ccc-repeater-items');
                    var tripleNestedRepeaterData = [];
                    var tripleNestedFieldDefinitions = JSON.parse($tripleNestedRepeaterContainer.attr('data-nested-field-definitions') || '[]');

                    $tripleNestedItems.children('.ccc-repeater-item').each(function() {
                        var $tripleItem = $(this);
                        var tripleItemData = {};

                        tripleNestedFieldDefinitions.forEach(function(tripleNestedField) {
                            var $tripleNestedInput = $tripleItem.find(`.ccc-nested-field[data-nested-field-name="${tripleNestedField.name}"] .ccc-nested-field-input`);
                            if ($tripleNestedInput.length) {
                                tripleItemData[tripleNestedField.name] = $tripleNestedInput.val();
                            }
                        });
                        tripleNestedRepeaterData.push(tripleItemData);
                    });

                    $tripleNestedRepeaterInput.val(JSON.stringify(tripleNestedRepeaterData));
                    
                    // Trigger change on the parent repeater's main input
                    $parentRepeaterContainer.find('.ccc-repeater-main-input').trigger('change');
                }

                // Initialize existing accordions with instance IDs
                $('.ccc-component-accordion').each(function(index) {
                    var $this = $(this);
                    if (!$this.data('instance-id') && componentsData[index]) {
                        if (!componentsData[index].instance_id) {
                            componentsData[index].instance_id = Date.now() + '_' + index;
                        }
                        $this.attr('data-instance-id', componentsData[index].instance_id);
                        $this.find('.ccc-remove-btn').attr('data-instance-id', componentsData[index].instance_id);
                    }
                    
                    $this.find('.ccc-toggle-btn').text('Expand');
                    $this.find('.ccc-component-order').text('Order: ' + (index + 1));
                    
                    initializeMediaUploaders(componentsData[index].instance_id);
                    initializeRepeaterFields(componentsData[index].instance_id);
                });
            });
        </script>
        <?php
    }

    protected function renderComponentAccordion($comp, $index, $field_values) {
        $fields = Field::findByComponent($comp['id']);
        $instance_id = $comp['instance_id'] ?? ('legacy_' . $index);
        
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
                        <div class="ccc-field-input" data-field-type="<?php echo esc_attr($field->getType()); ?>">
                            <label for="ccc_field_<?php echo esc_attr($instance_id . '_' . $field->getId()); ?>">
                                <?php echo esc_html($field->getLabel()); ?>
                            </label>
                            <?php 
                            $field_name = "ccc_field_values[{$instance_id}][{$field->getId()}]";
                            $field_value = $field_values[$instance_id][$field->getId()] ?? '';
                            $field_config = json_decode($field->getConfig(), true);
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
                                        
                            <?php elseif ($field->getType() === 'image') : 
                                $image_return_type = $field_config['return_type'] ?? 'url';
                                $image_src = '';
                                if ($field_value) {
                                    if ($image_return_type === 'url') {
                                        $image_src = $field_value;
                                    } else {
                                        $decoded_value = json_decode($field_value, true);
                                        $image_src = $decoded_value['url'] ?? '';
                                    }
                                }
                            ?>
                                <div class="ccc-image-field">
                                    <input type="hidden" 
                                           class="ccc-image-field-input"
                                           id="ccc_field_<?php echo esc_attr($instance_id . '_' . $field->getId()); ?>"
                                           name="<?php echo esc_attr($field_name); ?>"
                                           value="<?php echo esc_attr($field_value); ?>"
                                           data-return-type="<?php echo esc_attr($image_return_type); ?>" />
                                    <button type="button" 
                                            class="button ccc-upload-image-btn" 
                                            data-field-id="<?php echo esc_attr($field->getId()); ?>"
                                            data-instance-id="<?php echo esc_attr($instance_id); ?>"
                                            data-return-type="<?php echo esc_attr($image_return_type); ?>">
                                        Select Image
                                    </button>
                                    <button type="button" 
                                            class="button ccc-remove-image-btn" 
                                            data-field-id="<?php echo esc_attr($field->getId()); ?>"
                                            data-instance-id="<?php echo esc_attr($instance_id); ?>"
                                            data-return-type="<?php echo esc_attr($image_return_type); ?>"
                                            style="display: <?php echo $field_value ? 'inline-block' : 'none'; ?>">
                                        Remove Image
                                    </button>
                                    <div class="ccc-image-preview-container" id="ccc_image_preview_<?php echo esc_attr($instance_id . '_' . $field->getId()); ?>">
                                        <?php if ($image_src) : ?>
                                            <div class="ccc-image-preview">
                                                <img src="<?php echo esc_url($image_src); ?>" alt="Selected image" />
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                            <?php elseif ($field->getType() === 'repeater') : 
                                $repeater_value = $field_value ? json_decode($field_value, true) : [];
                                $max_sets = isset($field_config['max_sets']) ? intval($field_config['max_sets']) : 0;
                                $nested_field_definitions = isset($field_config['nested_fields']) ? $field_config['nested_fields'] : [];
                                ?>
                                <div class="ccc-repeater-container" 
                                     data-field-id="<?php echo esc_attr($field->getId()); ?>"
                                     data-instance-id="<?php echo esc_attr($instance_id); ?>"
                                     data-max-sets="<?php echo esc_attr($max_sets); ?>"
                                     data-nested-field-definitions='<?php echo esc_attr(json_encode($nested_field_definitions)); ?>'>
                                    <div class="ccc-repeater-items">
                                        <?php if (!empty($repeater_value)) : ?>
                                            <?php foreach ($repeater_value as $item_index => $item_data) : ?>
                                                <?php $this->renderRepeaterItem($instance_id, $field->getId(), $item_index, $item_data, $nested_field_definitions); ?>
                                            <?php endforeach; ?>
                                        <?php else : ?>
                                            <?php if ($max_sets === 0 || $max_sets > 0) : ?>
                                                <?php $this->renderRepeaterItem($instance_id, $field->getId(), 0, [], $nested_field_definitions); ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" 
                                            class="ccc-repeater-add button"
                                            data-field-id="<?php echo esc_attr($field->getId()); ?>"
                                            data-instance-id="<?php echo esc_attr($instance_id); ?>">
                                        Add Item
                                    </button>
                                    <input type="hidden" 
                                           class="ccc-repeater-main-input"
                                           id="ccc_field_<?php echo esc_attr($instance_id . '_' . $field->getId()); ?>"
                                           name="<?php echo esc_attr($field_name); ?>"
                                           value="<?php echo esc_attr($field_value ?: '[]'); ?>" />
                                </div>
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

    protected function renderRepeaterItem($parent_instance_id, $parent_field_id, $item_index, $item_data, $nested_field_definitions) {
        ?>
        <div class="ccc-repeater-item" data-index="<?php echo esc_attr($item_index); ?>">
            <div class="ccc-repeater-item-header">
                <span class="ccc-drag-handle dashicons dashicons-menu"></span>
                <strong>Item #<?php echo esc_html($item_index + 1); ?></strong>
                <div class="ccc-repeater-controls">
                    <button type="button" class="ccc-repeater-remove button">Remove</button>
                </div>
            </div>
            <div class="ccc-repeater-item-fields">
                <?php foreach ($nested_field_definitions as $nested_field) : 
                    $nested_field_value = $item_data[$nested_field['name']] ?? '';
                    $nested_field_config = $nested_field['config'] ?? [];
                ?>
                    <div class="ccc-nested-field" 
                         data-nested-field-name="<?php echo esc_attr($nested_field['name']); ?>" 
                         data-nested-field-type="<?php echo esc_attr($nested_field['type']); ?>">
                        <label class="ccc-nested-field-label"><?php echo esc_html($nested_field['label']); ?></label>
                        <?php if ($nested_field['type'] === 'text') : ?>
                            <input type="text" 
                                   class="ccc-nested-field-input" 
                                   data-nested-field-type="text"
                                   value="<?php echo esc_attr($nested_field_value); ?>" />
                        <?php elseif ($nested_field['type'] === 'textarea') : ?>
                            <textarea class="ccc-nested-field-input" 
                                      data-nested-field-type="textarea"
                                      rows="3"><?php echo esc_textarea($nested_field_value); ?></textarea>
                        <?php elseif ($nested_field['type'] === 'image') : 
                            $nested_image_return_type = $nested_field_config['return_type'] ?? 'url';
                            $image_src = '';
                            $display_remove_btn = 'none';
                            if ($nested_field_value) {
                                if ($nested_image_return_type === 'url') {
                                    $image_src = $nested_field_value;
                                } else {
                                    try {
                                        $imageData = json_decode($nested_field_value, true);
                                        $image_src = $imageData['url'] ?? '';
                                    } catch (\Exception $e) {
                                        $image_src = $nested_field_value;
                                    }
                                }
                                $display_remove_btn = 'inline-block';
                            }
                        ?>
                            <div class="ccc-nested-image-field">
                                <input type="hidden" 
                                       class="ccc-nested-field-input" 
                                       data-nested-field-type="image"
                                       value="<?php echo esc_attr($nested_field_value); ?>"
                                       data-return-type="<?php echo esc_attr($nested_image_return_type); ?>" />
                                <button type="button" class="button ccc-nested-upload-image" data-return-type="<?php echo esc_attr($nested_image_return_type); ?>">Select Image</button>
                                <button type="button" class="button ccc-nested-remove-image" data-return-type="<?php echo esc_attr($nested_image_return_type); ?>" style="display: <?php echo $display_remove_btn; ?>">Remove</button>
                                <div class="ccc-nested-image-preview-container">
                                    <?php if ($image_src) : ?>
                                        <div class="ccc-nested-image-preview">
                                            <img src="<?php echo esc_url($image_src); ?>" alt="Selected image" />
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php elseif ($nested_field['type'] === 'repeater') : 
                            $deeply_nested_repeater_value = is_array($nested_field_value) ? $nested_field_value : ($nested_field_value ? json_decode($nested_field_value, true) : []);
                            $deeply_nested_max_sets = isset($nested_field_config['max_sets']) ? intval($nested_field_config['max_sets']) : 0;
                            $deeply_nested_field_definitions = isset($nested_field_config['nested_fields']) ? $nested_field_config['nested_fields'] : [];
                        ?>
                            <div class="ccc-repeater-container ccc-deeply-nested-repeater" 
                                 data-field-id="<?php echo esc_attr($nested_field['id'] ?? $nested_field['name']); ?>" 
                                 data-instance-id="<?php echo esc_attr($parent_instance_id); ?>"
                                 data-parent-field-id="<?php echo esc_attr($parent_field_id); ?>"
                                 data-parent-item-index="<?php echo esc_attr($item_index); ?>"
                                 data-nested-field-name="<?php echo esc_attr($nested_field['name']); ?>"
                                 data-max-sets="<?php echo esc_attr($deeply_nested_max_sets); ?>"
                                 data-nesting-level="2"
                                 data-nested-field-definitions='<?php echo esc_attr(json_encode($nested_field_definitions)); ?>'>
                                <label class="ccc-nested-field-label"><?php echo esc_html($nested_field['label']); ?> (Level 2)</label>
                                <div class="ccc-repeater-items">
                                    <?php if (!empty($deeply_nested_repeater_value)) : ?>
                                        <?php foreach ($deeply_nested_repeater_value as $deep_item_index => $deep_item_data) : ?>
                                            <?php $this->renderDeeplyNestedRepeaterItem($parent_instance_id, $parent_field_id, $item_index, $nested_field['name'], $deep_item_index, $deep_item_data, $deeply_nested_field_definitions); ?>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <?php if ($deeply_nested_max_sets === 0 || $deeply_nested_max_sets > 0) : ?>
                                            <?php $this->renderDeeplyNestedRepeaterItem($parent_instance_id, $parent_field_id, $item_index, $nested_field['name'], 0, [], $deeply_nested_field_definitions); ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" 
                                        class="ccc-repeater-add button"
                                        data-field-id="<?php echo esc_attr($nested_field['id'] ?? $nested_field['name']); ?>"
                                        data-instance-id="<?php echo esc_attr($parent_instance_id); ?>"
                                        data-parent-field-id="<?php echo esc_attr($parent_field_id); ?>"
                                        data-parent-item-index="<?php echo esc_attr($item_index); ?>"
                                        data-nested-field-name="<?php echo esc_attr($nested_field['name']); ?>"
                                        data-nesting-level="2">
                                    Add Level 2 Item
                                </button>
                                <input type="hidden" 
                                       class="ccc-nested-field-input ccc-repeater-main-input"
                                       data-nested-field-type="repeater"
                                       value='<?php echo esc_attr(json_encode($deeply_nested_repeater_value)); ?>' />
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    protected function renderDeeplyNestedRepeaterItem($grandparent_instance_id, $grandparent_field_id, $parent_item_index, $parent_nested_field_name, $deep_item_index, $deep_item_data, $deeply_nested_field_definitions) {
        ?>
        <div class="ccc-repeater-item ccc-deeply-nested-item" data-index="<?php echo esc_attr($deep_item_index); ?>" data-nesting-level="2">
            <div class="ccc-repeater-item-header">
                <span class="ccc-drag-handle dashicons dashicons-menu"></span>
                <strong>Level 2 Item #<?php echo esc_html($deep_item_index + 1); ?></strong>
                <div class="ccc-repeater-controls">
                    <button type="button" class="ccc-repeater-remove button">Remove</button>
                </div>
            </div>
            <div class="ccc-repeater-item-fields">
                <?php foreach ($deeply_nested_field_definitions as $deep_nested_field) : 
                    $deep_nested_field_value = $deep_item_data[$deep_nested_field['name']] ?? '';
                    $deep_nested_field_config = $deep_nested_field['config'] ?? [];
                ?>
                    <div class="ccc-nested-field" 
                         data-nested-field-name="<?php echo esc_attr($deep_nested_field['name']); ?>" 
                         data-nested-field-type="<?php echo esc_attr($deep_nested_field['type']); ?>">
                        <label class="ccc-nested-field-label"><?php echo esc_html($deep_nested_field['label']); ?></label>
                        <?php if ($deep_nested_field['type'] === 'text') : ?>
                            <input type="text" 
                                   class="ccc-nested-field-input" 
                                   data-nested-field-type="text"
                                   value="<?php echo esc_attr($deep_nested_field_value); ?>" />
                        <?php elseif ($deep_nested_field['type'] === 'textarea') : ?>
                            <textarea class="ccc-nested-field-input" 
                                      data-nested-field-type="textarea"
                                      rows="3"><?php echo esc_textarea($deep_nested_field_value); ?></textarea>
                        <?php elseif ($deep_nested_field['type'] === 'image') : 
                            $deep_nested_image_return_type = $deep_nested_field_config['return_type'] ?? 'url';
                            $image_src = '';
                            $display_remove_btn = 'none';
                            if ($deep_nested_field_value) {
                                if ($deep_nested_image_return_type === 'url') {
                                    $image_src = $deep_nested_field_value;
                                } else {
                                    try {
                                        $imageData = json_decode($deep_nested_field_value, true);
                                        $image_src = $imageData['url'] ?? '';
                                    } catch (\Exception $e) {
                                        $image_src = $deep_nested_field_value;
                                    }
                                }
                                $display_remove_btn = 'inline-block';
                            }
                        ?>
                            <div class="ccc-nested-image-field">
                                <input type="hidden" 
                                       class="ccc-nested-field-input" 
                                       data-nested-field-type="image"
                                       value="<?php echo esc_attr($deep_nested_field_value); ?>"
                                       data-return-type="<?php echo esc_attr($deep_nested_image_return_type); ?>" />
                                <button type="button" class="button ccc-nested-upload-image" data-return-type="<?php echo esc_attr($deep_nested_image_return_type); ?>">Select Image</button>
                                <button type="button" class="button ccc-nested-remove-image" data-return-type="<?php echo esc_attr($deep_nested_image_return_type); ?>" style="display: <?php echo $display_remove_btn; ?>">Remove</button>
                                <div class="ccc-nested-image-preview-container">
                                    <?php if ($image_src) : ?>
                                        <div class="ccc-nested-image-preview">
                                            <img src="<?php echo esc_url($image_src); ?>" alt="Selected image" />
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php elseif ($deep_nested_field['type'] === 'repeater') : 
                            $triple_nested_repeater_value = is_array($deep_nested_field_value) ? $deep_nested_field_value : ($deep_nested_field_value ? json_decode($deep_nested_field_value, true) : []);
                            $triple_nested_max_sets = isset($deep_nested_field_config['max_sets']) ? intval($deep_nested_field_config['max_sets']) : 0;
                            $triple_nested_field_definitions = isset($deep_nested_field_config['nested_fields']) ? $deep_nested_field_config['nested_fields'] : [];
                        ?>
                            <div class="ccc-repeater-container ccc-triple-nested-repeater" 
                                 data-field-id="<?php echo esc_attr($deep_nested_field['id'] ?? $deep_nested_field['name']); ?>" 
                                 data-instance-id="<?php echo esc_attr($grandparent_instance_id); ?>"
                                 data-grandparent-field-id="<?php echo esc_attr($grandparent_field_id); ?>"
                                 data-parent-item-index="<?php echo esc_attr($parent_item_index); ?>"
                                 data-nested-field-name="<?php echo esc_attr($parent_nested_field_name); ?>"
                                 data-deep-item-index="<?php echo esc_attr($deep_item_index); ?>"
                                 data-deep-nested-field-name="<?php echo esc_attr($deep_nested_field['name']); ?>"
                                 data-max-sets="<?php echo esc_attr($triple_nested_max_sets); ?>"
                                 data-nesting-level="3"
                                 data-nested-field-definitions='<?php echo esc_attr(json_encode($triple_nested_field_definitions)); ?>'>
                                <label class="ccc-nested-field-label"><?php echo esc_html($deep_nested_field['label']); ?> (Level 3)</label>
                                <div class="ccc-repeater-items">
                                    <?php if (!empty($triple_nested_repeater_value)) : ?>
                                        <?php foreach ($triple_nested_repeater_value as $triple_item_index => $triple_item_data) : ?>
                                            <?php $this->renderTripleNestedRepeaterItem($grandparent_instance_id, $grandparent_field_id, $parent_item_index, $parent_nested_field_name, $deep_item_index, $deep_nested_field['name'], $triple_item_index, $triple_item_data, $triple_nested_field_definitions); ?>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <?php if ($triple_nested_max_sets === 0 || $triple_nested_max_sets > 0) : ?>
                                            <?php $this->renderTripleNestedRepeaterItem($grandparent_instance_id, $grandparent_field_id, $parent_item_index, $parent_nested_field_name, $deep_item_index, $deep_nested_field['name'], 0, [], $triple_nested_field_definitions); ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" 
                                        class="ccc-repeater-add button"
                                        data-field-id="<?php echo esc_attr($deep_nested_field['id'] ?? $deep_nested_field['name']); ?>"
                                        data-instance-id="<?php echo esc_attr($grandparent_instance_id); ?>"
                                        data-grandparent-field-id="<?php echo esc_attr($grandparent_field_id); ?>"
                                        data-parent-item-index="<?php echo esc_attr($parent_item_index); ?>"
                                        data-nested-field-name="<?php echo esc_attr($parent_nested_field_name); ?>"
                                        data-deep-item-index="<?php echo esc_attr($deep_item_index); ?>"
                                        data-deep-nested-field-name="<?php echo esc_attr($deep_nested_field['name']); ?>"
                                        data-nesting-level="3">
                                    Add Level 3 Item
                                </button>
                                <input type="hidden" 
                                       class="ccc-nested-field-input ccc-repeater-main-input"
                                       data-nested-field-type="repeater"
                                       value='<?php echo esc_attr(json_encode($triple_nested_repeater_value)); ?>' />
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    // NEW: Render triple nested repeater item
    protected function renderTripleNestedRepeaterItem($great_grandparent_instance_id, $great_grandparent_field_id, $grandparent_item_index, $grandparent_nested_field_name, $parent_item_index, $parent_nested_field_name, $triple_item_index, $triple_item_data, $triple_nested_field_definitions) {
        ?>
        <div class="ccc-repeater-item ccc-triple-nested-item" data-index="<?php echo esc_attr($triple_item_index); ?>" data-nesting-level="3">
            <div class="ccc-repeater-item-header">
                <span class="ccc-drag-handle dashicons dashicons-menu"></span>
                <strong>Level 3 Item #<?php echo esc_html($triple_item_index + 1); ?></strong>
                <div class="ccc-repeater-controls">
                    <button type="button" class="ccc-repeater-remove button">Remove</button>
                </div>
            </div>
            <div class="ccc-repeater-item-fields">
                <?php foreach ($triple_nested_field_definitions as $triple_nested_field) : 
                    $triple_nested_field_value = $triple_item_data[$triple_nested_field['name']] ?? '';
                    $triple_nested_field_config = $triple_nested_field['config'] ?? [];
                ?>
                    <div class="ccc-nested-field" 
                         data-nested-field-name="<?php echo esc_attr($triple_nested_field['name']); ?>" 
                         data-nested-field-type="<?php echo esc_attr($triple_nested_field['type']); ?>">
                        <label class="ccc-nested-field-label"><?php echo esc_html($triple_nested_field['label']); ?></label>
                        <?php if ($triple_nested_field['type'] === 'text') : ?>
                            <input type="text" 
                                   class="ccc-nested-field-input" 
                                   data-nested-field-type="text"
                                   value="<?php echo esc_attr($triple_nested_field_value); ?>" />
                        <?php elseif ($triple_nested_field['type'] === 'textarea') : ?>
                            <textarea class="ccc-nested-field-input" 
                                      data-nested-field-type="textarea"
                                      rows="3"><?php echo esc_textarea($triple_nested_field_value); ?></textarea>
                        <?php elseif ($triple_nested_field['type'] === 'image') : 
                            $triple_nested_image_return_type = $triple_nested_field_config['return_type'] ?? 'url';
                            $image_src = '';
                            $display_remove_btn = 'none';
                            if ($triple_nested_field_value) {
                                if ($triple_nested_image_return_type === 'url') {
                                    $image_src = $triple_nested_field_value;
                                } else {
                                    try {
                                        $imageData = json_decode($triple_nested_field_value, true);
                                        $image_src = $imageData['url'] ?? '';
                                    } catch (\Exception $e) {
                                        $image_src = $triple_nested_field_value;
                                    }
                                }
                                $display_remove_btn = 'inline-block';
                            }
                        ?>
                            <div class="ccc-nested-image-field">
                                <input type="hidden" 
                                       class="ccc-nested-field-input" 
                                       data-nested-field-type="image"
                                       value="<?php echo esc_attr($triple_nested_field_value); ?>"
                                       data-return-type="<?php echo esc_attr($triple_nested_image_return_type); ?>" />
                                <button type="button" class="button ccc-nested-upload-image" data-return-type="<?php echo esc_attr($triple_nested_image_return_type); ?>">Select Image</button>
                                <button type="button" class="button ccc-nested-remove-image" data-return-type="<?php echo esc_attr($triple_nested_image_return_type); ?>" style="display: <?php echo $display_remove_btn; ?>">Remove</button>
                                <div class="ccc-nested-image-preview-container">
                                    <?php if ($image_src) : ?>
                                        <div class="ccc-nested-image-preview">
                                            <img src="<?php echo esc_url($image_src); ?>" alt="Selected image" />
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php elseif ($triple_nested_field['type'] === 'repeater') : ?>
                            <p><em>Maximum nesting level (4) reached for repeater fields</em></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
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

                $value_to_save = wp_unslash($value);

                if ($field_obj->getType() === 'repeater') {
                    $decoded_value = json_decode($value_to_save, true);
                    $sanitized_decoded_value = $this->sanitizeRepeaterData($decoded_value, $field_obj->getConfig());
                    $value_to_save = json_encode($sanitized_decoded_value);
                } elseif ($field_obj->getType() === 'image') {
                    $field_config = json_decode($field_obj->getConfig(), true);
                    $return_type = $field_config['return_type'] ?? 'url';

                    if ($return_type === 'url') {
                        $decoded_value = json_decode($value_to_save, true);
                        if (is_array($decoded_value) && isset($decoded_value['url'])) {
                            $value_to_save = esc_url_raw($decoded_value['url']);
                        } else {
                            $value_to_save = esc_url_raw($value_to_save);
                        }
                    } else {
                        $decoded_value = json_decode($value_to_save, true);
                        $value_to_save = json_encode($decoded_value);
                    }
                } else {
                    $value_to_save = wp_kses_post($value_to_save);
                }
                
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

    private function sanitizeRepeaterData($data, $config_json) {
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
                    if ($field_type === 'image') {
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
                    } elseif ($field_type === 'repeater') {
                        $decoded_value = is_array($value) ? $value : json_decode($value, true);
                        $sanitized_item[$field_name] = $this->sanitizeRepeaterData($decoded_value, json_encode($nested_field_config));
                    } else {
                        $sanitized_item[$field_name] = wp_kses_post($value);
                    }
                }
            }
            $sanitized_data[] = $sanitized_item;
        }
        return $sanitized_data;
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
}