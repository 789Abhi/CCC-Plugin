<?php
namespace CCC\Admin;

use CCC\Admin\MetaBoxManager;
use CCC\Admin\AssetManager;

defined('ABSPATH') || exit;

class AdminManager {
    private $meta_box_manager;
    private $asset_manager;

    public function __construct() {
        $this->meta_box_manager = new MetaBoxManager();
        $this->asset_manager = new AssetManager();
    }

    public function init() {
        add_action('admin_menu', [$this, 'registerAdminPages']);
        $this->meta_box_manager->init();
        $this->asset_manager->init();
    }

    public function registerAdminPages() {
        add_menu_page(
            'Custom Craft Component',
            'Custom Components',
            'manage_options',
            'custom-craft-component',
            [$this, 'renderComponentsPage'],
            'dashicons-admin-customizer',
            20
        );

        add_submenu_page('custom-craft-component', 'Components', 'Components', 'manage_options', 'custom-craft-component', [$this, 'renderComponentsPage']);
        add_submenu_page('custom-craft-component', 'Post Types', 'Post Types', 'manage_options', 'custom-craft-posttypes', [$this, 'renderPostTypesPage']);
        add_submenu_page('custom-craft-component', 'Taxonomies', 'Taxonomies', 'manage_options', 'custom-craft-taxonomies', [$this, 'renderTaxonomiesPage']);
        add_submenu_page('custom-craft-component', 'Import-Export', 'Import-Export', 'manage_options', 'custom-craft-importexport', [$this, 'renderImportExportPage']);
        add_submenu_page('custom-craft-component', 'Settings', 'Settings', 'manage_options', 'custom-craft-settings', [$this, 'renderSettingsPage']);
    }

    public function renderComponentsPage() {
        echo '<div id="root" data-page="components"></div>';
    }

    public function renderPostTypesPage() {
        echo '<div id="root" data-page="posttypes"></div>';
    }

    public function renderTaxonomiesPage() {
        echo '<div id="root" data-page="taxonomies"></div>';
    }

    public function renderImportExportPage() {
        echo '<div id="root" data-page="importexport"></div>';
    }

    public function renderSettingsPage() {
        echo '<div id="root" data-page="settings"></div>';
    }
}
