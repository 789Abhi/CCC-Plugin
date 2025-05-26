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
        $handle_name = $component->getHandleName();
        
        return "<?php\n"
            . "/* Template for component: $component_name */\n"
            . "// Helper functions available:\n"
            . "// get_ccc_field('field_name') - Get field value by name for current component\n"
            . "// get_ccc_component_fields(\$component_id) - Get all fields for this component\n"
            . "// get_ccc_post_components() - Get all components for current post\n"
            . "\n"
            . "global \$wpdb, \$ccc_current_component, \$ccc_current_post_id;\n"
            . "\$post_id = \$ccc_current_post_id ?: get_the_ID();\n"
            . "\$component_id = $component_id;\n"
            . "\n"
            . "// Get all fields for this component instance\n"
            . "\$component_fields = get_ccc_component_fields(\$component_id, \$post_id);\n"
            . "\n"
            . "// Example: Get specific field values for this component instance\n"
            . "\$title = get_ccc_field('title');\n"
            . "\$description = get_ccc_field('description');\n"
            . "\$content = get_ccc_field('content');\n"
            . "\n"
            . "?>\n"
            . "<div class=\"ccc-$handle_name-component\">\n"
            . "    <h3>$component_name Component</h3>\n"
            . "    \n"
            . "    <?php if (!empty(\$title)) : ?>\n"
            . "        <h2 class=\"component-title\"><?php echo esc_html(\$title); ?></h2>\n"
            . "    <?php endif; ?>\n"
            . "    \n"
            . "    <?php if (!empty(\$description)) : ?>\n"
            . "        <div class=\"component-description\"><?php echo wp_kses_post(\$description); ?></div>\n"
            . "    <?php endif; ?>\n"
            . "    \n"
            . "    <?php if (!empty(\$content)) : ?>\n"
            . "        <div class=\"component-content\"><?php echo wp_kses_post(\$content); ?></div>\n"
            . "    <?php endif; ?>\n"
            . "    \n"
            . "    <?php\n"
            . "    // Display all field values for this component\n"
            . "    foreach (\$component_fields as \$field_name => \$field_value) {\n"
            . "        if (!empty(\$field_value)) {\n"
            . "            echo '<div class=\"ccc-field-display ccc-field-' . esc_attr(\$field_name) . '\">';\n"
            . "            echo '<strong>' . esc_html(ucfirst(str_replace('_', ' ', \$field_name))) . ':</strong> ';\n"
            . "            echo '<span>' . esc_html(\$field_value) . '</span>';\n"
            . "            echo '</div>';\n"
            . "        }\n"
            . "    }\n"
            . "    ?>\n"
            . "</div>\n"
            . "\n";
        
    }
}
