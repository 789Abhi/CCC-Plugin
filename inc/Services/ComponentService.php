<?php
namespace CCC\Services;

use CCC\Models\Component;
use CCC\Models\Field;

defined('ABSPATH') || exit;

class ComponentService {
    public function createComponent($name, $handle) {
        if (Component::handleExists($handle)) {
            throw new \Exception('Handle already exists. Please choose a different one.');
        }

        $component = new Component([
            'name' => sanitize_text_field($name),
            'handle_name' => sanitize_title($handle)
        ]);

        if (!$component->save()) {
            throw new \Exception('Failed to create component');
        }

        $this->createComponentTemplate($component);
        
        return $component;
    }

    public function getComponentsWithFields($post_id = 0) {
        $components = Component::all();
        $result = [];

        foreach ($components as $component) {
            $fields = Field::findByComponent($component->getId());
            $component_data = [
                'id' => $component->getId(),
                'name' => $component->getName(),
                'handle_name' => $component->getHandleName(),
                'fields' => []
            ];

            foreach ($fields as $field) {
                $field_data = [
                    'id' => $field->getId(),
                    'label' => $field->getLabel(),
                    'name' => $field->getName(),
                    'type' => $field->getType(),
                    'value' => $post_id ? $field->getValue($post_id) : ''
                ];
                $component_data['fields'][] = $field_data;
            }

            $result[] = $component_data;
        }

        return $result;
    }

    private function createComponentTemplate(Component $component) {
        $theme_dir = get_stylesheet_directory();
        $templates_dir = $theme_dir . '/ccc-templates';
        
        if (!file_exists($templates_dir)) {
            wp_mkdir_p($templates_dir);
        }

        $template_file = $templates_dir . '/' . $component->getHandleName() . '.php';
        $template_content = $this->generateTemplateContent($component);

        if (!file_put_contents($template_file, $template_content)) {
            error_log("Failed to write component template: $template_file");
            throw new \Exception('Failed to create component template file');
        }
    }

    private function generateTemplateContent(Component $component) {
        global $wpdb;
        $component_id = $component->getId();
        $component_name = $component->getName();
        
        return "<?php\n"
            . "/* Template for component: $component_name */\n"
            . "global \$wpdb;\n"
            . "\$post_id = get_the_ID();\n"
            . "\$component_id = $component_id;\n"
            . "\$fields = \$wpdb->get_results(\$wpdb->prepare(\n"
            . "    \"SELECT id, label, name, type FROM {$wpdb->prefix}cc_fields WHERE component_id = %d ORDER BY field_order, created_at\",\n"
            . "    \$component_id\n"
            . "));\n"
            . "if (\$fields) {\n"
            . "    echo '<h3>' . esc_html('$component_name') . '</h3>';\n"
            . "    foreach (\$fields as \$field) {\n"
            . "        \$value = \$wpdb->get_var(\$wpdb->prepare(\n"
            . "            \"SELECT value FROM {$wpdb->prefix}cc_field_values WHERE field_id = %d AND post_id = %d\",\n"
            . "            \$field->id, \$post_id\n"
            . "        ));\n"
            . "        \$field_id = \$field->id;\n"
            . "        \$field_name = \$field->name;\n"
            . "        \$field_label = \$field->label;\n"
            . "        \$field_type = \$field->type;\n"
            . "?>\n"
            . "<div class='ccc-field ccc-field-<?php echo esc_attr(\$field_name); ?>'>\n"
            . "    <label for='ccc_field_<?php echo esc_attr(\$field_id); ?>'><?php echo esc_html(\$field_label); ?></label>\n"
            . "    <?php if (\$field_type === 'text') { ?>\n"
            . "        <input type='text' id='ccc_field_<?php echo esc_attr(\$field_id); ?>' name='ccc_field_values[<?php echo esc_attr(\$field_id); ?>]' value='<?php echo esc_attr(\$value ?: ''); ?>' class='ccc-input' />\n"
            . "    <?php } elseif (\$field_type === 'text-area') { ?>\n"
            . "        <textarea id='ccc_field_<?php echo esc_attr(\$field_id); ?>' name='ccc_field_values[<?php echo esc_attr(\$field_id); ?>]' class='ccc-textarea' rows='5'><?php echo esc_textarea(\$value ?: ''); ?></textarea>\n"
            . "    <?php } ?>\n"
            . "</div>\n"
            . "<?php }\n"
            . "} else {\n"
            . "    echo '<p>No fields defined for this component.</p>';\n"
            . "}\n"
            . "?>\n";
    }
}
