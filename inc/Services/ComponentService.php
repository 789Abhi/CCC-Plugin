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
            . "\n"
            . "// Ensure helper functions are loaded\n"
            . "if (!function_exists('get_ccc_field')) {\n"
            . "    \$global_helpers_file = WP_PLUGIN_DIR . '/custom-craft-component/inc/Helpers/GlobalHelpers.php';\n"
            . "    if (file_exists(\$global_helpers_file)) {\n"
            . "        require_once \$global_helpers_file;\n"
            . "    }\n"
            . "}\n"
            . "\n"
            . "global \$wpdb, \$ccc_current_component, \$ccc_current_post_id, \$ccc_current_instance_id;\n"
            . "\$post_id = \$ccc_current_post_id ?: get_the_ID();\n"
            . "\$component_id = $component_id;\n"
            . "\$instance_id = \$ccc_current_instance_id;\n"
            . "\n"
            . "// Debug: Check what's available for this instance\n"
            . "if (function_exists('ccc_debug_field_values')) {\n"
            . "    ccc_debug_field_values(\$post_id, \$instance_id);\n"
            . "}\n"
            . "\n"
            . "// Get all fields for this component instance\n"
            . "\$component_fields = get_ccc_component_fields(\$component_id, \$post_id, \$instance_id);\n"
            . "\n"
            . "// Example: Get specific field values for this instance\n"
            . "\$title = get_ccc_field('title');\n"
            . "\$description = get_ccc_field('description');\n"
            . "\$content = get_ccc_field('content');\n"
            . "\n"
            . "?>\n"
            . "<div class=\"ccc-$handle_name-component\" data-instance=\"<?php echo esc_attr(\$instance_id); ?>\">\n"
            . "    <h3>$component_name Component (Instance: <?php echo esc_html(\$instance_id); ?>)</h3>\n"
            . "    \n"
            . "    <?php if (!empty(\$title)) : ?>\n"
            . "        <h2 class=\"component-title\"><?php echo esc_html(\$title); ?></h2>\n"
            . "    <?php else : ?>\n"
            . "        <p><em>No title field value found for this instance</em></p>\n"
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
            . "    <!-- Debug: Show all available fields for this instance -->\n"
            . "    <div class=\"ccc-debug\" style=\"background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;\">\n"
            . "        <strong>Debug - Available Fields for Instance <?php echo esc_html(\$instance_id); ?>:</strong><br>\n"
            . "        <?php\n"
            . "        if (!empty(\$component_fields)) {\n"
            . "            foreach (\$component_fields as \$field_name => \$field_value) {\n"
            . "                echo \"Field: <strong>\" . esc_html(\$field_name) . \"</strong> = '\" . esc_html(\$field_value) . \"'<br>\";\n"
            . "            }\n"
            . "        } else {\n"
            . "            echo \"No fields found for component ID: \$component_id, Post ID: \$post_id, Instance: \$instance_id\";\n"
            . "        }\n"
            . "        ?>\n"
            . "    </div>\n"
            . "    \n"
            . "    <?php\n"
            . "    // Display all field values for this component instance\n"
            . "    if (!empty(\$component_fields)) {\n"
            . "        echo '<div class=\"component-fields\">';\n"
            . "        foreach (\$component_fields as \$field_name => \$field_value) {\n"
            . "            if (!empty(\$field_value)) {\n"
            . "                echo '<div class=\"ccc-field-display ccc-field-' . esc_attr(\$field_name) . '\">';\n"
            . "                echo '<strong>' . esc_html(ucfirst(str_replace('_', ' ', \$field_name))) . ':</strong> ';\n"
            . "                echo '<span>' . esc_html(\$field_value) . '</span>';\n"
            . "                echo '</div>';\n"
            . "            }\n"
            . "        }\n"
            . "        echo '</div>';\n"
            . "    }\n"
            . "    ?>\n"
            . "</div>\n";
        
    }
}
