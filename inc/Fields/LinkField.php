<?php

namespace CCC\Fields;

class LinkField extends BaseField {
    
    protected $config;
    
    public function __construct($label, $name, $component_id, $required = false, $placeholder = '', $config = '') {
        parent::__construct($label, $name, $component_id, $required, $placeholder);
        $this->config = $config;
    }
    
    public function render($post_id, $instance_id, $value = '') {
        // Default field config - this will be overridden by React component
        $field_config = [
            'link_types' => ['internal', 'external'],
            'default_type' => 'internal',
            'post_types' => ['post', 'page'],
            'show_target' => true,
            'show_title' => true
        ];
        
        // Parse current value
        $parsed_value = [
            'type' => 'internal',
            'url' => '',
            'post_id' => '',
            'title' => '',
            'target' => '_self'
        ];
        
        if (!empty($value)) {
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if ($decoded) {
                    $parsed_value = array_merge($parsed_value, $decoded);
                } else {
                    // Legacy support - treat as URL
                    $parsed_value['url'] = $value;
                    $parsed_value['type'] = 'external';
                }
            } elseif (is_array($value)) {
                $parsed_value = array_merge($parsed_value, $value);
            }
        }
        
        $field_id = "ccc_field_{$this->name}_{$instance_id}";
        $field_name = "ccc_field_values[{$this->component_id}][{$instance_id}][{$this->name}]";
        $unique_id = 'ccc_link_' . $this->component_id . '_' . $instance_id;
        
        ob_start();
        ?>
        <div class="ccc-field ccc-link-field">
            <label class="ccc-field-label">
                <?php echo esc_html($this->label); ?>
                <?php if ($this->required): ?>
                    <span class="required">*</span>
                <?php endif; ?>
            </label>
            
            <!-- Hidden input to store the final value -->
            <input type="hidden" 
                   name="<?php echo esc_attr($field_name); ?>" 
                   value="<?php echo esc_attr(json_encode($parsed_value)); ?>" 
                   id="<?php echo esc_attr($unique_id); ?>_hidden" />
            
            <!-- Container for React component -->
            <div id="<?php echo esc_attr($unique_id); ?>" 
                 data-field-name="<?php echo esc_attr($this->name); ?>"
                 data-field-config="<?php echo esc_attr(json_encode($field_config)); ?>"
                 data-field-value="<?php echo esc_attr(json_encode($parsed_value)); ?>"
                 data-field-required="<?php echo $this->required ? 'true' : 'false'; ?>">
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function sanitize($value) {
        if (empty($value)) {
            return '';
        }
        
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!$decoded) {
                return '';
            }
            $value = $decoded;
        }
        
        if (!is_array($value)) {
            return '';
        }
        
        $sanitized = [
            'type' => sanitize_text_field($value['type'] ?? 'external'),
            'url' => esc_url_raw($value['url'] ?? ''),
            'post_id' => intval($value['post_id'] ?? 0),
            'title' => sanitize_text_field($value['title'] ?? ''),
            'target' => sanitize_text_field($value['target'] ?? '_self')
        ];
        
        // Validate target values
        if (!in_array($sanitized['target'], ['_self', '_blank', '_parent', '_top'])) {
            $sanitized['target'] = '_self';
        }
        
        // Validate type
        if (!in_array($sanitized['type'], ['internal', 'external'])) {
            $sanitized['type'] = 'external';
        }
        
        return json_encode($sanitized);
    }
    
    public function validate($value, $field) {
        if ($field->getRequired() && empty($value)) {
            return 'This field is required.';
        }
        
        if (empty($value)) {
            return true;
        }
        
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!$decoded) {
                return 'Invalid link data format.';
            }
            $value = $decoded;
        }
        
        if (!is_array($value)) {
            return 'Invalid link data.';
        }
        
        $type = $value['type'] ?? '';
        
        if ($type === 'internal') {
            $post_id = intval($value['post_id'] ?? 0);
            if ($post_id <= 0) {
                return 'Please select a page or post for internal link.';
            }
            
            // Verify post exists
            $post = get_post($post_id);
            if (!$post) {
                return 'Selected post/page no longer exists.';
            }
        } elseif ($type === 'external') {
            $url = $value['url'] ?? '';
            if (empty($url)) {
                return 'Please enter a URL for external link.';
            }
            
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return 'Please enter a valid URL.';
            }
        } else {
            return 'Invalid link type.';
        }
        
        return true;
    }
    
    public function getDisplayValue($value) {
        if (empty($value)) {
            return '';
        }
        
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!$decoded) {
                return $value; // Legacy support
            }
            $value = $decoded;
        }
        
        if (!is_array($value)) {
            return '';
        }
        
        $type = $value['type'] ?? 'external';
        $title = $value['title'] ?? '';
        
        if ($type === 'internal') {
            $post_id = intval($value['post_id'] ?? 0);
            if ($post_id > 0) {
                $post = get_post($post_id);
                if ($post) {
                    $url = get_permalink($post_id);
                    $display_title = !empty($title) ? $title : $post->post_title;
                    return '<a href="' . esc_url($url) . '" target="' . esc_attr($value['target'] ?? '_self') . '">' . esc_html($display_title) . '</a>';
                }
            }
        } elseif ($type === 'external') {
            $url = $value['url'] ?? '';
            if (!empty($url)) {
                $display_title = !empty($title) ? $title : $url;
                return '<a href="' . esc_url($url) . '" target="' . esc_attr($value['target'] ?? '_self') . '">' . esc_html($display_title) . '</a>';
            }
        }
        
        return '';
    }
    
    public function save() {
        // Implementation for saving the field - this is handled by the Field model
        return true;
    }
}