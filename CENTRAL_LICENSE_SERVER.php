<?php
/**
 * CENTRAL LICENSE SERVER - Install on your main website
 * 
 * This file should be placed in your main website's plugin directory
 * to receive reports from all Custom Craft Component installations worldwide.
 * 
 * File: /wp-content/plugins/ccc-central-license-server/ccc-central-license-server.php
 */

/*
Plugin Name: CCC Central License Server
Description: Central server for tracking all Custom Craft Component plugin installations worldwide
Version: 1.0
Author: Abhishek
*/

defined('ABSPATH') || exit;

class CCC_Central_License_Server {
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action('init', [$this, 'createTables']);
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'handleReports']);
    }
    
    /**
     * Create necessary database tables
     */
    public function createTables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for all plugin installations worldwide
        $table_installations = $wpdb->prefix . 'ccc_worldwide_installations';
        $sql_installations = "CREATE TABLE $table_installations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            domain varchar(255) NOT NULL,
            site_name varchar(255),
            site_description text,
            admin_email varchar(255),
            installation_date datetime DEFAULT CURRENT_TIMESTAMP,
            last_activity datetime DEFAULT CURRENT_TIMESTAMP,
            plugin_version varchar(50),
            wordpress_version varchar(50),
            site_url text,
            status enum('active', 'inactive', 'deactivated') DEFAULT 'active',
            active_users_count int DEFAULT 0,
            components_count int DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY domain (domain)
        ) $charset_collate;";
        
        // Table for all users worldwide
        $table_users = $wpdb->prefix . 'ccc_worldwide_users';
        $sql_users = "CREATE TABLE $table_users (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            domain varchar(255) NOT NULL,
            user_id bigint(20),
            user_email varchar(255) NOT NULL,
            user_phone varchar(100),
            user_role enum('user', 'admin', 'super_admin') DEFAULT 'user',
            registration_date datetime DEFAULT CURRENT_TIMESTAMP,
            status enum('active', 'pending', 'inactive') DEFAULT 'active',
            PRIMARY KEY (id),
            KEY domain (domain),
            KEY user_email (user_email)
        ) $charset_collate;";
        
        // Table for license requests worldwide
        $table_licenses = $wpdb->prefix . 'ccc_worldwide_licenses';
        $sql_licenses = "CREATE TABLE $table_licenses (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            domain varchar(255) NOT NULL,
            user_email varchar(255) NOT NULL,
            license_key varchar(100) NOT NULL,
            proxy_api_key varchar(100) NOT NULL,
            status enum('pending', 'approved', 'rejected', 'expired') DEFAULT 'pending',
            request_date datetime DEFAULT CURRENT_TIMESTAMP,
            approval_date datetime NULL,
            expires_date datetime NULL,
            PRIMARY KEY (id),
            KEY domain (domain),
            KEY user_email (user_email),
            KEY license_key (license_key)
        ) $charset_collate;";
        
        // Table for activity logs
        $table_logs = $wpdb->prefix . 'ccc_worldwide_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            domain varchar(255) NOT NULL,
            action varchar(100) NOT NULL,
            details text,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY domain (domain),
            KEY action (action),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_installations);
        dbDelta($sql_users);
        dbDelta($sql_licenses);
        dbDelta($sql_logs);
    }
    
    /**
     * Register REST API routes
     */
    public function registerRoutes() {
        register_rest_route('ccc-central/v1', '/report', [
            'methods' => 'POST',
            'callback' => [$this, 'handleReport'],
            'permission_callback' => [$this, 'checkReportPermissions']
        ]);
        
        register_rest_route('ccc-central/v1', '/test', [
            'methods' => 'POST',
            'callback' => [$this, 'handleTest'],
            'permission_callback' => [$this, 'checkReportPermissions']
        ]);
    }
    
    /**
     * Check if the reporting request is allowed
     */
    private function checkReportPermissions($request) {
        // Allow reports from any domain (for data collection)
        // But validate the request format and domain
        $params = $request->get_params();
        $domain = sanitize_text_field($params['domain'] ?? '');
        
        // Basic validation - domain must be present and valid
        if (empty($domain) || !filter_var('http://' . $domain, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Handle incoming reports from plugin installations
     */
    public function handleReport($request) {
        $params = $request->get_params();
        $action = sanitize_text_field($params['action'] ?? '');
        $domain = sanitize_text_field($params['domain'] ?? '');
        
        if (empty($action) || empty($domain)) {
            return new WP_Error('missing_data', 'Missing action or domain', ['status' => 400]);
        }
        
        switch ($action) {
            case 'report_user_registration':
                return $this->handleUserRegistration($params);
                
            case 'report_plugin_installation':
                return $this->handlePluginInstallation($params);
                
            case 'report_plugin_deactivation':
                return $this->handlePluginDeactivation($params);
                
            case 'health_check':
                return $this->handleHealthCheck($params);
                
            default:
                return new WP_Error('invalid_action', 'Invalid action', ['status' => 400]);
        }
    }
    
    /**
     * Handle user registration report
     */
    private function handleUserRegistration($params) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'ccc_worldwide_users';
        $table_logs = $wpdb->prefix . 'ccc_worldwide_logs';
        
        $domain = sanitize_text_field($params['domain']);
        $user_email = sanitize_email($params['user_email']);
        $user_phone = sanitize_text_field($params['user_phone']);
        $user_role = sanitize_text_field($params['user_role']);
        $registration_date = sanitize_text_field($params['registration_date']);
        
        // Check if user already exists for this domain
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_users WHERE domain = %s AND user_email = %s",
            $domain, $user_email
        ));
        
        if ($existing) {
            // Update existing user
            $wpdb->update(
                $table_users,
                [
                    'user_phone' => $user_phone,
                    'user_role' => $user_role,
                    'status' => 'active',
                    'registration_date' => $registration_date
                ],
                ['id' => $existing->id]
            );
        } else {
            // Create new user
            $wpdb->insert(
                $table_users,
                [
                    'domain' => $domain,
                    'user_id' => intval($params['user_id']),
                    'user_email' => $user_email,
                    'user_phone' => $user_phone,
                    'user_role' => $user_role,
                    'registration_date' => $registration_date,
                    'status' => 'active'
                ]
            );
        }
        
        // Log the activity
        $wpdb->insert(
            $table_logs,
            [
                'domain' => $domain,
                'action' => 'user_registration',
                'details' => "User registered: $user_email ($user_role)"
            ]
        );
        
        return [
            'success' => true,
            'message' => 'User registration recorded successfully'
        ];
    }
    
    /**
     * Handle plugin installation report
     */
    private function handlePluginInstallation($params) {
        global $wpdb;
        
        $table_installations = $wpdb->prefix . 'ccc_worldwide_installations';
        $table_logs = $wpdb->prefix . 'ccc_worldwide_logs';
        
        $domain = sanitize_text_field($params['domain']);
        $site_name = sanitize_text_field($params['site_name']);
        $site_description = sanitize_textarea_field($params['site_description']);
        $admin_email = sanitize_email($params['admin_email']);
        $installation_date = sanitize_text_field($params['installation_date']);
        $plugin_version = sanitize_text_field($params['plugin_version']);
        $wordpress_version = sanitize_text_field($params['wordpress_version']);
        $site_url = esc_url_raw($params['site_url']);
        
        // Check if installation already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_installations WHERE domain = %s",
            $domain
        ));
        
        if ($existing) {
            // Update existing installation
            $wpdb->update(
                $table_installations,
                [
                    'site_name' => $site_name,
                    'site_description' => $site_description,
                    'admin_email' => $admin_email,
                    'last_activity' => current_time('mysql'),
                    'plugin_version' => $plugin_version,
                    'wordpress_version' => $wordpress_version,
                    'site_url' => $site_url,
                    'status' => 'active'
                ],
                ['domain' => $domain]
            );
        } else {
            // Create new installation record
            $wpdb->insert(
                $table_installations,
                [
                    'domain' => $domain,
                    'site_name' => $site_name,
                    'site_description' => $site_description,
                    'admin_email' => $admin_email,
                    'installation_date' => $installation_date,
                    'last_activity' => current_time('mysql'),
                    'plugin_version' => $plugin_version,
                    'wordpress_version' => $wordpress_version,
                    'site_url' => $site_url,
                    'status' => 'active'
                ]
            );
        }
        
        // Log the activity
        $wpdb->insert(
            $table_logs,
            [
                'domain' => $domain,
                'action' => 'plugin_installation',
                'details' => "Plugin installed: v$plugin_version on WordPress v$wordpress_version"
            ]
        );
        
        return [
            'success' => true,
            'message' => 'Plugin installation recorded successfully'
        ];
    }
    
    /**
     * Handle plugin deactivation report
     */
    private function handlePluginDeactivation($params) {
        global $wpdb;
        
        $table_installations = $wpdb->prefix . 'ccc_worldwide_installations';
        $table_logs = $wpdb->prefix . 'ccc_worldwide_logs';
        
        $domain = sanitize_text_field($params['domain']);
        $deactivation_date = sanitize_text_field($params['deactivation_date']);
        
        // Update installation status
        $wpdb->update(
            $table_installations,
            [
                'status' => 'deactivated',
                'last_activity' => $deactivation_date
            ],
            ['domain' => $domain]
        );
        
        // Log the activity
        $wpdb->insert(
            $table_logs,
            [
                'domain' => $domain,
                'action' => 'plugin_deactivation',
                'details' => "Plugin deactivated on $deactivation_date"
            ]
        );
        
        return [
            'success' => true,
            'message' => 'Plugin deactivation recorded successfully'
        ];
    }
    
    /**
     * Handle health check report
     */
    private function handleHealthCheck($params) {
        global $wpdb;
        
        $table_installations = $wpdb->prefix . 'ccc_worldwide_installations';
        $table_logs = $wpdb->prefix . 'ccc_worldwide_logs';
        
        $domain = sanitize_text_field($params['domain']);
        $active_users_count = intval($params['active_users_count']);
        $components_count = intval($params['components_count']);
        $last_activity = sanitize_text_field($params['last_activity']);
        
        // Update installation with health data
        $wpdb->update(
            $table_installations,
            [
                'last_activity' => $last_activity,
                'active_users_count' => $active_users_count,
                'components_count' => $components_count
            ],
            ['domain' => $domain]
        );
        
        // Log the activity
        $wpdb->insert(
            $table_logs,
            [
                'domain' => $domain,
                'action' => 'health_check',
                'details' => "Health check: $active_users_count users, $components_count components"
            ]
        );
        
        return [
            'success' => true,
            'message' => 'Health check recorded successfully'
        ];
    }
    
    /**
     * Handle test connection
     */
    public function handleTest($request) {
        $params = $request->get_params();
        $domain = sanitize_text_field($params['domain'] ?? '');
        
        return [
            'success' => true,
            'message' => "Connection test successful from domain: $domain"
        ];
    }
    
    /**
     * Add admin menu
     */
    public function addAdminMenu() {
        // Only show to users with manage_options capability (admin/super_admin)
        if (current_user_can('manage_options')) {
            add_menu_page(
                'Worldwide Installations',
                'CCC Worldwide',
                'manage_options',
                'ccc-worldwide',
                [$this, 'renderAdminPage'],
                'dashicons-admin-site',
                30
            );
        }
    }
    
    /**
     * Render admin page
     */
    public function renderAdminPage() {
        // Double-check user permissions
        if (!current_user_can('manage_options')) {
            wp_die('Access denied. You do not have permission to view this page.');
        }
        
        global $wpdb;
        
        $table_installations = $wpdb->prefix . 'ccc_worldwide_installations';
        $table_users = $wpdb->prefix . 'ccc_worldwide_users';
        $table_licenses = $wpdb->prefix . 'ccc_worldwide_licenses';
        
        // Get statistics
        $total_installations = $wpdb->get_var("SELECT COUNT(*) FROM $table_installations WHERE status = 'active'");
        $total_users = $wpdb->get_var("SELECT COUNT(*) FROM $table_users WHERE status = 'active'");
        $pending_licenses = $wpdb->get_var("SELECT COUNT(*) FROM $table_licenses WHERE status = 'pending'");
        $active_licenses = $wpdb->get_var("SELECT COUNT(*) FROM $table_licenses WHERE status = 'approved'");
        
        // Get recent installations
        $recent_installations = $wpdb->get_results("
            SELECT * FROM $table_installations 
            WHERE status = 'active' 
            ORDER BY last_activity DESC 
            LIMIT 10
        ");
        
        // Get recent users
        $recent_users = $wpdb->get_results("
            SELECT * FROM $table_users 
            WHERE status = 'active' 
            ORDER BY registration_date DESC 
            LIMIT 10
        ");
        
        ?>
        <div class="wrap">
            <h1>🌍 CCC Worldwide Installations Dashboard</h1>
            
            <!-- Statistics Overview -->
            <div class="card" style="margin-bottom: 20px;">
                <h2>📊 Global Statistics</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div style="text-align: center; padding: 20px; background: #f0f8ff; border-radius: 8px;">
                        <h3 style="margin: 0; color: #0066cc;"><?php echo $total_installations; ?></h3>
                        <p style="margin: 5px 0 0 0;">Active Installations</p>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #fff0f0; border-radius: 8px;">
                        <h3 style="margin: 0; color: #cc0000;"><?php echo $total_users; ?></h3>
                        <p style="margin: 5px 0 0 0;">Total Users</p>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #fff8f0; border-radius: 8px;">
                        <h3 style="margin: 0; color: #cc6600;"><?php echo $pending_licenses; ?></h3>
                        <p style="margin: 5px 0 0 0;">Pending Licenses</p>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #f0fff0; border-radius: 8px;">
                        <h3 style="margin: 0; color: #006600;"><?php echo $active_licenses; ?></h3>
                        <p style="margin: 5px 0 0 0;">Active Licenses</p>
                    </div>
                </div>
            </div>
            
            <!-- Recent Installations -->
            <div class="card" style="margin-bottom: 20px;">
                <h2>🆕 Recent Installations</h2>
                <?php if ($recent_installations): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th>Site Name</th>
                                <th>Admin Email</th>
                                <th>Plugin Version</th>
                                <th>Last Activity</th>
                                <th>Users</th>
                                <th>Components</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_installations as $installation): ?>
                                <tr>
                                    <td><?php echo esc_html($installation->domain); ?></td>
                                    <td><?php echo esc_html($installation->site_name); ?></td>
                                    <td><?php echo esc_html($installation->admin_email); ?></td>
                                    <td><?php echo esc_html($installation->plugin_version); ?></td>
                                    <td><?php echo esc_html(date('M j, Y H:i', strtotime($installation->last_activity))); ?></td>
                                    <td><?php echo esc_html($installation->active_users_count); ?></td>
                                    <td><?php echo esc_html($installation->components_count); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No installations found.</p>
                <?php endif; ?>
            </div>
            
            <!-- Recent Users -->
            <div class="card">
                <h2>👥 Recent Users</h2>
                <?php if ($recent_users): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Registration Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $user): ?>
                                <tr>
                                    <td><?php echo esc_html($user->domain); ?></td>
                                    <td><?php echo esc_html($user->user_email); ?></td>
                                    <td><?php echo esc_html($user->user_phone); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo esc_attr($user->user_role); ?>">
                                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $user->user_role))); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html(date('M j, Y', strtotime($user->registration_date))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No users found.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .role-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .role-user { background: #e1f5fe; color: #0277bd; }
        .role-admin { background: #fff3e0; color: #ef6c00; }
        .role-super_admin { background: #f3e5f5; color: #7b1fa2; }
        </style>
        <?php
    }
}

// Initialize the plugin
new CCC_Central_License_Server();
