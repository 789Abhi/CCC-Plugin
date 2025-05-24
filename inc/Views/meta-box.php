<?php
defined('ABSPATH') || exit;
?>

<div id="ccc-component-selector" data-post-id="<?php echo esc_attr($post->ID); ?>">
    <?php if (empty($components)) : ?>
        <p>No components available. Please create components in the <a href="<?php echo admin_url('admin.php?page=custom-craft-component'); ?>">Custom Components</a> section.</p>
    <?php else : ?>
        <p>Select components to assign to this page:</p>
        <select id="ccc-component-select" name="ccc_components[]" multiple style="width: 100%; height: 150px;">
            <?php foreach ($components as $component) : ?>
                <option value='<?php echo esc_attr(json_encode(['id' => $component->getId(), 'name' => $component->getName()])); ?>'
                    <?php echo in_array(['id' => $component->getId(), 'name' => $component->getName()], $current_components, true) ? 'selected' : ''; ?>>
                    <?php echo esc_html($component->getName()); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <div id="ccc-field-inputs" style="margin-top: 20px;">
            <?php foreach ($current_components as $comp) : ?>
                <?php
                $comp_id = $comp['id'];
                $fields = \CCC\Models\Field::findByComponent($comp_id);
                if ($fields) : ?>
                    <div class="ccc-component-fields" data-component-id="<?php echo esc_attr($comp_id); ?>">
                        <h4><?php echo esc_html($comp['name']); ?> Fields</h4>
                        <?php foreach ($fields as $field) : ?>
                            <div class="ccc-field-input">
                                <label for="ccc_field_<?php echo esc_attr($field->getId()); ?>">
                                    <?php echo esc_html($field->getLabel()); ?>
                                </label>
                                <?php if ($field->getType() === 'text') : ?>
                                    <input type="text" id="ccc_field_<?php echo esc_attr($field->getId()); ?>"
                                        name="ccc_field_values[<?php echo esc_attr($field->getId()); ?>]"
                                        value="<?php echo esc_attr($field_values[$field->getId()] ?: ''); ?>"
                                        style="width: 100%; margin-bottom: 10px;" />
                                <?php elseif ($field->getType() === 'text-area') : ?>
                                    <textarea id="ccc_field_<?php echo esc_attr($field->getId()); ?>"
                                        name="ccc_field_values[<?php echo esc_attr($field->getId()); ?>]"
                                        rows="5" style="width: 100%; margin-bottom: 10px;"><?php echo esc_textarea($field_values[$field->getId()] ?: ''); ?></textarea>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        
        <p><em>Select components above to add or edit their fields. Save the page to store field values.</em></p>
        
        <script>
            jQuery(document).ready(function ($) {
                var $select = $('#ccc-component-select');
                var $fieldInputs = $('#ccc-field-inputs');
                var postId = $('#ccc-component-selector').data('post-id');

                function updateFields() {
                    var selectedComponents = $select.val() || [];
                    $fieldInputs.empty();
                    selectedComponents.forEach(function (compJson) {
                        var comp = JSON.parse(compJson);
                        $.ajax({
                            url: cccData.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'ccc_get_components',
                                nonce: cccData.nonce,
                                post_id: postId
                            },
                            success: function (response) {
                                if (response.success && response.data.components) {
                                    var component = response.data.components.find(c => c.id == comp.id);
                                    if (component && component.fields.length) {
                                        var $compDiv = $('<div class="ccc-component-fields" data-component-id="' + comp.id + '">');
                                        $compDiv.append('<h4>' + $('<div>').text(comp.name).html() + ' Fields</h4>');
                                        component.fields.forEach(function (field) {
                                            var $fieldDiv = $('<div class="ccc-field-input">');
                                            $fieldDiv.append('<label for="ccc_field_' + field.id + '">' + $('<div>').text(field.label).html() + '</label>');
                                            if (field.type === 'text') {
                                                $fieldDiv.append('<input type="text" id="ccc_field_' + field.id + '" name="ccc_field_values[' + field.id + ']" value="' + (field.value || '') + '" style="width: 100%; margin-bottom: 10px;" />');
                                            } else if (field.type === 'text-area') {
                                                $fieldDiv.append('<textarea id="ccc_field_' + field.id + '" name="ccc_field_values[' + field.id + ']" rows="5" style="width: 100%; margin-bottom: 10px;">' + (field.value || '') + '</textarea>');
                                            }
                                            $compDiv.append($fieldDiv);
                                        });
                                        $fieldInputs.append($compDiv);
                                    }
                                }
                            },
                            error: function () {
                                console.error('Failed to fetch component fields.');
                            }
                        });
                    });
                }

                $select.on('change', updateFields);
                updateFields(); // Initial load
            });
        </script>
    <?php endif; ?>
</div>
