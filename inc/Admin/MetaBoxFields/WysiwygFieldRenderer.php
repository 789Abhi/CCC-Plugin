<?php

namespace CCC\Admin\MetaBoxFields;

defined('ABSPATH') || exit;

class WysiwygFieldRenderer extends BaseFieldRenderer {
    public function render() {
        $config = $this->getFieldConfig();
        $editor_settings = array_merge([
            'textarea_name' => $this->getFieldName(),
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
        
        ob_start();
        ?>
        <div class="ccc-wysiwyg-wrapper">
            <?php wp_editor($this->field_value, $this->getFieldId(), $editor_settings); ?>
        </div>
        <?php
        $content = ob_get_clean();

        return $this->renderFieldWrapper($content, 'ccc-wysiwyg-field') . $this->renderFieldStyles();
    }

    protected function renderFieldStyles() {
        return '
        <style>
            .ccc-field-wysiwyg .ccc-wysiwyg-wrapper {
                border: 2px solid #e1e5e9;
                border-radius: 6px;
                overflow: hidden;
                transition: border-color 0.2s ease;
            }
            
            .ccc-field-wysiwyg .ccc-wysiwyg-wrapper:focus-within {
                border-color: #0073aa;
                box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.1);
            }
            
            .ccc-field-wysiwyg .wp-editor-wrap {
                border: none;
            }
            
            .ccc-field-wysiwyg .wp-editor-tools {
                background: #f6f7f7;
                border-bottom: 1px solid #e1e5e9;
                padding: 8px;
            }
            
            .ccc-field-wysiwyg .wp-media-buttons {
                padding: 8px 12px;
                background: #f9f9f9;
                border-bottom: 1px solid #e1e5e9;
            }
            
            .ccc-field-wysiwyg .wp-media-buttons .button {
                margin-right: 8px;
                padding: 4px 8px;
                font-size: 12px;
                border-radius: 4px;
                border: 1px solid #c3c4c7;
                background: #fff;
                color: #2c3338;
                text-decoration: none;
                transition: all 0.2s ease;
            }
            
            .ccc-field-wysiwyg .wp-media-buttons .button:hover {
                border-color: #0073aa;
                color: #0073aa;
            }
            
            .ccc-field-wysiwyg .mce-toolbar-grp {
                background: #f6f7f7 !important;
                border-bottom: 1px solid #e1e5e9 !important;
            }
            
            .ccc-field-wysiwyg .mce-btn {
                border-radius: 3px !important;
                margin: 1px !important;
            }
            
            .ccc-field-wysiwyg .wp-editor-area {
                border: none !important;
                padding: 12px !important;
                font-size: 14px !important;
                line-height: 1.6 !important;
                min-height: 200px !important;
            }
            
            .ccc-field-wysiwyg .ccc-field-label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: #1d2327;
                font-size: 14px;
            }
            
            .ccc-field-wysiwyg .ccc-required {
                color: #d63638;
                margin-left: 3px;
            }
            
            .ccc-field-wysiwyg .quicktags-toolbar {
                background: #f6f7f7;
                border-bottom: 1px solid #e1e5e9;
                padding: 8px;
            }
            
            .ccc-field-wysiwyg .quicktags-toolbar .ed_button {
                margin: 2px;
                padding: 4px 8px;
                font-size: 11px;
                border-radius: 3px;
                border: 1px solid #c3c4c7;
                background: #fff;
                color: #2c3338;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            .ccc-field-wysiwyg .quicktags-toolbar .ed_button:hover {
                border-color: #0073aa;
                color: #0073aa;
            }
        </style>';
    }
}
