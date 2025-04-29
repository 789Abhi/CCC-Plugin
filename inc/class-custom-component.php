<?php
/**
 * The main plugin class
 */
class CCC_Plugin {
    public function run() {
        // Register scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('admin_enqueue_scripts', array($this, 'register_admin_assets'));
        
        // Add shortcode for frontend display
        add_shortcode('ccc_frontend', array($this, 'render_frontend'));
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    public function register_assets() {
        // Get the manifest file
        $manifest_path = CCC_PLUGIN_PATH . 'manifest.json';
        
        if (file_exists($manifest_path)) {
            $manifest = json_decode(file_get_contents($manifest_path), true);
            
            if (isset($manifest['files']['main.css'])) {
                wp_enqueue_style(
                    'ccc-frontend-styles',
                    CCC_PLUGIN_URL . 'assets/' . $manifest['files']['main.css'],
                    array(),
                    CCC_PLUGIN_VERSION
                );
            }
            
            if (isset($manifest['files']['main.js'])) {
                wp_enqueue_script(
                    'ccc-frontend-script',
                    CCC_PLUGIN_URL . 'assets/' . $manifest['files']['main.js'],
                    array('wp-element'),
                    CCC_PLUGIN_VERSION,
                    true
                );
                
                // Pass data to the script
                wp_localize_script(
                    'ccc-frontend-script',
                    'cccPluginData',
                    array(
                        'apiUrl' => rest_url('ccc-plugin/v1'),
                        'nonce' => wp_create_nonce('wp_rest')
                    )
                );
            }
        }
    }
    
    public function register_admin_assets($hook) {
        if ('toplevel_page_ccc-plugin-settings' !== $hook) {
            return;
        }
        
        $this->register_assets();
    }
    
    public function render_frontend() {
        ob_start();
        ?>
        <div id="ccc-frontend-app"></div>
        <?php
        return ob_get_clean();
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'CCC Plugin',
            'CCC Plugin',
            'manage_options',
            'ccc-plugin-settings',
            array($this, 'render_admin_page'),
            'dashicons-admin-generic',
            100
        );
    }
    
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>CCC Plugin Settings</h1>
            <div id="ccc-frontend-app"></div>
        </div>
        <?php
    }
}