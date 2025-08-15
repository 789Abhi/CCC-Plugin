<?php
namespace CCC\Fields;

defined('ABSPATH') || exit;

class UserField extends BaseField {
    private $role_filter = [];
    private $multiple = false;
    private $return_type = 'id'; // 'id', 'object', 'array'
    
    public function __construct($label, $name, $component_id, $required = false, $placeholder = '', $config = []) {
        parent::__construct($label, $name, $component_id, $required, $placeholder);
        
        if (is_string($config)) {
            $config = json_decode($config, true);
        }
        
        $this->role_filter = isset($config['role_filter']) ? (array) $config['role_filter'] : [];
        $this->multiple = isset($config['multiple']) ? (bool) $config['multiple'] : false;
        $this->return_type = isset($config['return_type']) ? sanitize_text_field($config['return_type']) : 'id';
    }
    
    public function render($post_id, $instance_id, $value = '') {
        $field_id = "ccc_field_{$this->name}_{$instance_id}";
        $field_name = "ccc_field_values[{$this->component_id}][{$instance_id}][{$this->name}]";
        
        $required = $this->required ? 'required' : '';
        $placeholder = esc_attr($this->placeholder ?: '— Select User —');
        
        // Get users based on role filter
        $users = $this->getUsers();
        
        ob_start();
        ?>
        <div class="ccc-field ccc-field-user">
            <label for="<?php echo esc_attr($field_id); ?>" class="ccc-field-label">
                <?php echo esc_html($this->label); ?>
                <?php if ($this->required): ?>
                    <span class="ccc-required">*</span>
                <?php endif; ?>
            </label>
            
            <?php if ($this->multiple): ?>
                <div class="ccc-user-multiple-container">
                    <div class="ccc-user-selected-list">
                        <?php 
                        if (!empty($value)) {
                            $selected_users = is_array($value) ? $value : [$value];
                            foreach ($selected_users as $user_id) {
                                $user = get_user_by('ID', $user_id);
                                if ($user) {
                                    echo '<div class="ccc-user-tag">';
                                    echo '<span>' . esc_html($user->display_name) . '</span>';
                                    echo '<button type="button" class="ccc-user-remove" data-user-id="' . esc_attr($user_id) . '">&times;</button>';
                                    echo '</div>';
                                }
                            }
                        }
                        ?>
                    </div>
                    <select 
                        id="<?php echo esc_attr($field_id); ?>_select" 
                        class="ccc-user-select" 
                        data-field-id="<?php echo esc_attr($field_id); ?>"
                        data-field-name="<?php echo esc_attr($field_name); ?>"
                        data-multiple="true"
                    >
                        <option value=""><?php echo $placeholder; ?></option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo esc_attr($user->ID); ?>">
                                <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" 
                           id="<?php echo esc_attr($field_id); ?>" 
                           name="<?php echo esc_attr($field_name); ?>[]" 
                           value="<?php echo esc_attr(is_array($value) ? implode(',', $value) : $value); ?>"
                           <?php echo $required; ?>
                    />
                </div>
            <?php else: ?>
                <select 
                    id="<?php echo esc_attr($field_id); ?>" 
                    name="<?php echo esc_attr($field_name); ?>"
                    class="ccc-user-select" 
                    <?php echo $required; ?>
                >
                    <option value=""><?php echo $placeholder; ?></option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($value, $user->ID); ?>>
                            <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Handle multiple user selection
            $('.ccc-user-select[data-multiple="true"]').on('change', function() {
                var $select = $(this);
                var $hidden = $('#' + $select.data('field-id'));
                var $container = $select.closest('.ccc-user-multiple-container');
                var $selectedList = $container.find('.ccc-user-selected-list');
                var selectedUserId = $select.val();
                
                if (selectedUserId) {
                    var $option = $select.find('option:selected');
                    var userName = $option.text();
                    
                    // Add user tag
                    var $tag = $('<div class="ccc-user-tag">' +
                        '<span>' + userName + '</span>' +
                        '<button type="button" class="ccc-user-remove" data-user-id="' + selectedUserId + '">&times;</button>' +
                        '</div>');
                    
                    $selectedList.append($tag);
                    
                    // Update hidden input
                    var currentValues = $hidden.val() ? $hidden.val().split(',') : [];
                    if (currentValues.indexOf(selectedUserId) === -1) {
                        currentValues.push(selectedUserId);
                    }
                    $hidden.val(currentValues.join(','));
                    
                    // Reset select
                    $select.val('');
                }
            });
            
            // Handle user removal
            $(document).on('click', '.ccc-user-remove', function() {
                var $tag = $(this).closest('.ccc-user-tag');
                var userId = $(this).data('user-id');
                var $container = $tag.closest('.ccc-user-multiple-container');
                var $hidden = $container.find('input[type="hidden"]');
                
                // Remove from hidden input
                var currentValues = $hidden.val() ? $hidden.val().split(',') : [];
                var index = currentValues.indexOf(userId);
                if (index > -1) {
                    currentValues.splice(index, 1);
                }
                $hidden.val(currentValues.join(','));
                
                // Remove tag
                $tag.remove();
            });
        });
        </script>
        
        <style>
        .ccc-user-multiple-container {
            margin-top: 5px;
        }
        .ccc-user-selected-list {
            margin-bottom: 10px;
        }
        .ccc-user-tag {
            display: inline-flex;
            align-items: center;
            background: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 3px;
            padding: 2px 8px;
            margin: 2px;
            font-size: 12px;
        }
        .ccc-user-remove {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            margin-left: 5px;
            font-size: 14px;
            line-height: 1;
        }
        .ccc-user-remove:hover {
            color: #d00;
        }
        .ccc-user-select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    public function save() {
        // Implementation for saving the field
        return true;
    }
    
    private function getUsers() {
        $args = [
            'orderby' => 'display_name',
            'order' => 'ASC',
            'number' => -1
        ];
        
        // Add role filter if specified
        if (!empty($this->role_filter)) {
            $args['role__in'] = $this->role_filter;
        }
        
        return get_users($args);
    }
    
    public function getRoleFilter() {
        return $this->role_filter;
    }
    
    public function isMultiple() {
        return $this->multiple;
    }
    
    public function getReturnType() {
        return $this->return_type;
    }
} 