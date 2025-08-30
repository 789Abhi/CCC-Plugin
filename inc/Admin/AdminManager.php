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
        
        // Beautiful loading state
        echo '<div id="loading-state" class="ccc-loading-container">';
        echo '<div class="ccc-loading-content">';
        echo '<div class="ccc-loading-spinner"></div>';
        echo '<h2 class="ccc-loading-title">Loading Custom Craft Component</h2>';
        echo '<p class="ccc-loading-subtitle">Please wait while we prepare your components...</p>';
        echo '</div>';
        echo '</div>';
        
        echo '<script>
            console.log("CCC: Components page rendered, root element:", document.getElementById("root"));
            
            // Show loading state immediately
            document.getElementById("loading-state").style.display = "flex";
            
            // Check if React app loads successfully
            function checkReactApp() {
                const root = document.querySelector("#root");
                if (root && root.children.length > 0) {
                    // React app loaded successfully, hide loading state
                    document.getElementById("loading-state").style.display = "none";
                    console.log("CCC: React app loaded successfully");
                } else {
                    // Check again in 500ms
                    setTimeout(checkReactApp, 500);
                }
            }
            
            // Start checking after a short delay
            setTimeout(checkReactApp, 100);
        </script>';
        
        // Loading styles
        echo '<style>
            .ccc-loading-container {
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 400px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 12px;
                margin: 20px 0;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            }
            
            .ccc-loading-content {
                text-align: center;
                color: white;
                padding: 40px;
            }
            
            .ccc-loading-spinner {
                width: 60px;
                height: 60px;
                border: 4px solid rgba(255,255,255,0.3);
                border-top: 4px solid white;
                border-radius: 50%;
                animation: ccc-spin 1s linear infinite;
                margin: 0 auto 20px;
            }
            
            .ccc-loading-title {
                font-size: 24px;
                font-weight: 600;
                margin: 0 0 10px 0;
                text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }
            
            .ccc-loading-subtitle {
                font-size: 16px;
                margin: 0;
                opacity: 0.9;
                text-shadow: 0 1px 2px rgba(0,0,0,0.2);
            }
            
            @keyframes ccc-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>';
        
        echo '</div>';
    }

    public function renderPostTypesPage() {
        error_log("CCC AdminManager: Rendering Post Types page");
        echo '<div class="">';
        echo '<div id="root" data-page="posttypes"></div>';
        
        // Beautiful loading state
        echo '<div id="loading-state" class="ccc-loading-container">';
        echo '<div class="ccc-loading-content">';
        echo '<div class="ccc-loading-spinner"></div>';
        echo '<h2 class="ccc-loading-title">Loading Post Types Manager</h2>';
        echo '<p class="ccc-loading-subtitle">Please wait while we prepare your post types...</p>';
        echo '</div>';
        echo '</div>';
        
        echo '<script>
            console.log("CCC: Post Types page rendered, root element:", document.getElementById("root"));
            console.log("CCC: Checking if React script is loaded...");
            console.log("CCC: React script elements:", document.querySelectorAll("script[src*=\'index-\']"));
            
            // Show loading state immediately
            document.getElementById("loading-state").style.display = "flex";
            
            // Check if React app loads successfully
            function checkReactApp() {
                const root = document.querySelector("#root");
                if (root && root.children.length > 0) {
                    // React app loaded successfully, hide loading state
                    document.getElementById("loading-state").style.display = "none";
                    console.log("CCC: React app loaded successfully");
                } else {
                    // Check again in 500ms
                    setTimeout(checkReactApp, 500);
                }
            }
            
            // Start checking after a short delay
            setTimeout(checkReactApp, 100);
        </script>';
        
        // Loading styles
        echo '<style>
            .ccc-loading-container {
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 400px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 12px;
                margin: 20px 0;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            }
            
            .ccc-loading-content {
                text-align: center;
                color: white;
                padding: 40px;
            }
            
            .ccc-loading-spinner {
                width: 60px;
                height: 60px;
                border: 4px solid rgba(255,255,255,0.3);
                border-top: 4px solid white;
                border-radius: 50%;
                animation: ccc-spin 1s linear infinite;
                margin: 0 auto 20px;
            }
            
            .ccc-loading-title {
                font-size: 24px;
                font-weight: 600;
                margin: 0 0 10px 0;
                text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }
            
            .ccc-loading-subtitle {
                font-size: 16px;
                margin: 0;
                opacity: 0.9;
                text-shadow: 0 1px 2px rgba(0,0,0,0.2);
            }
            
            @keyframes ccc-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>';
        
        echo '</div>';
    }

    public function renderTaxonomiesPage() {
        error_log("CCC AdminManager: Rendering Taxonomies page");
        echo '<div class="">';
        echo '<div id="root" data-page="taxonomies"></div>';
        
        // Beautiful loading state
        echo '<div id="loading-state" class="ccc-loading-container">';
        echo '<div class="ccc-loading-content">';
        echo '<div class="ccc-loading-spinner"></div>';
        echo '<h2 class="ccc-loading-title">Loading Taxonomies Manager</h2>';
        echo '<p class="ccc-loading-subtitle">Please wait while we prepare your taxonomies...</p>';
        echo '</div>';
        echo '</div>';
        
        echo '<script>
            console.log("CCC: Taxonomies page rendered, root element:", document.getElementById("root"));
            
            // Show loading state immediately
            document.getElementById("loading-state").style.display = "flex";
            
            // Check if React app loads successfully
            function checkReactApp() {
                const root = document.querySelector("#root");
                if (root && root.children.length > 0) {
                    // React app loaded successfully, hide loading state
                    document.getElementById("loading-state").style.display = "none";
                    console.log("CCC: React app loaded successfully");
                } else {
                    // Check again in 500ms
                    setTimeout(checkReactApp, 500);
                }
            }
            
            // Start checking after a short delay
            setTimeout(checkReactApp, 100);
        </script>';
        
        // Loading styles
        echo '<style>
            .ccc-loading-container {
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 400px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 12px;
                margin: 20px 0;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            }
            
            .ccc-loading-content {
                text-align: center;
                color: white;
                padding: 40px;
            }
            
            .ccc-loading-spinner {
                width: 60px;
                height: 60px;
                border: 4px solid rgba(255,255,255,0.3);
                border-top: 4px solid white;
                border-radius: 50%;
                animation: ccc-spin 1s linear infinite;
                margin: 0 auto 20px;
            }
            
            .ccc-loading-title {
                font-size: 24px;
                font-weight: 600;
                margin: 0 0 10px 0;
                text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }
            
            .ccc-loading-subtitle {
                font-size: 16px;
                margin: 0;
                opacity: 0.9;
                text-shadow: 0 1px 2px rgba(0,0,0,0.2);
            }
            
            @keyframes ccc-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>';
        
        echo '</div>';
    }



    public function renderSettingsPage() {
        error_log("CCC AdminManager: Rendering Settings page");
        echo '<div class="">';
        echo '<div id="root" data-page="settings"></div>';
        
        // Beautiful loading state
        echo '<div id="loading-state" class="ccc-loading-container">';
        echo '<div class="ccc-loading-content">';
        echo '<div class="ccc-loading-spinner"></div>';
        echo '<h2 class="ccc-loading-title">Loading Settings Manager</h2>';
        echo '<p class="ccc-loading-subtitle">Please wait while we prepare your settings...</p>';
        echo '</div>';
        echo '</div>';
        
        echo '<script>
            console.log("CCC: Settings page rendered, root element:", document.getElementById("root"));
            
            // Show loading state immediately
            document.getElementById("loading-state").style.display = "flex";
            
            // Check if React app loads successfully
            function checkReactApp() {
                const root = document.querySelector("#root");
                if (root && root.children.length > 0) {
                    // React app loaded successfully, hide loading state
                    document.getElementById("loading-state").style.display = "none";
                    console.log("CCC: React app loaded successfully");
                } else {
                    // Check again in 500ms
                    setTimeout(checkReactApp, 500);
                }
            }
            
            // Start checking after a short delay
            setTimeout(checkReactApp, 100);
        </script>';
        
        // Loading styles
        echo '<style>
            .ccc-loading-container {
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 400px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 12px;
                margin: 20px 0;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            }
            
            .ccc-loading-content {
                text-align: center;
                color: white;
                padding: 40px;
            }
            
            .ccc-loading-spinner {
                width: 60px;
                height: 60px;
                border: 4px solid rgba(255,255,255,0.3);
                border-top: 4px solid white;
                border-radius: 50%;
                animation: ccc-spin 1s linear infinite;
                margin: 0 auto 20px;
            }
            
            .ccc-loading-title {
                font-size: 24px;
                font-weight: 600;
                margin: 0 0 10px 0;
                text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }
            
            .ccc-loading-subtitle {
                font-size: 16px;
                margin: 0;
                opacity: 0.9;
                text-shadow: 0 1px 2px rgba(0,0,0,0.2);
            }
            
            @keyframes ccc-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>';
        
        echo '</div>';
    }
}
