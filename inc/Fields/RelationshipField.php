<?php
namespace CCC\Fields;

defined('ABSPATH') || exit;

class RelationshipField extends BaseField {
    private $post_types = ['post', 'page'];
    private $max_items = 0;
    
    public function __construct($label, $name, $component_id, $required = false, $placeholder = '', $config = []) {
        parent::__construct($label, $name, $component_id, $required, $placeholder);
        
        if (is_string($config)) {
            $config = json_decode($config, true);
        }
        
        $this->post_types = isset($config['post_types']) ? (array)$config['post_types'] : ['post', 'page'];
        $this->max_items = isset($config['max_items']) ? (int)$config['max_items'] : 0;
    }
    
    public function render($post_id, $instance_id, $value = '') {
        $field_id = "ccc_field_{$this->name}_{$instance_id}";
        $field_name = "ccc_field_values[{$this->component_id}][{$instance_id}][{$this->name}]";
        
        // Handle array values
        $selected_ids = [];
        if (!empty($value)) {
            if (is_string($value) && strpos($value, ',') !== false) {
                $selected_ids = explode(',', $value);
            } elseif (is_array($value)) {
                $selected_ids = $value;
            } else {
                $selected_ids = [$value];
            }
        }
        $selected_ids = array_map('intval', $selected_ids);
        
        // Get selected posts data
        $selected_posts = [];
        if (!empty($selected_ids)) {
            $selected_posts = get_posts([
                'post_type' => $this->post_types,
                'post__in' => $selected_ids,
                'orderby' => 'post__in',
                'posts_per_page' => -1,
            ]);
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-sortable');
        
        ob_start();
        ?>
        <div class="ccc-field ccc-field-relationship">
            <label class="ccc-field-label">
                <?php echo esc_html($this->label); ?>
                <?php if ($this->required): ?>
                    <span class="ccc-required">*</span>
                <?php endif; ?>
            </label>
            
            <div class="ccc-relationship-container">
                <div class="ccc-relationship-search">
                    <input 
                        type="text" 
                        class="ccc-relationship-search-input" 
                        placeholder="Search..." 
                        data-field-id="<?php echo esc_attr($field_id); ?>"
                    >
                    
                    <select class="ccc-relationship-post-type-filter">
                        <option value="">All Types</option>
                        <?php foreach ($this->post_types as $post_type): ?>
                            <?php $post_type_obj = get_post_type_object($post_type); ?>
                            <option value="<?php echo esc_attr($post_type); ?>">
                                <?php echo esc_html($post_type_obj ? $post_type_obj->labels->singular_name : $post_type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="ccc-relationship-available">
                    <h4>Available Items</h4>
                    <ul class="ccc-relationship-available-list" data-field-id="<?php echo esc_attr($field_id); ?>">
                        <li class="ccc-relationship-loading">Loading...</li>
                    </ul>
                </div>
                
                <div class="ccc-relationship-selected">
                    <h4>Selected Items</h4>
                    <ul class="ccc-relationship-selected-list" data-field-id="<?php echo esc_attr($field_id); ?>">
                        <?php foreach ($selected_posts as $post): ?>
                            <li data-id="<?php echo esc_attr($post->ID); ?>">
                                <span class="ccc-relationship-title"><?php echo esc_html($post->post_title); ?></span>
                                <span class="ccc-relationship-type"><?php echo esc_html(get_post_type_object($post->post_type)->labels->singular_name); ?></span>
                                <button type="button" class="ccc-relationship-remove" title="Remove">&times;</button>
                                <input type="hidden" name="<?php echo esc_attr($field_name); ?>[]" value="<?php echo esc_attr($post->ID); ?>">
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var fieldId = '<?php echo esc_attr($field_id); ?>';
            var maxItems = <?php echo esc_js($this->max_items); ?>;
            var selectedIds = <?php echo json_encode($selected_ids); ?>;
            var postTypes = <?php echo json_encode($this->post_types); ?>;
            
            // Make selected items sortable
            $('.ccc-relationship-selected-list[data-field-id="' + fieldId + '"]').sortable({
                placeholder: 'ccc-relationship-placeholder',
                update: function() {
                    updateSelectedIds();
                }
            });
            
            // Load available posts
            loadAvailablePosts('', '');
            
            // Handle search input
            $('.ccc-relationship-search-input[data-field-id="' + fieldId + '"]').on('input', function() {
                var searchTerm = $(this).val();
                var postType = $('.ccc-relationship-post-type-filter').val();
                loadAvailablePosts(searchTerm, postType);
            });
            
            // Handle post type filter change
            $('.ccc-relationship-post-type-filter').on('change', function() {
                var searchTerm = $('.ccc-relationship-search-input[data-field-id="' + fieldId + '"]').val();
                var postType = $(this).val();
                loadAvailablePosts(searchTerm, postType);
            });
            
            // Handle adding items
            $(document).on('click', '.ccc-relationship-available-list[data-field-id="' + fieldId + '"] li', function() {
                var $item = $(this);
                var postId = $item.data('id');
                
                // Check if already selected
                if (selectedIds.indexOf(postId) !== -1) {
                    return;
                }
                
                // Check max items
                if (maxItems > 0 && selectedIds.length >= maxItems) {
                    alert('Maximum ' + maxItems + ' items allowed.');
                    return;
                }
                
                // Add to selected
                var $selectedList = $('.ccc-relationship-selected-list[data-field-id="' + fieldId + '"]');
                var $newItem = $('<li data-id="' + postId + '">' +
                    '<span class="ccc-relationship-title">' + $item.find('.ccc-relationship-title').text() + '</span>' +
                    '<span class="ccc-relationship-type">' + $item.find('.ccc-relationship-type').text() + '</span>' +
                    '<button type="button" class="ccc-relationship-remove" title="Remove">&times;</button>' +
                    '<input type="hidden" name="<?php echo esc_attr($field_name); ?>[]" value="' + postId + '">' +
                    '</li>');
                
                $selectedList.append($newItem);
                selectedIds.push(postId);
                
                // Update available items
                $item.addClass('selected');
            });
            
            // Handle removing items
            $(document).on('click', '.ccc-relationship-remove', function(e) {
                e.stopPropagation();
                
                var $item = $(this).closest('li');
                var postId = $item.data('id');
                
                // Remove from selected
                $item.remove();
                
                // Update selectedIds
                selectedIds = selectedIds.filter(function(id) {
                    return id !== postId;
                });
                
                // Update available items
                $('.ccc-relationship-available-list[data-field-id="' + fieldId + '"] li[data-id="' + postId + '"]').removeClass('selected');
            });
            
            function loadAvailablePosts(search, postType) {
                var $availableList = $('.ccc-relationship-available-list[data-field-id="' + fieldId + '"]');
                $availableList.html('<li class="ccc-relationship-loading">Loading...</li>');
                
                $.post(ajaxurl, {
                    action: 'ccc_get_relationship_posts',
                    search: search,
                    post_type: postType || postTypes,
                    selected: selectedIds,
                    nonce: '<?php echo wp_create_nonce('ccc_relationship'); ?>'
                }, function(response) {
                    if (response.success && response.data) {
                        $availableList.empty();
                        
                        if (response.data.length === 0) {
                            $availableList.html('<li class="ccc-relationship-empty">No items found</li>');
                            return;
                        }
                        
                        $.each(response.data, function(i, post) {
                            var isSelected = selectedIds.indexOf(post.id) !== -1;
                            var $item = $('<li data-id="' + post.id + '" class="' + (isSelected ? 'selected' : '') + '">' +
                                '<span class="ccc-relationship-title">' + post.title + '</span>' +
                                '<span class="ccc-relationship-type">' + post.type_label + '</span>' +
                                '</li>');
                            
                            $availableList.append($item);
                        });
                    } else {
                        $availableList.html('<li class="ccc-relationship-error">Error loading posts</li>');
                    }
                });
            }
            
            function updateSelectedIds() {
                selectedIds = [];
                $('.ccc-relationship-selected-list[data-field-id="' + fieldId + '"] li').each(function() {
                    selectedIds.push($(this).data('id'));
                });
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
}
