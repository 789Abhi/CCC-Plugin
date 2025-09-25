<?php

namespace CCC\Fields;

use CCC\Fields\BaseField;

class RelationshipField extends BaseField {
    protected $field_type = 'relationship';
    
    private $post_types = [];
    private $post_status = [];
    private $taxonomy_filters = [];
    private $filters = ['search'];
    private $min_posts = 0;
    private $max_posts = 0;
    private $return_format = 'object';
    
    public function __construct($label, $name, $component_id, $required = false, $placeholder = '', $config = '') {
        parent::__construct($label, $name, $component_id, $required, $placeholder, $config);
        
        // Parse field configuration
        $field_config = is_string($config) ? json_decode($config, true) : $config;
        
        // Parse field configuration
        $this->post_types = isset($field_config['filter_post_types']) ? $field_config['filter_post_types'] : [];
        $this->post_status = isset($field_config['filter_post_status']) ? $field_config['filter_post_status'] : [];
        $this->taxonomy_filters = isset($field_config['filter_taxonomy']) ? $field_config['filter_taxonomy'] : '';
        $this->filters = isset($field_config['filters']) ? $field_config['filters'] : ['search'];
        $this->min_posts = isset($field_config['min_posts']) ? intval($field_config['min_posts']) : 0;
        $this->max_posts = isset($field_config['max_posts']) ? intval($field_config['max_posts']) : 0;
        $this->return_format = isset($field_config['return_format']) ? $field_config['return_format'] : 'object';
    }
    
    public function render($post_id, $instance_id, $value = '') {
        $field_id = 'ccc-relationship-' . uniqid();
        $field_name = $this->name;
        $field_value = $value ?: $this->getValue();
        $field_config = json_encode([
            'filter_post_types' => $this->post_types,
            'filter_post_status' => $this->post_status,
            'filter_taxonomy' => $this->taxonomy_filters,
            'filters' => $this->filters,
            'min_posts' => $this->min_posts,
            'max_posts' => $this->max_posts,
            'return_format' => $this->return_format,
            'multiple' => $this->max_posts !== 1
        ]);
        
        ob_start();
        ?>
        <div class="ccc-field ccc-relationship-field" data-field-name="<?php echo esc_attr($field_name); ?>">
            <input type="hidden" 
                   name="<?php echo esc_attr($field_name); ?>" 
                   id="<?php echo esc_attr($field_id); ?>" 
                   value="<?php echo esc_attr($field_value); ?>" />
            <div id="<?php echo esc_attr($field_id); ?>-container" 
                 data-field-config="<?php echo esc_attr($field_config); ?>"
                 data-field-value="<?php echo esc_attr($field_value); ?>">
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.CCCRelationshipField && typeof window.CCCRelationshipField.init === 'function') {
                window.CCCRelationshipField.init('<?php echo esc_js($field_id); ?>-container');
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function save() {
        // Implementation for saving the field
        return true;
    }
    
    public function sanitize($value) {
        if (empty($value)) {
            return '';
        }
        
        // Handle JSON array format or comma-separated values
        if (is_string($value)) {
            if (strpos($value, '[') === 0) {
                $post_ids = json_decode($value, true);
            } else {
                $post_ids = explode(',', $value);
            }
        } else {
            $post_ids = $value;
        }
        
        // Sanitize post IDs
        $post_ids = array_map('intval', array_filter($post_ids));
        
        // Validate against min/max limits
        if ($this->min_posts > 0 && count($post_ids) < $this->min_posts) {
            return new \WP_Error('min_posts', sprintf(
                'Minimum %d posts required, but only %d selected.',
                $this->min_posts,
                count($post_ids)
            ));
        }
        
        if ($this->max_posts > 0 && count($post_ids) > $this->max_posts) {
            return new \WP_Error('max_posts', sprintf(
                'Maximum %d posts allowed, but %d selected.',
                $this->max_posts,
                count($post_ids)
            ));
        }
        
        // Verify posts exist and are accessible
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                return new \WP_Error('invalid_post', sprintf('Post ID %d does not exist.', $post_id));
            }
            
            // Check post type filter
            if (!empty($this->post_types) && !in_array($post->post_type, $this->post_types)) {
                return new \WP_Error('invalid_post_type', sprintf(
                    'Post "%s" is not of an allowed post type.',
                    $post->post_title
                ));
            }
            
            // Check post status filter
            if (!empty($this->post_status) && !in_array($post->post_status, $this->post_status)) {
                return new \WP_Error('invalid_post_status', sprintf(
                    'Post "%s" does not have an allowed status.',
                    $post->post_title
                ));
            }
        }
        
        // Return as JSON string for database storage
        return json_encode($post_ids);
    }
    
    public function getFieldConfig() {
        return [
            'post_types' => [
                'type' => 'select',
                'label' => 'Filter by Post Type',
                'description' => 'Filters the selectable results by post type. When left empty, all post types are shown.',
                'multiple' => true,
                'options' => $this->getAvailablePostTypes()
            ],
            'post_status' => [
                'type' => 'select',
                'label' => 'Filter by Post Status',
                'description' => 'Filters the selectable results by status, i.e, Published, Draft, etc.',
                'multiple' => true,
                'options' => [
                    'publish' => 'Published',
                    'draft' => 'Draft',
                    'private' => 'Private',
                    'pending' => 'Pending Review',
                    'future' => 'Scheduled',
                    'trash' => 'Trash'
                ]
            ],
            'taxonomy_filters' => [
                'type' => 'repeater',
                'label' => 'Filter by Taxonomy',
                'description' => 'Filters the selectable results via one or more taxonomy terms.',
                'fields' => [
                    'taxonomy' => [
                        'type' => 'select',
                        'label' => 'Taxonomy',
                        'options' => $this->getAvailableTaxonomies()
                    ],
                    'terms' => [
                        'type' => 'select',
                        'label' => 'Terms',
                        'multiple' => true,
                        'description' => 'Select specific terms to filter by'
                    ]
                ]
            ],
            'filters' => [
                'type' => 'select',
                'label' => 'Filters',
                'description' => 'Specifies which filters are displayed in the component.',
                'multiple' => true,
                'options' => [
                    'search' => 'Search',
                    'post_type' => 'Post Type',
                    'taxonomy' => 'Taxonomy'
                ]
            ],
            'min_posts' => [
                'type' => 'number',
                'label' => 'Minimum Posts',
                'description' => 'Sets a limit on how many posts are required.',
                'min' => 0
            ],
            'max_posts' => [
                'type' => 'number',
                'label' => 'Maximum Posts',
                'description' => 'Sets a limit on how many posts are allowed.',
                'min' => 1
            ],
            'return_format' => [
                'type' => 'select',
                'label' => 'Return Format',
                'description' => 'Specifies the format of the returned data.',
                'options' => [
                    'object' => 'Post Objects',
                    'array' => 'Post Arrays',
                    'id' => 'Post IDs'
                ]
            ]
        ];
    }
    
    public function validate($value) {
        if ($this->required && empty($value)) {
            return new \WP_Error('required', 'This field is required.');
        }
        
        if (!empty($value)) {
            $sanitized = $this->sanitize($value);
            if (is_wp_error($sanitized)) {
                return $sanitized;
            }
        }
        
        return true;
    }
    
    private function getAvailablePostTypes() {
        $post_types = get_post_types(['public' => true], 'objects');
        $options = [];
        
        foreach ($post_types as $post_type) {
            $options[$post_type->name] = $post_type->label;
        }
        
        return $options;
    }
    
    private function getAvailableTaxonomies() {
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        $options = [];
        
        foreach ($taxonomies as $taxonomy) {
            $options[$taxonomy->name] = $taxonomy->label;
        }
        
        return $options;
    }
    
    public function getPostTypes() {
        return $this->post_types;
    }
    
    public function getPostStatus() {
        return $this->post_status;
    }
    
    public function getTaxonomyFilters() {
        return $this->taxonomy_filters;
    }
    
    public function getFilters() {
        return $this->filters;
    }
    
    public function getMinPosts() {
        return $this->min_posts;
    }
    
    public function getMaxPosts() {
        return $this->max_posts;
    }
    
    public function getReturnFormat() {
        return $this->return_format;
    }
    
    /**
     * Process field value based on return format
     * 
     * @param mixed $value Raw field value (post IDs)
     * @return mixed Processed value based on return_format setting
     */
    public function processValue($value) {
        if (empty($value)) {
            return $this->max_posts === 1 ? null : [];
        }
        
        // Parse post IDs
        $post_ids = [];
        if (is_string($value)) {
            if (strpos($value, '[') === 0) {
                $post_ids = json_decode($value, true) ?: [];
            } else {
                $post_ids = explode(',', $value);
            }
        } elseif (is_array($value)) {
            $post_ids = $value;
        } else {
            $post_ids = [$value];
        }
        
        $post_ids = array_map('intval', array_filter($post_ids));
        
        if (empty($post_ids)) {
            return $this->max_posts === 1 ? null : [];
        }
        
        // Return based on format
        switch ($this->return_format) {
            case 'object':
                $posts = array_map(function($id) {
                    return get_post($id);
                }, $post_ids);
                return $this->max_posts === 1 ? $posts[0] : $posts;
                
            case 'array':
                $posts = array_map(function($id) {
                    $post = get_post($id);
                    return $post ? [
                        'ID' => $post->ID,
                        'post_title' => $post->post_title,
                        'post_name' => $post->post_name,
                        'post_type' => $post->post_type,
                        'post_status' => $post->post_status,
                        'post_date' => $post->post_date,
                        'post_modified' => $post->post_modified,
                        'post_content' => $post->post_content,
                        'post_excerpt' => $post->post_excerpt,
                        'permalink' => get_permalink($post->ID),
                        'featured_image' => get_the_post_thumbnail_url($post->ID, 'medium')
                    ] : null;
                }, $post_ids);
                $posts = array_filter($posts);
                return $this->max_posts === 1 ? ($posts[0] ?? null) : $posts;
                
            default: // 'id'
                return $this->max_posts === 1 ? $post_ids[0] : $post_ids;
        }
    }
    
    /**
     * Get post display information for admin/debug purposes
     * 
     * @param mixed $value Raw field value
     * @return array Array with post information for display
     */
    public function getDisplayInfo($value) {
        if (empty($value)) {
            return [];
        }
        
        $post_ids = [];
        if (is_string($value)) {
            if (strpos($value, '[') === 0) {
                $post_ids = json_decode($value, true) ?: [];
            } else {
                $post_ids = explode(',', $value);
            }
        } elseif (is_array($value)) {
            $post_ids = $value;
        } else {
            $post_ids = [$value];
        }
        
        $post_ids = array_map('intval', array_filter($post_ids));
        
        $display_info = [];
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if ($post) {
                $post_type_obj = get_post_type_object($post->post_type);
                $display_info[] = [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'post_type' => $post->post_type,
                    'post_type_label' => $post_type_obj ? $post_type_obj->label : $post->post_type,
                    'post_status' => $post->post_status,
                    'permalink' => get_permalink($post->ID),
                    'featured_image' => get_the_post_thumbnail_url($post->ID, 'thumbnail')
                ];
            } else {
                $display_info[] = [
                    'id' => $post_id,
                    'title' => '(Post not found)',
                    'post_type' => '',
                    'post_type_label' => '',
                    'post_status' => '',
                    'permalink' => '',
                    'featured_image' => ''
                ];
            }
        }
        
        return $display_info;
    }
}
