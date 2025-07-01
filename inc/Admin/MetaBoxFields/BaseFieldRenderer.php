<?php

namespace CCC\Admin\MetaBoxFields;

defined('ABSPATH') || exit;

abstract class BaseFieldRenderer {
    protected $field;
    protected $instance_id;
    protected $field_value;
    protected $post_id;

    public function __construct($field, $instance_id, $field_value, $post_id) {
        $this->field = $field;
        $this->instance_id = $instance_id;
        $this->field_value = $field_value;
        $this->post_id = $post_id;
    }

    abstract public function render();

    protected function getFieldName() {
        return "ccc_field_values[{$this->instance_id}][{$this->field->getId()}]";
    }

    protected function getFieldId() {
        return "ccc_field_{$this->instance_id}_{$this->field->getId()}";
    }

    protected function getFieldConfig() {
        $config = $this->field->getConfig();
        return is_string($config) ? json_decode($config, true) : $config;
    }

    protected function renderFieldWrapper($content, $additional_classes = '') {
        $field_type = $this->field->getType();
        $classes = "ccc-field-input ccc-field-{$field_type} {$additional_classes}";
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr(trim($classes)); ?>" data-field-type="<?php echo esc_attr($field_type); ?>">
            <label for="<?php echo esc_attr($this->getFieldId()); ?>" class="ccc-field-label">
                <?php echo esc_html($this->field->getLabel()); ?>
                <?php if ($this->field->getRequired()): ?>
                    <span class="ccc-required">*</span>
                <?php endif; ?>
            </label>
            <?php echo $content; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    protected function renderFieldStyles() {
        return '';
    }
}
