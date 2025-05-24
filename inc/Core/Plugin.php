<?php
namespace CCC\Core;

use CCC\Admin\AdminManager;
use CCC\Frontend\TemplateManager;
use CCC\Ajax\AjaxHandler;

defined('ABSPATH') || exit;

class Plugin {
    private $admin_manager;
    private $template_manager;
    private $ajax_handler;

    public function __construct() {
        $this->admin_manager = new AdminManager();
        $this->template_manager = new TemplateManager();
        $this->ajax_handler = new AjaxHandler();
    }

    public function init() {
        $this->admin_manager->init();
        $this->template_manager->init();
        $this->ajax_handler->init();
    }
}
