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
        add_action('admin_menu', [$this, 'addMigrationToolPage']);
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

    public function addMigrationToolPage() {
        add_management_page(
            'CCC Migrate Nested Fields',
            'CCC Migrate Nested Fields',
            'manage_options',
            'ccc-migrate-nested-fields',
            [$this, 'renderMigrationToolPage']
        );
    }

    public function renderMigrationToolPage() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to access this page.');
        }
        $ran = false;
        $error = '';
        if (isset($_POST['ccc_run_nested_fields_migration'])) {
            try {
                \CCC\Core\Database::migrateAllNestedFieldsToRows();
                $ran = true;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
        echo '<div class=""><h1>CCC Migrate Nested Fields</h1>';
        if ($ran) {
            echo '<div style="color:green;font-weight:bold;">Migration complete! All nested fields are now real DB rows.</div>';
        } elseif ($error) {
            echo '<div style="color:red;">Error: ' . esc_html($error) . '</div>';
        }
        echo '<form method="post">';
        echo '<p>This tool will convert all nested fields in config.nested_fields into real database rows. <strong>Run this only once.</strong></p>';
        echo '<input type="submit" name="ccc_run_nested_fields_migration" class="button button-primary" value="Run Migration">';
        echo '</form></div>';
    }

    public function renderComponentsPage() {
        error_log("CCC AdminManager: Rendering Components page");
        echo '<div class="">';
        echo '<div id="root" data-page="components"></div>';
        echo '<div id="fallback" style="display:none; padding: 20px; background: #f0f0f0; border: 1px solid #ccc; margin: 20px 0;">';
        echo '<h2>React App Not Loading</h2>';
        echo '<p>If you see this message, the React app failed to load. Check the browser console for errors.</p>';
        echo '</div>';
        echo '<script>
            console.log("CCC: Components page rendered, root element:", document.getElementById("root"));
            setTimeout(function() {
                if (!document.querySelector("#root").children.length) {
                    document.getElementById("fallback").style.display = "block";
                }
            }, 2000);
        </script>';
        echo '</div>';
    }

    public function renderPostTypesPage() {
        error_log("CCC AdminManager: Rendering Post Types page");
        echo '<div class="">';
        echo '<div id="root" data-page="posttypes"></div>';
        echo '<div id="fallback" style="display:none; padding: 20px; background: #f0f0f0; border: 1px solid #ccc; margin: 20px 0;">';
        echo '<h2>React App Not Loading</h2>';
        echo '<p>If you see this message, the React app failed to load. Check the browser console for errors.</p>';
        echo '</div>';
        echo '<script>
            console.log("CCC: Post Types page rendered, root element:", document.getElementById("root"));
            console.log("CCC: Checking if React script is loaded...");
            console.log("CCC: React script elements:", document.querySelectorAll("script[src*=\'index-\']"));
            setTimeout(function() {
                if (!document.querySelector("#root").children.length) {
                    document.getElementById("fallback").style.display = "block";
                }
            }, 2000);
        </script>';
        echo '</div>';
    }

    public function renderTaxonomiesPage() {
        error_log("CCC AdminManager: Rendering Taxonomies page");
        echo '<div class="">';
        echo '<div id="root" data-page="taxonomies"></div>';
        echo '<div id="fallback" style="display:none; padding: 20px; background: #f0f0f0; border: 1px solid #ccc; margin: 20px 0;">';
        echo '<h2>React App Not Loading</h2>';
        echo '<p>If you see this message, the React app failed to load. Check the browser console for errors.</p>';
        echo '</div>';
        echo '<script>
            console.log("CCC: Taxonomies page rendered, root element:", document.getElementById("root"));
            setTimeout(function() {
                if (!document.querySelector("#root").children.length) {
                    document.getElementById("fallback").style.display = "block";
                }
            }, 2000);
        </script>';
        echo '</div>';
    }

    public function renderImportExportPage() {
        error_log("CCC AdminManager: Rendering Import/Export page");
        echo '<div class="">';
        echo '<div id="root" data-page="importexport"></div>';
        echo '<div id="fallback" style="display:none; padding: 20px; background: #f0f0f0; border: 1px solid #ccc; margin: 20px 0;">';
        echo '<h2>React App Not Loading</h2>';
        echo '<p>If you see this message, the React app failed to load. Check the browser console for errors.</p>';
        echo '</div>';
        echo '<script>
            console.log("CCC: Import/Export page rendered, root element:", document.getElementById("root"));
            setTimeout(function() {
                if (!document.querySelector("#root").children.length) {
                    document.getElementById("fallback").style.display = "block";
                }
            }, 2000);
        </script>';
        echo '</div>';
    }

    public function renderSettingsPage() {
        error_log("CCC AdminManager: Rendering Settings page");
        echo '<div class="">';
        echo '<div id="root" data-page="settings"></div>';
        echo '<div id="fallback" style="display:none; padding: 20px; background: #f0f0f0; border: 1px solid #ccc; margin: 20px 0;">';
        echo '<h2>React App Not Loading</h2>';
        echo '<p>If you see this message, the React app failed to load. Check the browser console for errors.</p>';
        echo '</div>';
        echo '<script>
            console.log("CCC: Settings page rendered, root element:", document.getElementById("root"));
            setTimeout(function() {
                if (!document.querySelector("#root").children.length) {
                    document.getElementById("fallback").style.display = "block";
                }
            }, 2000);
        </script>';
        echo '</div>';
    }
}
