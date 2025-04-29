<?php
/**
 * Handle plugin updates from GitHub
 */
class CCC_Updater {
    private $slug;
    private $plugin_data;
    private $github_username;
    private $github_repo;
    private $plugin_file;
    private $transient_cache_key = 'ccc_plugin_update_data';

    public function __construct() {
        $this->plugin_file = 'custom-craft-component/custom-craft-component.php';
        $this->slug = dirname($this->plugin_file);
        $this->github_username = '789Abhi';
        $this->github_repo = 'CCC-Plugin';
    }

    public function init() {
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
        
        // Clear transient cache when visiting plugin page
        add_action('admin_init', array($this, 'clear_transient_on_plugin_page'));
    }
    
    public function clear_transient_on_plugin_page() {
        if (isset($_GET['page']) && $_GET['page'] == 'ccc-plugin-settings') {
            delete_transient($this->transient_cache_key);
        }
    }

    private function get_repository_info() {
        if (false === ($response = get_transient($this->transient_cache_key))) {
            // Get info from GitHub
            $request_uri = sprintf('https://raw.githubusercontent.com/789Abhi/CCC-Plugin/Master/manifest.json', 
                $this->github_username, $this->github_repo);
                
            $response = json_decode(wp_remote_retrieve_body(wp_remote_get($request_uri)), true);
            
            if (is_array($response)) {
                set_transient($this->transient_cache_key, $response, 12 * HOUR_IN_SECONDS);
            }
        }
        return $response;
    }

    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $this->plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $this->plugin_file);
        $remote_version_info = $this->get_repository_info();
        
        if (empty($remote_version_info)) {
            return $transient;
        }

        // Check if version is newer
        if (version_compare($this->plugin_data['Version'], $remote_version_info['version'], '<')) {
            $download_url = sprintf('https://github.com/%s/%s/archive/refs/heads/Master.zip', 
                $this->github_username, $this->github_repo);
                
            $transient->response[$this->plugin_file] = (object) array(
                'slug' => $this->slug,
                'new_version' => $remote_version_info['version'],
                'package' => $download_url,
                'tested' => '6.2',
                'icons' => array(
                    '1x' => CCC_PLUGIN_URL . 'assets/icon-128x128.png',
                    '2x' => CCC_PLUGIN_URL . 'assets/icon-256x256.png'
                ),
                'banners' => array(
                    'low' => CCC_PLUGIN_URL . 'assets/banner-772x250.jpg',
                    'high' => CCC_PLUGIN_URL . 'assets/banner-1544x500.jpg'
                )
            );
        }

        return $transient;
    }

    public function plugin_popup($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== $this->slug) {
            return $result;
        }

        $this->plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $this->plugin_file);
        $remote_version_info = $this->get_repository_info();

        $plugin_info = (object) array(
            'name' => $this->plugin_data['Name'],
            'slug' => $this->slug,
            'version' => $remote_version_info['version'],
            'author' => $this->plugin_data['Author'],
            'author_profile' => $this->plugin_data['AuthorURI'],
            'requires' => '5.6',
            'tested' => '6.2',
            'last_updated' => $remote_version_info['updated_at'],
            'sections' => array(
                'description' => $this->plugin_data['Description'],
                'changelog' => '<h4>1.0.0</h4><p>Initial release</p>'
            ),
            'download_link' => sprintf('c', 
                $this->github_username, $this->github_repo)
        );

        return $plugin_info;
    }

    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;

        // Move files to the correct plugin directory
        $install_directory = plugin_dir_path(WP_PLUGIN_DIR . '/' . $this->plugin_file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;

        // Activate plugin
        if (current_user_can('activate_plugins') && is_plugin_inactive($this->plugin_file)) {
            activate_plugin($this->plugin_file);
        }

        return $result;
    }
}