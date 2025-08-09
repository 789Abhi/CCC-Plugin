<?php

namespace CCC\Fields;

defined('ABSPATH') || exit;

class OembedField extends BaseField {
    private $width;
    private $height;
    private $show_title;
    private $show_author;
    private $show_related;

    public function __construct($label, $name, $component_id, $required = false, $placeholder = '', $config = '') {
        parent::__construct($label, $name, $component_id, $required, $placeholder, $config);
        $this->type = 'oembed';
        
        // Parse config
        if ($config) {
            $config_array = is_string($config) ? json_decode($config, true) : $config;
            $this->width = $config_array['width'] ?? '100%';
            $this->height = $config_array['height'] ?? '400px';
            $this->show_title = $config_array['show_title'] ?? true;
            $this->show_author = $config_array['show_author'] ?? false;
            $this->show_related = $config_array['show_related'] ?? false;
        } else {
            $this->width = '100%';
            $this->height = '400px';
            $this->show_title = true;
            $this->show_author = false;
            $this->show_related = false;
        }
    }

    public function render($value = '') {
        $field_id = $this->getId();
        $field_name = "ccc_field_values[$field_id]";
        $field_id_attr = "ccc_field_$field_id";
        
        $config_json = json_encode([
            'width' => $this->width,
            'height' => $this->height,
            'show_title' => $this->show_title,
            'show_author' => $this->show_author,
            'show_related' => $this->show_related
        ]);
        
        ob_start();
        ?>
        <div class="ccc-field ccc-oembed-field" data-config='<?php echo esc_attr($config_json); ?>'>
            <label for="<?php echo esc_attr($field_id_attr); ?>">
                <?php echo esc_html($this->label); ?>
                <?php if ($this->required): ?>
                    <span class="required">*</span>
                <?php endif; ?>
            </label>
            <div class="ccc-oembed-input-container">
                <textarea
                    id="<?php echo esc_attr($field_id_attr); ?>"
                    name="<?php echo esc_attr($field_name); ?>"
                    placeholder="<?php echo esc_attr($this->placeholder ?: 'Paste your iframe code here (e.g., Google Maps, YouTube, Vimeo embed code)'); ?>"
                    class="ccc-oembed-iframe-textarea"
                    rows="4"
                    <?php echo $this->required ? 'required' : ''; ?>
                ><?php echo esc_textarea($value); ?></textarea>
            </div>
            <div class="ccc-oembed-settings">
                <div class="ccc-oembed-setting">
                    <label>Width:</label>
                    <input type="text" class="ccc-oembed-width" value="<?php echo esc_attr($this->width); ?>" placeholder="100%">
                </div>
                <div class="ccc-oembed-setting">
                    <label>Height:</label>
                    <input type="text" class="ccc-oembed-height" value="<?php echo esc_attr($this->height); ?>" placeholder="400px">
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function getConfig() {
        return json_encode([
            'width' => $this->width,
            'height' => $this->height,
            'show_title' => $this->show_title,
            'show_author' => $this->show_author,
            'show_related' => $this->show_related
        ]);
    }

    public function validate($value) {
        if ($this->required && empty($value)) {
            return new \WP_Error('required_field', 'This field is required.');
        }
        
        if (!empty($value)) {
            // Basic iframe validation
            if (!preg_match('/<iframe\s+/i', $value)) {
                return new \WP_Error('invalid_iframe', 'Please enter a valid iframe code.');
            }
            
            if (!preg_match('/src\s*=\s*["\'][^"\']+["\']/i', $value)) {
                return new \WP_Error('invalid_iframe', 'Iframe code must include a valid src attribute.');
            }
        }
        
        return true;
    }

    public function sanitize($value) {
        // Allow iframe tags and common attributes
        $allowed_html = [
            'iframe' => [
                'src' => true,
                'width' => true,
                'height' => true,
                'frameborder' => true,
                'allowfullscreen' => true,
                'loading' => true,
                'referrerpolicy' => true,
                'title' => true,
                'style' => true,
                'class' => true,
                'id' => true
            ]
        ];
        
        return wp_kses($value, $allowed_html);
    }

    // Getters and setters
    public function getWidth() { return $this->width; }
    public function setWidth($width) { $this->width = $width; }
    
    public function getHeight() { return $this->height; }
    public function setHeight($height) { $this->height = $height; }
    
    public function getShowTitle() { return $this->show_title; }
    public function setShowTitle($show_title) { $this->show_title = $show_title; }
    
    public function getShowAuthor() { return $this->show_author; }
    public function setShowAuthor($show_author) { $this->show_author = $show_author; }
    
    public function getShowRelated() { return $this->show_related; }
    public function setShowRelated($show_related) { $this->show_related = $show_related; }
} 