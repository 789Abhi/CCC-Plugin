<?php

namespace CCC\Admin\MetaBoxFields;

defined('ABSPATH') || exit;

class RepeaterFieldRenderer extends BaseFieldRenderer {
    public function render() {
        $config = $this->getFieldConfig();
        $repeater_value = $this->field_value ? json_decode($this->field_value, true) : [];
        $max_sets = isset($config['max_sets']) ? intval($config['max_sets']) : 0;
        $nested_field_definitions = isset($config['nested_fields']) ? $config['nested_fields'] : [];
        
        if (!is_array($repeater_value)) {
            $repeater_value = [];
        }
        
        // Extract data and state from repeater value
        $repeater_data = [];
        $repeater_state = [];
        
        if (isset($repeater_value['data']) && isset($repeater_value['state'])) {
            $repeater_data = $repeater_value['data'];
            $repeater_state = $repeater_value['state'];
        } else {
            // Legacy format - treat as data only
            $repeater_data = $repeater_value;
            $repeater_state = [];
        }
        
        ob_start();
        ?>
        <div class="ccc-repeater-container" 
             data-field-id="<?php echo esc_attr($this->field->getId()); ?>"
             data-instance-id="<?php echo esc_attr($this->instance_id); ?>"
             data-max-sets="<?php echo esc_attr($max_sets); ?>"
             data-nested-field-definitions='<?php echo esc_attr(json_encode($nested_field_definitions)); ?>'>
            
            <div class="ccc-repeater-header">
                <div class="ccc-repeater-info">
                    <span class="ccc-repeater-count"><?php echo count($repeater_data); ?> item(s)</span>
                    <?php if ($max_sets > 0): ?>
                        <span class="ccc-repeater-limit">Max: <?php echo $max_sets; ?></span>
                    <?php endif; ?>
                </div>
                <button type="button" 
                        class="ccc-repeater-add button button-primary"
                        data-field-id="<?php echo esc_attr($this->field->getId()); ?>"
                        data-instance-id="<?php echo esc_attr($this->instance_id); ?>">
                    <span class="dashicons dashicons-plus-alt"></span>
                    Add Item
                </button>
            </div>
            
            <div class="ccc-repeater-items">
                <?php 
                if (empty($repeater_data)) {
                    // Always show one empty item if none exist
                    $this->renderRepeaterItem(0, [], $nested_field_definitions, false);
                } else {
                    foreach ($repeater_data as $item_index => $item_data) {
                        $is_expanded = false;
                        if (isset($repeater_state[$item_index])) {
                            $state_value = $repeater_state[$item_index];
                            // Handle both boolean and string values
                            if (is_string($state_value)) {
                                $is_expanded = $state_value === 'true' || $state_value === '1';
                            } else {
                                $is_expanded = (bool) $state_value;
                            }
                        }
                        $this->renderRepeaterItem($item_index, $item_data, $nested_field_definitions, $is_expanded);
                    }
                }
                ?>
            </div>
            
            <input type="hidden" 
                   class="ccc-repeater-main-input"
                   id="<?php echo esc_attr($this->getFieldId()); ?>"
                   name="<?php echo esc_attr($this->getFieldName()); ?>"
                   value="<?php echo esc_attr($this->field_value ?: '{"data":[],"state":{}}'); ?>" />
        </div>
        <script>
        jQuery(document).ready(function($) {
            // Use a single shared media frame for all image fields (including nested)
            var cccMediaFrame = null;

            function openCCCFrame($field, $input, returnType, $btn) {
                if (!cccMediaFrame) {
                    cccMediaFrame = wp.media({
                        title: 'Select or Upload an Image',
                        button: { text: 'Use this image' },
                        multiple: false,
                        library: { type: 'image' }
                    });
                }
                // Remove all previous handlers before binding new ones
                cccMediaFrame.off('select');
                cccMediaFrame.off('open');

                cccMediaFrame.on('select', function() {
                    var attachment = cccMediaFrame.state().get('selection').first().toJSON();
                    var imageUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                    if ($btn && $btn.hasClass('ccc-upload-image-btn')) {
                        var previewHtml = '<div class="ccc-image-preview">' +
                            '<img src="' + imageUrl + '" alt="Selected image" style="max-width: 300px; height: auto; display: block; margin: 0 auto;" />' +
                            '<div class="ccc-image-overlay">' +
                                '<button type="button" class="ccc-change-image-btn" data-field-id="' + $btn.data('field-id') + '" data-instance-id="' + $btn.data('instance-id') + '" data-return-type="' + returnType + '"><span class="dashicons dashicons-edit"></span>Change Image</button>' +
                                '<button type="button" class="ccc-remove-image-btn" data-field-id="' + $btn.data('field-id') + '" data-instance-id="' + $btn.data('instance-id') + '" data-return-type="' + returnType + '"><span class="dashicons dashicons-trash"></span>Remove</button>' +
                            '</div>' +
                        '</div>';
                        $field.find('.ccc-image-upload-area').html(previewHtml).removeClass('no-image').addClass('has-image');
                    } else {
                        $field.find('img').attr('src', imageUrl);
                    }
                    if (returnType === 'url') {
                        $input.val(imageUrl);
                    } else {
                        var imageData = {
                            id: attachment.id,
                            url: imageUrl,
                            alt: attachment.alt,
                            title: attachment.title,
                            caption: attachment.caption,
                            description: attachment.description
                        };
                        $input.val(JSON.stringify(imageData));
                    }
                    $field.find('.ccc-image-upload-area').removeClass('no-image').addClass('has-image');
                    cccMediaFrame.close();
                    
                    // Serialize repeater data after image selection
                    serializeRepeater($field.closest('.ccc-repeater-container'));
                });

                // Always clear previous selection on open
                cccMediaFrame.on('open', function() {
                    var selection = cccMediaFrame.state().get('selection');
                    selection.reset();
                });

                cccMediaFrame.open();
            }

            // Change Image
            $(document).on('click', '.ccc-change-image-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $field = $btn.closest('.ccc-image-field');
                var $input = $field.find('.ccc-image-field-input');
                var returnType = $btn.data('return-type');
                openCCCFrame($field, $input, returnType, $btn);
            });

            // First time image selection
            $(document).on('click', '.ccc-upload-image-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $field = $btn.closest('.ccc-image-field');
                var $input = $field.find('.ccc-image-field-input');
                var returnType = $btn.data('return-type');
                openCCCFrame($field, $input, returnType, $btn);
            });

            $(document).on('click', '.ccc-remove-image-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $field = $btn.closest('.ccc-image-field');
                var $input = $field.find('.ccc-image-field-input');
                $input.val('');
                // Restore the placeholder HTML
                var placeholderHtml = '<div class="ccc-image-placeholder">' +
                    '<div class="ccc-upload-icon"><span class="dashicons dashicons-cloud-upload"></span></div>' +
                    '<h4>Upload an Image</h4>' +
                    '<p>Click to select an image from your media library</p>' +
                    '<button type="button" class="ccc-upload-image-btn button button-primary" data-field-id="' + $btn.data('field-id') + '" data-instance-id="' + $btn.data('instance-id') + '" data-return-type="' + $btn.data('return-type') + '">Select Image</button>' +
                '</div>';
                $field.find('.ccc-image-upload-area').html(placeholderHtml).removeClass('has-image').addClass('no-image');
                
                // Serialize repeater data after image removal
                serializeRepeater($field.closest('.ccc-repeater-container'));
            });

            // Serialize repeater data function with state persistence
            function serializeRepeater($container) {
                var $items = $container.find('.ccc-repeater-items');
                var $input = $container.find('.ccc-repeater-main-input');
                var repeaterData = [];
                var repeaterState = {};
                
                $items.children('.ccc-repeater-item').each(function(index) {
                    var $item = $(this);
                    var $content = $item.find('.ccc-repeater-item-content');
                    var itemData = {};
                    
                    // Store the expand/collapse state
                    repeaterState[index] = $content.is(':visible');
                    
                    $item.find('.ccc-nested-field').each(function() {
                        var $field = $(this);
                        var fieldName = $field.data('nested-field-name');
                        var fieldType = $field.data('nested-field-type');
                        var $inputField = $field.find('input, select, textarea').first();
                        var value;
                        
                        if ($inputField.is(':checkbox')) {
                            value = [];
                            $field.find('input[type="checkbox"]:checked').each(function() {
                                value.push($(this).val());
                            });
                        } else if ($inputField.is(':radio')) {
                            value = $field.find('input[type="radio"]:checked').val() || '';
                        } else {
                            value = $inputField.val();
                        }
                        
                        itemData[fieldName] = value;
                    });
                    
                    repeaterData.push(itemData);
                });
                
                // Create the new format with data and state
                var finalData = {
                    data: repeaterData,
                    state: repeaterState
                };
                
                console.log('CCC Repeater: Serializing data for container', $container.data('field-id'), 'State:', repeaterState);
                $input.val(JSON.stringify(finalData));
            }

            // Serialize on nested field changes
            $(document).on('change', '.ccc-nested-field-input', function() {
                serializeRepeater($(this).closest('.ccc-repeater-container'));
            });

            // Serialize on textarea input
            $(document).on('input', '.ccc-nested-field-input', function() {
                serializeRepeater($(this).closest('.ccc-repeater-container'));
            });

            // Serialize on checkbox/radio changes
            $(document).on('change', '.ccc-nested-field input[type="checkbox"], .ccc-nested-field input[type="radio"]', function() {
                serializeRepeater($(this).closest('.ccc-repeater-container'));
            });

            // Serialize on add/remove repeater items
            $(document).on('click', '.ccc-repeater-add, .ccc-repeater-remove', function() {
                setTimeout(function() {
                    serializeRepeater($(this).closest('.ccc-repeater-container'));
                }.bind(this), 100);
            });

            // Restore expand/collapse state on page load FIRST
            $('.ccc-repeater-container').each(function() {
                var $container = $(this);
                var $input = $container.find('.ccc-repeater-main-input');
                var currentValue = $input.val();
                
                try {
                    var parsedValue = JSON.parse(currentValue);
                    if (parsedValue && parsedValue.state) {
                        console.log('CCC Repeater: Restoring state for container', $container.data('field-id'), 'State:', parsedValue.state);
                        // Restore state for each item
                        $container.find('.ccc-repeater-item').each(function(index) {
                            var $item = $(this);
                            var $content = $item.find('.ccc-repeater-item-content');
                            var $icon = $item.find('.ccc-repeater-toggle .dashicons');
                            
                            // Handle both boolean and string values for state
                            var isExpanded = parsedValue.state[index];
                            if (typeof isExpanded === 'string') {
                                isExpanded = isExpanded === 'true' || isExpanded === '1';
                            } else {
                                isExpanded = Boolean(isExpanded);
                            }
                            
                            if (isExpanded) {
                                // Item should be expanded
                                $content.show();
                                $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                            } else {
                                // Item should be collapsed
                                $content.hide();
                                $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                            }
                        });
                    } else {
                        // No state found - assume legacy format, all items collapsed
                        $container.find('.ccc-repeater-item').each(function() {
                            var $item = $(this);
                            var $content = $item.find('.ccc-repeater-item-content');
                            var $icon = $item.find('.ccc-repeater-toggle .dashicons');
                            $content.hide();
                            $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                        });
                    }
                } catch (e) {
                    // If parsing fails, assume legacy format - all items collapsed by default
                    console.log('Legacy repeater format detected, using default collapsed state');
                    // Ensure all items are collapsed for legacy data
                    $container.find('.ccc-repeater-item').each(function() {
                        var $item = $(this);
                        var $content = $item.find('.ccc-repeater-item-content');
                        var $icon = $item.find('.ccc-repeater-toggle .dashicons');
                        $content.hide();
                        $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                    });
                }
            });

            // Initial serialization on page load AFTER state restoration
            $('.ccc-repeater-container').each(function() {
                serializeRepeater($(this));
            });

            // Initialize drag and drop functionality
            function initializeSortable($container) {
                $container.find('.ccc-repeater-items').sortable({
                    handle: '.ccc-repeater-item-header',
                    axis: 'y',
                    opacity: 0.95,
                    tolerance: 'pointer',
                    distance: 3,
                    delay: 150,
                    cursor: 'move',
                    cursorAt: { top: 10, left: 10 },
                    placeholder: '<div class="ccc-repeater-item ccc-repeater-placeholder" style="height: 60px; background: #f0f6fc; border: 2px dashed #0073aa; margin: 8px 0; display: flex; align-items: center; justify-content: center; color: #0073aa; font-style: italic;">Drop here to reorder</div>',
                    helper: function(e, item) {
                        // Create a helper element that shows what's being dragged
                        var $helper = item.clone();
                        $helper.css({
                            'width': item.width(),
                            'background': '#fff',
                            'box-shadow': '0 8px 25px rgba(0,0,0,0.2)',
                            'border': '2px solid #0073aa',
                            'border-radius': '8px',
                            'opacity': '0.95',
                            'transform': 'rotate(1deg)',
                            'z-index': '9999'
                        });
                        return $helper;
                    },
                    start: function(e, ui) {
                        ui.item.addClass('ccc-dragging');
                        ui.placeholder.height(ui.item.height());
                        
                        // Add dragging class to body for global cursor
                        $('body').addClass('ccc-dragging-active');
                    },
                    stop: function(e, ui) {
                        ui.item.removeClass('ccc-dragging');
                        $('body').removeClass('ccc-dragging-active');
                        
                        // Reindex items after drag
                        var $container = ui.item.closest('.ccc-repeater-container');
                        $container.find('.ccc-repeater-item').each(function(index) {
                            $(this).attr('data-index', index);
                            $(this).find('.ccc-repeater-item-title strong').text('Item #' + (index + 1));
                        });
                        
                        // Update item count
                        updateItemCount($container);
                        
                        // Serialize data after reordering
                        setTimeout(function() {
                            serializeRepeater($container);
                        }, 100);
                    }
                });
            }

            // Initialize sortable for existing containers
            $('.ccc-repeater-container').each(function() {
                initializeSortable($(this));
            });

            // Re-initialize sortable after adding new items
            $(document).on('click', '.ccc-repeater-add', function(e) {
                e.preventDefault();
                var $container = $(this).closest('.ccc-repeater-container');
                var $items = $container.find('.ccc-repeater-items');
                var maxSets = parseInt($container.data('max-sets')) || 0;
                var currentCount = $items.children('.ccc-repeater-item').length;
                
                // Check if we've reached the maximum limit
                if (maxSets > 0 && currentCount >= maxSets) {
                    alert('Maximum limit of ' + maxSets + ' items reached. You cannot add more items.');
                    return;
                }
                
                // Get nested field definitions
                var nestedFieldDefinitions = $container.data('nested-field-definitions');
                if (!nestedFieldDefinitions) {
                    console.error('Nested field definitions not found');
                    return;
                }
                
                // Create new item HTML
                var newItemHtml = createRepeaterItemHtml(currentCount, nestedFieldDefinitions);
                $items.append(newItemHtml);
                
                // Re-initialize sortable for the new item
                initializeSortable($container);
                
                // Update item count
                updateItemCount($container);
                
                // Serialize data (new items start collapsed by default)
                setTimeout(function() {
                    serializeRepeater($container);
                }, 100);
            });

            // Remove repeater item
            $(document).on('click', '.ccc-repeater-remove', function(e) {
                e.preventDefault();
                var $item = $(this).closest('.ccc-repeater-item');
                var $container = $item.closest('.ccc-repeater-container');
                
                // Confirm deletion
                if (!confirm('Are you sure you want to remove this item?')) {
                    return;
                }
                
                $item.remove();
                
                // Reindex remaining items
                $container.find('.ccc-repeater-item').each(function(index) {
                    $(this).attr('data-index', index);
                    $(this).find('.ccc-repeater-item-title strong').text('Item #' + (index + 1));
                });
                
                // Update item count
                updateItemCount($container);
                
                // Serialize data
                setTimeout(function() {
                    serializeRepeater($container);
                }, 100);
            });

            // Toggle repeater item (expand/collapse) with state persistence
            $(document).on('click', '.ccc-repeater-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleRepeaterItem($(this).closest('.ccc-repeater-item'));
            });

            // Toggle when clicking on header (but not on controls)
            $(document).on('click', '.ccc-repeater-item-header', function(e) {
                // Don't toggle if clicking on controls (remove button, toggle button, or drag handle)
                if ($(e.target).closest('.ccc-repeater-item-controls, .ccc-drag-handle').length > 0) {
                    return;
                }
                
                e.preventDefault();
                toggleRepeaterItem($(this).closest('.ccc-repeater-item'));
            });

            // Function to toggle repeater item with state persistence
            function toggleRepeaterItem($item) {
                var $content = $item.find('.ccc-repeater-item-content');
                var $icon = $item.find('.ccc-repeater-toggle .dashicons');
                
                if ($content.is(':visible')) {
                    $content.slideUp(200);
                    $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                } else {
                    $content.slideDown(200);
                    $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                }
                
                // Serialize to save the state change
                setTimeout(function() {
                    serializeRepeater($item.closest('.ccc-repeater-container'));
                }, 250); // Wait for animation to complete
            }

            // Function to create repeater item HTML
            function createRepeaterItemHtml(itemIndex, nestedFieldDefinitions) {
                var html = '<div class="ccc-repeater-item" data-index="' + itemIndex + '">';
                html += '<div class="ccc-repeater-item-header">';
                html += '<div class="ccc-repeater-item-title">';
                html += '<span class="ccc-drag-handle dashicons dashicons-menu"></span>';
                html += '<strong>Item #' + (itemIndex + 1) + '</strong>';
                html += '</div>';
                html += '<div class="ccc-repeater-item-controls">';
                html += '<button type="button" class="ccc-repeater-toggle" title="Toggle">';
                html += '<span class="dashicons dashicons-arrow-down-alt2"></span>';
                html += '</button>';
                html += '<button type="button" class="ccc-repeater-remove" title="Remove">';
                html += '<span class="dashicons dashicons-trash"></span>';
                html += '</button>';
                html += '</div>';
                html += '</div>';
                html += '<div class="ccc-repeater-item-content" style="display: none;">';
                html += '<div class="ccc-repeater-item-fields">';
                
                // Add nested fields
                nestedFieldDefinitions.forEach(function(fieldDef) {
                    html += '<div class="ccc-nested-field" data-nested-field-name="' + fieldDef.name + '" data-nested-field-type="' + fieldDef.type + '">';
                    html += '<label class="ccc-nested-field-label">' + fieldDef.label + '</label>';
                    html += createNestedFieldHtml(fieldDef, '');
                    html += '</div>';
                });
                
                html += '</div>';
                html += '</div>';
                html += '</div>';
                
                return html;
            }

            // Function to create nested field HTML
            function createNestedFieldHtml(fieldDef, value) {
                var html = '';
                var fieldType = fieldDef.type;
                var fieldConfig = fieldDef.config || {};
                
                switch (fieldType) {
                    case 'text':
                        html = '<input type="text" class="ccc-nested-field-input" data-nested-field-type="text" value="' + (value || '') + '" />';
                        break;
                    case 'textarea':
                        html = '<textarea class="ccc-nested-field-input" data-nested-field-type="textarea" rows="3">' + (value || '') + '</textarea>';
                        break;
                    case 'color':
                        html = '<input type="text" class="ccc-nested-field-input ccc-color-picker" data-nested-field-type="color" value="' + (value || '') + '" />';
                        break;
                    case 'select':
                        html = createSelectFieldHtml(fieldConfig, value);
                        break;
                    case 'checkbox':
                        html = createCheckboxFieldHtml(fieldConfig, value);
                        break;
                    case 'radio':
                        html = createRadioFieldHtml(fieldConfig, value);
                        break;
                    case 'wysiwyg':
                        html = '<textarea class="ccc-nested-field-input ccc-nested-wysiwyg" data-nested-field-type="wysiwyg" rows="5">' + (value || '') + '</textarea>';
                        break;
                    case 'image':
                        html = createImageFieldHtml(fieldConfig, value);
                        break;
                    default:
                        html = '<input type="text" class="ccc-nested-field-input" data-nested-field-type="text" value="' + (value || '') + '" />';
                        break;
                }
                
                return html;
            }

            // Helper functions for creating specific field types
            function createSelectFieldHtml(fieldConfig, value) {
                var options = fieldConfig.options || {};
                var multiple = fieldConfig.multiple || false;
                var html = '<select class="ccc-nested-field-input" data-nested-field-type="select"' + (multiple ? ' multiple' : '') + '>';
                
                if (!multiple) {
                    html += '<option value="">— Select —</option>';
                }
                
                for (var optionValue in options) {
                    var selected = (value == optionValue) ? 'selected' : '';
                    html += '<option value="' + optionValue + '" ' + selected + '>' + options[optionValue] + '</option>';
                }
                
                html += '</select>';
                return html;
            }

            function createCheckboxFieldHtml(fieldConfig, value) {
                var options = fieldConfig.options || {};
                var selectedValues = Array.isArray(value) ? value : (value ? value.split(',') : []);
                var html = '<div class="ccc-nested-checkbox-options">';
                
                for (var optionValue in options) {
                    var checked = selectedValues.includes(optionValue) ? 'checked' : '';
                    html += '<label><input type="checkbox" class="ccc-nested-field-input" data-nested-field-type="checkbox" value="' + optionValue + '" ' + checked + '> ' + options[optionValue] + '</label>';
                }
                
                html += '</div>';
                return html;
            }

            function createRadioFieldHtml(fieldConfig, value) {
                var options = fieldConfig.options || {};
                var html = '<div class="ccc-nested-radio-options">';
                
                for (var optionValue in options) {
                    var checked = (value == optionValue) ? 'checked' : '';
                    html += '<label><input type="radio" class="ccc-nested-field-input" data-nested-field-type="radio" value="' + optionValue + '" ' + checked + '> ' + options[optionValue] + '</label>';
                }
                
                html += '</div>';
                return html;
            }

            function createImageFieldHtml(fieldConfig, value) {
                var returnType = fieldConfig.return_type || 'url';
                var fieldId = 'nestedimg_' + Math.random().toString(36).substr(2, 9);
                var html = '<div class="ccc-image-field ccc-nested-image-field">';
                html += '<input type="hidden" class="ccc-image-field-input ccc-nested-field-input" id="' + fieldId + '" data-nested-field-type="image" data-return-type="' + returnType + '" value="' + (value || '') + '" />';
                html += '<div class="ccc-image-upload-area no-image">';
                html += '<div class="ccc-image-placeholder">';
                html += '<div class="ccc-upload-icon"><span class="dashicons dashicons-cloud-upload"></span></div>';
                html += '<h4>Upload an Image</h4>';
                html += '<p>Click to select an image from your media library</p>';
                html += '<button type="button" class="ccc-upload-image-btn button button-primary" data-field-id="' + fieldId + '" data-instance-id="' + fieldId + '" data-return-type="' + returnType + '">Select Image</button>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
                return html;
            }

            // Function to update item count display
            function updateItemCount($container) {
                var currentCount = $container.find('.ccc-repeater-item').length;
                $container.find('.ccc-repeater-count').text(currentCount + ' item(s)');
            }
        });
        </script>
        <?php
        $content = ob_get_clean();

        return $this->renderFieldWrapper($content) . $this->renderFieldStyles();
    }
    
    protected function renderRepeaterItem($item_index, $item_data, $nested_field_definitions, $is_expanded) {
        $content_style = $is_expanded ? '' : 'style="display: none;"';
        $icon_class = $is_expanded ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2';
        ?>
        <div class="ccc-repeater-item" data-index="<?php echo esc_attr($item_index); ?>">
            <div class="ccc-repeater-item-header">
                <div class="ccc-repeater-item-title">
                    <span class="ccc-drag-handle dashicons dashicons-menu"></span>
                    <strong>Item #<?php echo esc_html($item_index + 1); ?></strong>
                </div>
                <div class="ccc-repeater-item-controls">
                    <button type="button" class="ccc-repeater-toggle" title="Toggle">
                        <span class="dashicons <?php echo esc_attr($icon_class); ?>"></span>
                    </button>
                    <button type="button" class="ccc-repeater-remove" title="Remove">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            </div>
            <div class="ccc-repeater-item-content" <?php echo $content_style; ?>>
                <div class="ccc-repeater-item-fields">
                    <?php foreach ($nested_field_definitions as $nested_field): 
                        $nested_field_value = $item_data[$nested_field['name']] ?? '';
                        // Ensure config is always set
                        if (!isset($nested_field['config']) || !is_array($nested_field['config'])) {
                            $nested_field['config'] = [];
                        }
                        $nested_field_config = $nested_field['config'];
                    ?>
                        <div class="ccc-nested-field" 
                             data-nested-field-name="<?php echo esc_attr($nested_field['name']); ?>" 
                             data-nested-field-type="<?php echo esc_attr($nested_field['type']); ?>">
                            <label class="ccc-nested-field-label"><?php echo esc_html($nested_field['label']); ?></label>
                            <?php $this->renderNestedField($nested_field, $nested_field_value); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    protected function renderNestedField($field_config, $value) {
        $type = $field_config['type'];
        switch ($type) {
            case 'text':
                echo '<input type="text" class="ccc-nested-field-input" data-nested-field-type="text" value="' . esc_attr($value) . '" />';
                break;
            case 'textarea':
                echo '<textarea class="ccc-nested-field-input" data-nested-field-type="textarea" rows="3">' . esc_textarea($value) . '</textarea>';
                break;
            case 'color':
                echo '<input type="text" class="ccc-nested-field-input ccc-color-picker" data-nested-field-type="color" value="' . esc_attr($value) . '" />';
                break;
            case 'select':
                $this->renderNestedSelectField($field_config, $value);
                break;
            case 'checkbox':
                $this->renderNestedCheckboxField($field_config, $value);
                break;
            case 'radio':
                $this->renderNestedRadioField($field_config, $value);
                break;
            case 'wysiwyg':
                echo '<textarea class="ccc-nested-field-input ccc-nested-wysiwyg" data-nested-field-type="wysiwyg" rows="5">' . esc_textarea($value) . '</textarea>';
                break;
            case 'image':
                $return_type = isset($field_config['config']['return_type']) ? $field_config['config']['return_type'] : 'url';
                $required = !empty($field_config['required']) ? 'required' : '';
                $image_src = '';
                $image_id = '';
                if (!empty($value)) {
                    if ($return_type === 'url') {
                        $image_src = $value;
                        $image_id = attachment_url_to_postid($image_src);
                    } else if ($return_type === 'array' && is_string($value)) {
                        $image_data = json_decode($value, true);
                        $image_src = isset($image_data['url']) ? $image_data['url'] : '';
                        $image_id = isset($image_data['id']) ? $image_data['id'] : '';
                    }
                }
                // If we have an image ID, get the medium size URL
                if ($image_id) {
                    $medium = wp_get_attachment_image_src($image_id, 'medium');
                    if ($medium && isset($medium[0])) {
                        $image_src = $medium[0];
                    }
                }
                $field_id = $field_config['name'];
                $instance_id = uniqid('nestedimg_');
                ?>
                <div class="ccc-image-field ccc-nested-image-field">
                    <input type="hidden"
                           class="ccc-image-field-input ccc-nested-field-input"
                           id="<?php echo esc_attr($instance_id); ?>"
                           data-nested-field-type="image"
                           data-return-type="<?php echo esc_attr($return_type); ?>"
                           value="<?php echo esc_attr($value); ?>"
                           <?php echo $required; ?> />
                    <div class="ccc-image-upload-area <?php echo $image_src ? 'has-image' : 'no-image'; ?>">
                        <?php if ($image_src): ?>
                            <div class="ccc-image-preview">
                                <img src="<?php echo esc_url($image_src); ?>" alt="Selected image" style="max-width:300px; height: auto; display: block; margin: 0 auto;" />
                                <div class="ccc-image-overlay">
                                    <button type="button" class="ccc-change-image-btn" data-field-id="<?php echo esc_attr($field_id); ?>" data-instance-id="<?php echo esc_attr($instance_id); ?>" data-return-type="<?php echo esc_attr($return_type); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                        Change Image
                                    </button>
                                    <button type="button" class="ccc-remove-image-btn" data-field-id="<?php echo esc_attr($field_id); ?>" data-instance-id="<?php echo esc_attr($instance_id); ?>" data-return-type="<?php echo esc_attr($return_type); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                        Remove
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="ccc-image-placeholder">
                                <div class="ccc-upload-icon">
                                    <span class="dashicons dashicons-cloud-upload"></span>
                                </div>
                                <h4>Upload an Image</h4>
                                <p>Click to select an image from your media library</p>
                                <button type="button" class="ccc-upload-image-btn button button-primary" data-field-id="<?php echo esc_attr($field_id); ?>" data-instance-id="<?php echo esc_attr($instance_id); ?>" data-return-type="<?php echo esc_attr($return_type); ?>">
                                    Select Image
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                break;
            default:
                echo '<input type="text" class="ccc-nested-field-input" data-nested-field-type="text" value="' . esc_attr($value) . '" />';
                break;
        }
    }
    
    protected function renderNestedSelectField($field_config, $value) {
        $options = $field_config['config']['options'] ?? [];
        $multiple = isset($field_config['config']['multiple']) && $field_config['config']['multiple'];
        
        echo '<select class="ccc-nested-field-input" data-nested-field-type="select"' . ($multiple ? ' multiple' : '') . '>';
        if (!$multiple) {
            echo '<option value="">— Select —</option>';
        }
        foreach ($options as $option_value => $option_label) {
            $selected = ($multiple && is_array($value)) ? 
                (in_array($option_value, $value) ? 'selected' : '') : 
                selected($value, $option_value, false);
            echo '<option value="' . esc_attr($option_value) . '" ' . $selected . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
    }
    
    protected function renderNestedCheckboxField($field_config, $value) {
        $options = $field_config['config']['options'] ?? [];
        $selected_values = is_array($value) ? $value : explode(',', $value);
        
        echo '<div class="ccc-nested-checkbox-options">';
        foreach ($options as $option_value => $option_label) {
            $checked = in_array($option_value, $selected_values) ? 'checked' : '';
            echo '<label><input type="checkbox" class="ccc-nested-field-input" data-nested-field-type="checkbox" value="' . esc_attr($option_value) . '" ' . $checked . '> ' . esc_html($option_label) . '</label>';
        }
        echo '</div>';
    }
    
    protected function renderNestedRadioField($field_config, $value) {
        $options = $field_config['config']['options'] ?? [];
        
        echo '<div class="ccc-nested-radio-options">';
        foreach ($options as $option_value => $option_label) {
            $checked = checked($value, $option_value, false);
            echo '<label><input type="radio" class="ccc-nested-field-input" data-nested-field-type="radio" value="' . esc_attr($option_value) . '" ' . $checked . '> ' . esc_html($option_label) . '</label>';
        }
        echo '</div>';
    }

    protected function renderFieldStyles() {
        return '
        <style>
            .ccc-field-repeater .ccc-repeater-container {
                border: 2px solid #e1e5e9;
                border-radius: 8px;
                background: #fff;
                overflow: hidden;
            }
            
            .ccc-field-repeater .ccc-repeater-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 16px 20px;
                background: #f6f7f7;
                border-bottom: 1px solid #e1e5e9;
            }
            
            .ccc-field-repeater .ccc-repeater-info {
                display: flex;
                align-items: center;
                gap: 12px;
                font-size: 14px;
                color: #646970;
            }
            
            .ccc-field-repeater .ccc-repeater-count {
                font-weight: 600;
                color: #1d2327;
            }
            
            .ccc-field-repeater .ccc-repeater-limit {
                background: #dbeafe;
                color: #1e40af;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 500;
            }
            
            .ccc-field-repeater .ccc-repeater-add {
                display: flex;
                align-items: center;
                gap: 6px;
                padding: 8px 16px;
                font-size: 13px;
                border-radius: 6px;
            }
            
            .ccc-field-repeater .ccc-repeater-add .dashicons {
                width: 16px;
                height: 16px;
                font-size: 16px;
            }
            
            .ccc-field-repeater .ccc-repeater-items {
                min-height: 60px;
            }
            
            .ccc-field-repeater .ccc-repeater-empty {
                padding: 40px 20px;
                text-align: center;
                color: #646970;
                background: #fafafa;
            }
            
            .ccc-field-repeater .ccc-empty-icon {
                font-size: 48px;
                color: #c3c4c7;
                margin-bottom: 16px;
            }
            
            .ccc-field-repeater .ccc-empty-icon .dashicons {
                width: 48px;
                height: 48px;
                font-size: 48px;
            }
            
            .ccc-field-repeater .ccc-repeater-item {
                border-bottom: 1px solid #e1e5e9;
                background: #fff;
                transition: all 0.2s ease;
            }
            
            .ccc-field-repeater .ccc-repeater-item:last-child {
                border-bottom: none;
            }
            
            .ccc-field-repeater .ccc-repeater-item:hover {
                background: #f9f9f9;
            }
            
            .ccc-field-repeater .ccc-repeater-item-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 20px;
                background: #f6f7f7;
                border-bottom: 1px solid #e1e5e9;
                cursor: pointer;
            }
            
            .ccc-field-repeater .ccc-repeater-item-title {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
                font-weight: 600;
                color: #1d2327;
            }
            
            .ccc-field-repeater .ccc-drag-handle {
                cursor: grab;
                transition: all 0.2s ease;
                padding: 4px;
                border-radius: 3px;
            }
            
            .ccc-field-repeater .ccc-drag-handle:hover {
                background: #e1e5e9;
                color: #0073aa;
            }
            
            .ccc-field-repeater .ccc-drag-handle:active {
                cursor: grabbing;
            }
            
            .ccc-field-repeater .ccc-repeater-item-header {
                cursor: pointer;
                transition: background-color 0.2s ease;
            }
            
            .ccc-field-repeater .ccc-repeater-item-header:hover {
                background: #f0f6fc;
            }
            
            .ccc-field-repeater .ccc-repeater-item-header:active {
                background: #e1e5e9;
            }
            
            .ccc-field-repeater .ccc-repeater-item.ccc-dragging {
                opacity: 0.95;
                transform: rotate(1deg);
                box-shadow: 0 8px 25px rgba(0,0,0,0.2);
                z-index: 1000;
                transition: none;
            }
            
            .ccc-field-repeater .ccc-repeater-placeholder {
                background: #f0f6fc !important;
                border: 2px dashed #0073aa !important;
                border-radius: 8px;
                margin: 8px 0;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #0073aa;
                font-style: italic;
                font-size: 14px;
                min-height: 60px;
            }
            
            .ccc-field-repeater .ccc-repeater-items.ui-sortable-helper {
                box-shadow: 0 8px 25px rgba(0,0,0,0.2);
                border: 2px solid #0073aa;
                background: #fff;
                opacity: 0.95;
                border-radius: 8px;
                transform: rotate(1deg);
            }
            
            .ccc-field-repeater .ccc-repeater-items.ui-sortable-placeholder {
                background: #f0f6fc;
                border: 2px dashed #0073aa;
                border-radius: 8px;
                margin: 8px 0;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #0073aa;
                font-style: italic;
                font-size: 14px;
                min-height: 60px;
            }
            
            /* Global cursor styles for dragging */
            body.ccc-dragging-active {
                cursor: grabbing !important;
            }
            
            body.ccc-dragging-active * {
                cursor: grabbing !important;
            }
            
            /* Prevent text selection during drag */
            .ccc-field-repeater .ccc-repeater-item-header {
                user-select: none;
                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
            }
            
            /* Smooth transitions for content */
            .ccc-field-repeater .ccc-repeater-item-content {
                transition: all 0.2s ease;
                padding: 20px;
            }
            
            .ccc-field-repeater .ccc-repeater-item-fields {
                display: grid;
                gap: 16px;
            }
            
            .ccc-field-repeater .ccc-repeater-item-controls {
                display: flex;
                gap: 4px;
            }
            
            .ccc-field-repeater .ccc-repeater-toggle,
            .ccc-field-repeater .ccc-repeater-remove {
                padding: 6px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                transition: all 0.2s ease;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .ccc-field-repeater .ccc-repeater-toggle {
                background: #0073aa;
                color: white;
            }
            
            .ccc-field-repeater .ccc-repeater-toggle:hover {
                background: #005a87;
            }
            
            .ccc-field-repeater .ccc-repeater-remove {
                background: #d63638;
                color: white;
            }
            
            .ccc-field-repeater .ccc-repeater-remove:hover {
                background: #b32d2e;
            }
            
            .ccc-field-repeater .ccc-repeater-toggle .dashicons,
            .ccc-field-repeater .ccc-repeater-remove .dashicons {
                width: 16px;
                height: 16px;
                font-size: 16px;
            }
            
            .ccc-field-repeater .ccc-nested-field {
                display: flex;
                flex-direction: column;
                gap: 6px;
            }
            
            .ccc-field-repeater .ccc-nested-field-label {
                font-weight: 600;
                color: #1d2327;
                font-size: 13px;
            }
            
            .ccc-field-repeater .ccc-nested-field-input {
                padding: 8px 12px;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                font-size: 13px;
                transition: border-color 0.2s ease;
            }
            
            .ccc-field-repeater .ccc-nested-field-input:focus {
                outline: none;
                border-color: #0073aa;
                box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
            }
            
            .ccc-field-repeater .ccc-field-label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: #1d2327;
                font-size: 14px;
            }
            
            .ccc-field-repeater .ccc-required {
                color: #d63638;
                margin-left: 3px;
            }
            
            /* Nested Image Field Styles - Same as main image field */
            .ccc-field-repeater .ccc-nested-image-field {
                position: relative;
            }
            
            .ccc-field-repeater .ccc-nested-image-field .ccc-image-upload-area {
                border: 2px dashed #e1e5e9;
                border-radius: 8px;
                transition: all 0.3s ease;
                position: relative;
                overflow: hidden;
            }
            
            .ccc-field-repeater .ccc-nested-image-field .ccc-image-upload-area.no-image {
                padding: 40px 20px;
                text-align: center;
                background: #fafafa;
            }
            
            .ccc-field-repeater .ccc-nested-image-field .ccc-image-upload-area.no-image:hover {
                border-color: #0073aa;
                background: #f0f6fc;
            }
            
            .ccc-field-repeater .ccc-nested-image-field .ccc-image-upload-area.has-image {
                border: 2px solid #e1e5e9;
                border-radius: 8px;
                overflow: hidden;
            }
            
            .ccc-field-repeater .ccc-nested-image-field .ccc-image-placeholder {
                color: #646970;
            }
            
            .ccc-field-repeater .ccc-nested-image-field .ccc-upload-icon {
                font-size: 48px;
                color: #c3c4c7;
                margin-bottom: 16px;
            }
            
            .ccc-field-repeater .ccc-nested-image-field .ccc-upload-icon .dashicons {
                width: 48px;
                height: 48px;
                font-size: 48px;
            }
            
            .ccc-field-repeater .ccc-nested-image-field .ccc-image-placeholder h4 {
                margin: 0 0 8px 0;
                font-size: 16px;
                font-weight: 600;
                color: #1d2327;
            }
            
            .ccc-field-repeater .ccc-nested-image-field .ccc-image-placeholder p {
                margin: 0 0 20px 0;
                font-size: 14px;
                color: #646970;
            }
            
            .ccc-field-repeater .ccc-nested-image-field .ccc-image-preview {
                position: relative;
                display: inline-block;
            }
            
            .ccc-field-repeater .ccc-nested-image-field .ccc-image-preview img {
                width: 100%;
                height: auto;
                max-height: 300px;
                object-fit: cover;
                display: block;
            }
            
            .ccc-field-repeater .ccc-nested-image-field .ccc-image-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.7);
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            
            .ccc-field-repeater .ccc-nested-image-field .ccc-image-preview:hover .ccc-image-overlay {
                opacity: 1;
            }
            
            .ccc-field-repeater .ccc-nested-image-field .ccc-change-image-btn,
            .ccc-field-repeater .ccc-nested-image-field .ccc-remove-image-btn {
                padding: 8px 16px;
                border: none;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s ease;
                display: flex;
                align-items: center;
                gap: 6px;
            }
            
            .ccc-field-repeater .ccc-nested-image-field .ccc-change-image-btn {
                background: #0073aa;
                color: white;
            }
            
            .ccc-field-repeater .ccc-nested-image-field .ccc-change-image-btn:hover {
                background: #005a87;
            }
            
            .ccc-field-repeater .ccc-nested-image-field .ccc-remove-image-btn {
                background: #d63638;
                color: white;
            }
            
            .ccc-field-repeater .ccc-nested-image-field .ccc-remove-image-btn:hover {
                background: #b32d2e;
            }
            
            .ccc-field-repeater .ccc-nested-image-field .ccc-change-image-btn .dashicons,
            .ccc-field-repeater .ccc-nested-image-field .ccc-remove-image-btn .dashicons {
                width: 16px;
                height: 16px;
                font-size: 16px;
            }
        </style>';
    }
}
