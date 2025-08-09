<?php

namespace CCC\Fields;

defined('ABSPATH') || exit;

class RelationshipField extends BaseField {
    private $filter_post_types;
    private $filter_post_status;
    private $filter_taxonomy;
    private $filters;
    private $max_posts;
    private $return_format;

    public function __construct($label, $name, $component_id, $required = false, $placeholder = '', $config = '') {
        parent::__construct($label, $name, $component_id, $required, $placeholder, $config);
        $this->type = 'relationship';
        
        // Parse config
        if ($config) {
            $config_array = is_string($config) ? json_decode($config, true) : $config;
            $this->filter_post_types = $config_array['filter_post_types'] ?? [];
            $this->filter_post_status = $config_array['filter_post_status'] ?? [];
            $this->filter_taxonomy = $config_array['filter_taxonomy'] ?? '';
            $this->filters = $config_array['filters'] ?? ['search', 'post_type'];
            $this->max_posts = $config_array['max_posts'] ?? 0;
            $this->return_format = $config_array['return_format'] ?? 'object';
        } else {
            $this->filter_post_types = [];
            $this->filter_post_status = [];
            $this->filter_taxonomy = '';
            $this->filters = ['search', 'post_type'];
            $this->max_posts = 0;
            $this->return_format = 'object';
        }
    }

    public function render($value = '') {
        $field_id = $this->getId();
        $field_name = "ccc_field_values[$field_id]";
        $field_id_attr = "ccc_field_$field_id";
        
        // Get available post types
        $post_types = get_post_types(['public' => true], 'objects');
        $post_statuses = get_post_stati(['public' => true], 'objects');
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        
        // Parse current value
        $selected_posts = [];
        if (!empty($value)) {
            $post_ids = is_array($value) ? $value : explode(',', $value);
            foreach ($post_ids as $post_id) {
                $post = get_post($post_id);
                if ($post) {
                    $selected_posts[] = [
                        'id' => $post->ID,
                        'title' => $post->post_title,
                        'type' => $post->post_type,
                        'status' => $post->post_status
                    ];
                }
            }
        }

        $config_json = json_encode([
            'filter_post_types' => $this->filter_post_types,
            'filter_post_status' => $this->filter_post_status,
            'filter_taxonomy' => $this->filter_taxonomy,
            'filters' => $this->filters,
            'max_posts' => $this->max_posts,
            'return_format' => $this->return_format
        ]);

        // For React metabox, return a simple container with config data
        // The actual rendering will be handled by the React RelationshipField component
        ob_start();
        ?>
        <div class="ccc-field ccc-relationship-field" data-config='<?php echo esc_attr($config_json); ?>'>
            <label for="<?php echo esc_attr($field_id_attr); ?>">
                <?php echo esc_html($this->label); ?>
                <?php if ($this->required): ?>
                    <span class="required">*</span>
                <?php endif; ?>
            </label>
            
            <!-- React component will render here -->
            <div id="ccc-relationship-field-<?php echo esc_attr($field_id); ?>" class="ccc-react-field-container"></div>
            
            <input 
                type="hidden" 
                id="<?php echo esc_attr($field_id_attr); ?>"
                name="<?php echo esc_attr($field_name); ?>"
                value="<?php echo esc_attr(is_array($value) ? implode(',', $value) : $value); ?>"
                <?php echo $this->required ? 'required' : ''; ?>
            >
        </div>
        <?php
        return ob_get_clean();
    }

    public function validate($value) {
        if ($this->required && empty($value)) {
            return new \WP_Error('required_field', 'This field is required.');
        }
        
        if (!empty($value)) {
            $post_ids = is_array($value) ? $value : explode(',', $value);
            foreach ($post_ids as $post_id) {
                if (!get_post($post_id)) {
                    return new \WP_Error('invalid_post', 'One or more selected posts do not exist.');
                }
            }
        }
        
        return true;
    }

    public function sanitize($value) {
        if (empty($value)) {
            return '';
        }
        
        $post_ids = is_array($value) ? $value : explode(',', $value);
        $sanitized_ids = [];
        
        foreach ($post_ids as $post_id) {
            $post_id = intval($post_id);
            if ($post_id > 0 && get_post($post_id)) {
                $sanitized_ids[] = $post_id;
            }
        }
        
        return implode(',', $sanitized_ids);
    }

    public function getConfig() {
        return json_encode([
            'filter_post_types' => $this->filter_post_types,
            'filter_post_status' => $this->filter_post_status,
            'filter_taxonomy' => $this->filter_taxonomy,
            'filters' => $this->filters,
            'max_posts' => $this->max_posts,
            'return_format' => $this->return_format
        ]);
    }

    // Getters and setters
    public function getFilterPostTypes() {
        return $this->filter_post_types;
    }

    public function setFilterPostTypes($filter_post_types) {
        $this->filter_post_types = $filter_post_types;
    }

    public function getFilterPostStatus() {
        return $this->filter_post_status;
    }

    public function setFilterPostStatus($filter_post_status) {
        $this->filter_post_status = $filter_post_status;
    }

    public function getFilterTaxonomy() {
        return $this->filter_taxonomy;
    }

    public function setFilterTaxonomy($filter_taxonomy) {
        $this->filter_taxonomy = $filter_taxonomy;
    }

    public function getFilters() {
        return $this->filters;
    }

    public function setFilters($filters) {
        $this->filters = $filters;
    }

    public function getMaxPosts() {
        return $this->max_posts;
    }

    public function setMaxPosts($max_posts) {
        $this->max_posts = $max_posts;
    }

    public function getReturnFormat() {
        return $this->return_format;
    }

    public function setReturnFormat($return_format) {
        $this->return_format = $return_format;
    }
} 