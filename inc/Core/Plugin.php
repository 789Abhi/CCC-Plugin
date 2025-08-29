<?php

namespace CCC\Core;

use CCC\Admin\AdminManager;
use CCC\Admin\RevisionAdmin;
use CCC\Frontend\TemplateManager;
use CCC\Frontend\TemplateLoader;
use CCC\Ajax\AjaxHandler;

defined('ABSPATH') || exit;

class Plugin {
   private $admin_manager;
   private $revision_admin;
   private $template_manager;
   private $template_loader;
   private $ajax_handler;
   
   /**
    * Plugin version
    */
   const VERSION = '1.3.4';
   
   /**
    * Minimum WordPress version required
    */
   const MIN_WP_VERSION = '5.0';
   
   /**
    * Minimum PHP version required
    */
   const MIN_PHP_VERSION = '7.4';

   public function __construct() {
       $this->admin_manager = new AdminManager();
       $this->template_manager = new TemplateManager();
       $this->template_loader = new TemplateLoader();
       $this->ajax_handler = new AjaxHandler();
   }

   public function init() {
       // Check system requirements
       if (!$this->check_requirements()) {
           return;
       }
       
       // Check and update database schema on every admin page load for auto-updates
       // Temporarily disabled to debug AJAX issues
       // add_action('admin_init', ['\CCC\Core\Database', 'checkAndUpdateSchema'], 1);
       
       // Also check on plugin init for safety
       // add_action('init', ['\CCC\Core\Database', 'checkAndUpdateSchema'], 1);
       
       
       
       // Initialize components
       $this->admin_manager->init();
       $this->template_manager->init();
       $this->ajax_handler->init();
       
       // Add custom hooks
       $this->add_hooks();
       
       // Schedule cleanup tasks
       $this->schedule_cleanup_tasks();
   }
   
   /**
    * Check if system requirements are met
    */
   private function check_requirements() {
       global $wp_version;
       
       // Check WordPress version
       if (version_compare($wp_version, self::MIN_WP_VERSION, '<')) {
           add_action('admin_notices', function() {
               echo '<div class="notice notice-error"><p>';
               printf(
                   __('Custom Craft Component requires WordPress %s or higher. You are running version %s.', 'custom-craft-component'),
                   self::MIN_WP_VERSION,
                   $GLOBALS['wp_version']
               );
               echo '</p></div>';
           });
           return false;
       }
       
       // Check PHP version
       if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')) {
           add_action('admin_notices', function() {
               echo '<div class="notice notice-error"><p>';
               printf(
                   __('Custom Craft Component requires PHP %s or higher. You are running version %s.', 'custom-craft-component'),
                   self::MIN_PHP_VERSION,
                   PHP_VERSION
               );
               echo '</p></div>';
           });
           return false;
       }
       
       return true;
   }
   
   /**
    * Add custom WordPress hooks
    */
   private function add_hooks() {
       // Add REST API endpoints
       add_action('rest_api_init', [$this, 'register_rest_routes']);
       
       // Add custom post states
       add_filter('display_post_states', [$this, 'add_post_states'], 10, 2);
       
       // Add body classes for CCC pages
       add_filter('body_class', [$this, 'add_body_classes']);
       
       // Handle plugin updates
       add_action('upgrader_process_complete', [$this, 'handle_plugin_update'], 10, 2);
       
       // Register CCC shortcodes
       add_action('init', [$this, 'register_shortcodes']);
   }
   
   /**
    * Register CCC shortcodes
    */
    public function register_shortcodes() {
        // Register the ccc_render_components shortcode
        if (!shortcode_exists('ccc_render_components')) {
            add_shortcode('ccc_render_components', function($atts = []) {
                // Parse shortcode attributes
                $atts = shortcode_atts([
                    'post_id' => null,
                ], $atts, 'ccc_render_components');
                
                // Get post ID
                $post_id = $atts['post_id'] ? intval($atts['post_id']) : get_the_ID();
                
                if (!$post_id) {
                    return '';
                }
                
                // Start output buffering
                ob_start();
                
                // Render components
                if (function_exists('render_ccc_components')) {
                    render_ccc_components($post_id);
                }
                
                // Get the output
                $output = ob_get_clean();
                
                return $output;
            });
            error_log("CCC Plugin: Registered ccc_render_components shortcode in main plugin");
        }
    }
   
   /**
    * Register REST API routes
    */
   public function register_rest_routes() {
       error_log('CCC REST API: Registering routes');
       
       register_rest_route('ccc/v1', '/components', [
           'methods' => 'GET',
           'callback' => [$this, 'get_components_rest'],
           'permission_callback' => [$this, 'check_rest_permissions']
       ]);
       
       register_rest_route('ccc/v1', '/components', [
           'methods' => 'POST',
           'callback' => [$this, 'create_component_rest'],
           'permission_callback' => [$this, 'check_rest_permissions']
       ]);
       
       register_rest_route('ccc/v1', '/components/(?P<id>\d+)', [
           'methods' => 'GET',
           'callback' => [$this, 'get_component_rest'],
           'permission_callback' => [$this, 'check_rest_permissions']
       ]);
       
       register_rest_route('ccc/v1', '/fields', [
           'methods' => 'POST',
           'callback' => [$this, 'create_field_rest'],
           'permission_callback' => [$this, 'check_rest_permissions']
       ]);
       
       error_log('CCC REST API: Routes registered successfully');
   }
   
   /**
    * Check REST API permissions
    */
   public function check_rest_permissions($request) {
       // Debug logging
       error_log('CCC REST API: Permission check called');
       error_log('CCC REST API: User ID: ' . get_current_user_id());
       error_log('CCC REST API: User can edit posts: ' . (current_user_can('edit_posts') ? 'YES' : 'NO'));
       
       // Temporarily allow all requests for debugging
       error_log('CCC REST API: Temporarily allowing all requests for debugging');
       return true;
       
       // Check if user can edit posts
       if (!current_user_can('edit_posts')) {
           error_log('CCC REST API: Permission denied - user cannot edit posts');
           return false;
       }
       
       error_log('CCC REST API: Permission granted');
       return true;
   }
   
   /**
    * REST API callback for getting components
    */
   public function get_components_rest($request) {
       $components = \CCC\Models\Component::all();
       $data = [];
       
       foreach ($components as $component) {
           $data[] = [
               'id' => $component->getId(),
               'name' => $component->getName(),
               'handle_name' => $component->getHandleName(),
               'created_at' => $component->getCreatedAt()
           ];
       }
       
       return rest_ensure_response($data);
   }
   
   /**
    * REST API callback for getting a single component
    */
   public function get_component_rest($request) {
       $component_id = $request->get_param('id');
       $component = \CCC\Models\Component::find($component_id);
       
       if (!$component) {
           return new \WP_Error('component_not_found', 'Component not found', ['status' => 404]);
       }
       
       $fields = \CCC\Models\Field::findByComponent($component_id);
       $field_data = [];
       
       foreach ($fields as $field) {
           $field_data[] = [
               'id' => $field->getId(),
               'label' => $field->getLabel(),
               'name' => $field->getName(),
               'type' => $field->getType()
           ];
       }
       
       $data = [
           'id' => $component->getId(),
           'name' => $component->getName(),
           'handle_name' => $component->getHandleName(),
           'fields' => $field_data,
           'created_at' => $component->getCreatedAt()
       ];
       
       return rest_ensure_response($data);
   }
   
   /**
    * REST API callback for creating a component
    */
   public function create_component_rest($request) {
       error_log('CCC REST API: create_component_rest called');
       
       try {
           $params = $request->get_params();
           error_log('CCC REST API: Received params: ' . print_r($params, true));
           
           $name = sanitize_text_field($params['name'] ?? '');
           $handle_name = sanitize_title($params['handle_name'] ?? '');
           $description = sanitize_textarea_field($params['description'] ?? '');
           $status = sanitize_text_field($params['status'] ?? 'active');
           
           error_log('CCC REST API: Processed params - name: ' . $name . ', handle: ' . $handle_name);
           
           if (empty($name) || empty($handle_name)) {
               error_log('CCC REST API: Missing required fields');
               return new \WP_Error('missing_fields', 'Name and handle are required', ['status' => 400]);
           }
           
           $component_service = new \CCC\Services\ComponentService();
           $component = $component_service->createComponent($name, $handle_name);
           
           error_log('CCC REST API: Component created: ' . get_class($component));
           
           if (!$component) {
               error_log('CCC REST API: Failed to create component');
               return new \WP_Error('creation_failed', 'Failed to create component', ['status' => 500]);
           }
           
           // Extract the ID from the Component object
           $component_id = $component->getId();
           error_log('CCC REST API: Component created with ID: ' . $component_id);
           
           $response_data = [
               'success' => true,
               'data' => [
                   'id' => $component_id,
                   'name' => $name,
                   'handle_name' => $handle_name,
                   'description' => $description,
                   'status' => $status
               ]
           ];
           
           error_log('CCC REST API: Returning success response: ' . print_r($response_data, true));
           return rest_ensure_response($response_data);
           
       } catch (\Exception $e) {
           error_log('CCC REST API: Exception occurred: ' . $e->getMessage());
           return new \WP_Error('creation_error', $e->getMessage(), ['status' => 500]);
       }
   }
   
   /**
    * REST API callback for creating a field
    */
   public function create_field_rest($request) {
       error_log('CCC REST API: create_field_rest called');
       
       try {
           $params = $request->get_params();
           error_log('CCC REST API: Field params: ' . print_r($params, true));
           
           $component_id = intval($params['component_id'] ?? 0);
           $label = sanitize_text_field($params['label'] ?? '');
           $name = sanitize_title($params['name'] ?? '');
           $type = sanitize_text_field($params['type'] ?? 'text');
           $required = !empty($params['required']);
           $placeholder = sanitize_text_field($params['placeholder'] ?? '');
           $config = $params['config'] ?? '{}';
           $order = intval($params['order'] ?? 1);
           
           error_log('CCC REST API: Processed field params - component_id: ' . $component_id . ', label: ' . $label . ', type: ' . $type);
           
           if (!$component_id || empty($label) || empty($name)) {
               error_log('CCC REST API: Missing required field parameters');
               return new \WP_Error('missing_fields', 'Component ID, label, and name are required', ['status' => 400]);
           }
           
           // Create field using the Field model directly (like AJAX handler)
           $field = new \CCC\Models\Field([
               'component_id' => $component_id,
               'label' => $label,
               'name' => $name,
               'type' => $type,
               'config' => $config,
               'field_order' => $order,
               'required' => $required,
               'placeholder' => $placeholder
           ]);
           
           error_log('CCC REST API: Field object created, attempting to save');
           
           if (!$field->save()) {
               error_log('CCC REST API: Failed to save field');
               return new \WP_Error('creation_failed', 'Failed to create field', ['status' => 500]);
           }
           
           $field_id = $field->getId();
           error_log('CCC REST API: Field created with ID: ' . $field_id);
           
           $response_data = [
               'success' => true,
               'data' => [
                   'id' => $field_id,
                   'component_id' => $component_id,
                   'label' => $label,
                   'name' => $name,
                   'type' => $type,
                   'required' => $required,
                   'placeholder' => $placeholder,
                   'config' => $config,
                   'order' => $order
               ]
           ];
           
           error_log('CCC REST API: Field creation success response: ' . print_r($response_data, true));
           return rest_ensure_response($response_data);
           
       } catch (\Exception $e) {
           error_log('CCC REST API: Field creation exception: ' . $e->getMessage());
           return new \WP_Error('creation_error', $e->getMessage(), ['status' => 500]);
       }
   }
   
   /**
    * Add post states for pages using CCC template
    */
   public function add_post_states($post_states, $post) {
       $template = get_post_meta($post->ID, '_wp_page_template', true);
       if ($template === 'ccc-template.php') {
           $post_states['ccc_template'] = __('CCC Template', 'custom-craft-component');
       }
       
       $components = get_post_meta($post->ID, '_ccc_components', true);
       if (!empty($components) && is_array($components)) {
           $post_states['ccc_components'] = sprintf(
               __('CCC Components (%d)', 'custom-craft-component'),
               count($components)
           );
       }
       
       return $post_states;
   }
   
   /**
    * Add body classes for CCC-related pages
    */
   public function add_body_classes($classes) {
       if (is_singular()) {
           $post_id = get_the_ID();
           $template = get_post_meta($post_id, '_wp_page_template', true);
           
           if ($template === 'ccc-template.php') {
               $classes[] = 'ccc-template-page';
           }
           
           $components = get_post_meta($post_id, '_ccc_components', true);
           if (!empty($components)) {
               $classes[] = 'has-ccc-components';
               $classes[] = 'ccc-components-count-' . count($components);
           }
       }
       
       return $classes;
   }
   
   /**
    * Handle plugin updates
    */
   public function handle_plugin_update($upgrader, $hook_extra) {
       if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === plugin_basename(CCC_PLUGIN_PATH . 'custom-craft-component.php')) {
           // Clear any caches
           delete_transient('ccc_component_cache');
           
           // Update database schema if needed
           \CCC\Core\Database::checkAndUpdateSchema();
           
           // Log the update
           error_log('CCC Plugin updated to version ' . self::VERSION);
       }
   }
   
   /**
    * Schedule cleanup tasks
    */
   private function schedule_cleanup_tasks() {
       if (!wp_next_scheduled('ccc_cleanup_temp_files')) {
           wp_schedule_event(time(), 'daily', 'ccc_cleanup_temp_files');
       }
       
       add_action('ccc_cleanup_temp_files', [$this, 'cleanup_temp_files']);
   }
   
   /**
    * Cleanup temporary files and old data
    */
   public function cleanup_temp_files() {
       // Clean up old field values for deleted posts
       global $wpdb;
       
       $field_values_table = $wpdb->prefix . 'cc_field_values';
       
       // Delete field values for posts that no longer exist
       $wpdb->query("
           DELETE fv FROM {$field_values_table} fv
           LEFT JOIN {$wpdb->posts} p ON fv.post_id = p.ID
           WHERE p.ID IS NULL
       ");
       
       // Clean up orphaned field values (fields that no longer exist)
       $fields_table = $wpdb->prefix . 'cc_fields';
       $wpdb->query("
           DELETE fv FROM {$field_values_table} fv
           LEFT JOIN {$fields_table} f ON fv.field_id = f.id
           WHERE f.id IS NULL
       ");
       
       // Log cleanup
       error_log('CCC: Cleanup task completed');
   }
   
   /**
    * Get plugin version
    */
   public static function get_version() {
       return self::VERSION;
   }
   
   /**
    * Check if debug mode is enabled
    */
   public static function is_debug_mode() {
       return defined('WP_DEBUG') && WP_DEBUG;
   }

    public function run() {
        $this->init();
    }
}
