<?php
namespace CCC\Fields;

use CCC\Services\UniversalFieldAccessService;

defined('ABSPATH') || exit;

abstract class BaseField {
    protected $id;
    protected $label;
    protected $name;
    protected $component_id;
    protected $required;
    protected $placeholder;
    protected $config;
    protected $field_type;
    protected $universal_access_service;
    
    public function __construct($label, $name, $component_id, $required = false, $placeholder = '', $config = '')
    {
        $this->label = $label;
        $this->name = $name;
        $this->component_id = $component_id;
        $this->required = $required;
        $this->placeholder = $placeholder;
        $this->config = $config;
        $this->universal_access_service = new UniversalFieldAccessService();
    }
    
    abstract public function render($post_id, $instance_id, $value = '');
    abstract public function save();
    
    /**
     * Universal method to render field with PRO access check
     * All field classes should use this instead of direct render()
     */
    public function render_with_access_check($post_id, $instance_id, $value = '') {
        $access_check = $this->universal_access_service->can_access_field($this->field_type);
        
        if (!$access_check['canAccess']) {
            // Generate field content first
            ob_start();
            $this->render($post_id, $instance_id, $value);
            $field_content = ob_get_clean();
            
            return $this->universal_access_service->render_pro_field_disabled(
                $this->field_type, 
                $this->label, 
                $field_content
            );
        }
        
        // Field is accessible, render normally
        return $this->render($post_id, $instance_id, $value);
    }
    
    /**
     * Check if this field type can be accessed
     */
    public function can_access() {
        return $this->universal_access_service->can_access_field($this->field_type);
    }
    
    public function getId() {
        return $this->id;
    }
    
    public function setId($id) {
        $this->id = $id;
    }
    
    public function getLabel() {
        return $this->label;
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function getComponentId() {
        return $this->component_id;
    }
    
    public function isRequired() {
        return $this->required;
    }
    
    public function getPlaceholder() {
        return $this->placeholder;
    }
    
    public function getConfig() {
        return $this->config;
    }
    
    public function setConfig($config) {
        $this->config = $config;
    }
}
