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
        // Check and update database schema on every load
        add_action('init', ['\CCC\Core\Database', 'checkAndUpdateSchema'], 1);
        
        $this->admin_manager->init();
        $this->template_manager->init();
        $this->ajax_handler->init();
    }
}
