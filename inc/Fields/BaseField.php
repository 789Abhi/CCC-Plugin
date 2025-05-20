<?php
namespace CCC\Fields;

abstract class BaseField {
    protected $label;
    protected $name;
    protected $component_id;

    public function __construct($label, $name, $component_id) {
        $this->label = $label;
        $this->name = $name;
        $this->component_id = $component_id;
    }

    abstract public function save();
}