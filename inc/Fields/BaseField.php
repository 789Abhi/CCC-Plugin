<?php
namespace CCC\Fields;

defined('ABSPATH') || exit;

abstract class BaseField {
    protected $id;
    protected $label;
    protected $name;
    protected $component_id;
    protected $required;
    protected $placeholder;
    protected $config;
    
    public function __construct($label, $name, $component_id, $required = false, $placeholder = '', $config = '')
    {
        $this->label = $label;
        $this->name = $name;
        $this->component_id = $component_id;
        $this->required = $required;
        $this->placeholder = $placeholder;
        $this->config = $config;
    }
    
    abstract public function render($post_id, $instance_id, $value = '');
    abstract public function save();
    
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
