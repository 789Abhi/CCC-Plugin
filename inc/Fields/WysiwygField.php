<?php

namespace CCC\Fields;

defined('ABSPATH') || exit;

class WysiwygField extends BaseField {
    private $editor_settings = [];

    public function __construct($label, $name, $component_id, $required = false, $placeholder = '', $config = []) {
        parent::__construct($label, $name, $component_id, $required, $placeholder);
        
        if (is_string($config)) {
            $config = json_decode($config, true);
        }
        
        $this->editor_settings = array_merge([
            'textarea_rows' => 10,
            'media_buttons' => true,
            'teeny' => false,
            'dfw' => false,
            'tinymce' => array(
                'resize' => false,
                'wp_autoresize_on' => false,
                'toolbar1' => 'bold,italic,underline,strikethrough,|,bullist,numlist,blockquote,|,link,unlink,|,spellchecker,fullscreen,wp_adv',
                'toolbar2' => 'formatselect,|,pastetext,pasteword,removeformat,|,charmap,|,outdent,indent,|,undo,redo',
            ),
            'quicktags' => true,
        ], $config['editor_settings'] ?? []);
    }

    public function render($post_id, $instance_id, $value = '') {
        $field_id = "ccc_field_{$this->name}_{$instance_id}";
        $field_name = "ccc_field_values[{$this->component_id}][{$instance_id}][{$this->name}]";
        
        $editor_settings = array_merge($this->editor_settings, [
            'textarea_name' => $field_name,
        ]);
        
        ob_start();
        ?>
        <div class="ccc-field ccc-field-wysiwyg">
            <label class="ccc-field-label">
                <?php echo esc_html($this->label); ?>
                <?php if ($this->required): ?>
                    <span class="ccc-required">*</span>
                <?php endif; ?>
            </label>
            <div class="ccc-wysiwyg-wrapper">
                <?php wp_editor($value, $field_id, $editor_settings); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function save() {
        // Implementation for saving the field
        return true;
    }

    public function getEditorSettings() {
        return $this->editor_settings;
    }

    public function setEditorSettings($settings) {
        $this->editor_settings = $settings;
    }
}
