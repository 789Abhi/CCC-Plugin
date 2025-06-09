<?php
namespace CCC\Fields;

defined('ABSPATH') || exit;

abstract class BaseField {
    protected $label;
    protected $name;
    protected $component_id;
    protected $required;
    protected $placeholder;
    
    public function __construct($label, $name, $component_id, $required = false, $placeholder = '') {
        $this->label = $label;
        $this->name = $name;
        $this->component_id = $component_id;
        $this->required = $required;
        $this->placeholder = $placeholder;
    }
    
    abstract public function render($post_id, $instance_id, $value = '');
    abstract public function save();
    
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
}
