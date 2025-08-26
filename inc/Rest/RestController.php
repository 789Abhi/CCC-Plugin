<?php

namespace CCC\Rest;

class RestController {
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }
    
    public function registerRoutes() {
        register_rest_route('ccc/v1', '/register', [
            'methods' => 'POST',
            'callback' => [$this, 'handleRegister'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route('ccc/v1', '/login', [
            'methods' => 'POST',
            'callback' => [$this, 'handleLogin'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route('ccc/v1', '/logout', [
            'methods' => 'POST',
            'callback' => [$this, 'handleLogout'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route('ccc/v1', '/check-auth', [
            'methods' => 'GET',
            'callback' => [$this, 'checkAuth'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route('ccc/v1', '/request-license', [
            'methods' => 'POST',
            'callback' => [$this, 'requestLicense'],
            'permission_callback' => [$this, 'checkUserAuth']
        ]);
        
        register_rest_route('ccc/v1', '/installations', [
            'methods' => 'GET',
            'callback' => [$this, 'getAllInstallations'],
            'permission_callback' => [$this, 'checkUserAuth']
        ]);
        
        register_rest_route('ccc/v1', '/installations/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'getInstallationStats'],
            'permission_callback' => [$this, 'checkUserAuth']
        ]);
    }
    
    public function handleRegister($request) {
        $params = $request->get_params();
        
        $email = sanitize_email($params['email']);
        $phone = sanitize_text_field($params['phone']);
        $password = $params['password'];
        
        if (empty($email) || empty($phone) || empty($password)) {
            return new \WP_Error('missing_fields', 'All fields are required', ['status' => 400]);
        }
        
        if (!is_email($email)) {
            return new \WP_Error('invalid_email', 'Invalid email address', ['status' => 400]);
        }
        
        // Check if user already exists
        global $wpdb;
        $table = $wpdb->prefix . 'ccc_users';
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE email = %s",
            $email
        ));
        
        if ($existing) {
            return new \WP_Error('user_exists', 'User already exists', ['status' => 400]);
        }
        
        // Create user with default role
        $wpdb->insert(
            $table,
            [
                'email' => $email,
                'phone' => $phone,
                'password' => wp_hash_password($password),
                'role' => 'user',
                'status' => 'pending'
            ]
        );
        
        $user_id = $wpdb->insert_id;
        
        // Track installation only if enabled
        $user_manager = new \CCC\Services\UserManager();
        if ($user_manager->isInstallationTrackingEnabled()) {
            $user_manager->trackInstallation($_SERVER['HTTP_HOST'], $email);
        }
        
        // Report to central server
        do_action('ccc_user_registered', $user_id, [
            'email' => $email,
            'phone' => $phone,
            'role' => 'user'
        ]);
        
        // Set session
        if (!session_id()) {
            session_start();
        }
        $_SESSION['ccc_user_id'] = $user_id;
        $_SESSION['ccc_user_role'] = 'user';
        
        return [
            'success' => true,
            'message' => 'Registration successful'
        ];
    }
    
    public function handleLogin($request) {
        $params = $request->get_params();
        
        $email = sanitize_email($params['email']);
        $password = $params['password'];
        
        if (empty($email) || empty($password)) {
            return new \WP_Error('missing_fields', 'Email and password are required', ['status' => 400]);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'ccc_users';
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE email = %s",
            $email
        ));
        
        if (!$user || !wp_check_password($password, $user->password)) {
            return new \WP_Error('invalid_credentials', 'Invalid email or password', ['status' => 401]);
        }
        
        // Set session
        if (!session_id()) {
            session_start();
        }
        $_SESSION['ccc_user_id'] = $user->id;
        $_SESSION['ccc_user_role'] = $user->role;
        
        // Get license data
        $license_data = $this->getUserLicense($user->id);
        
        return [
            'success' => true,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'phone' => $user->phone,
                'created_at' => $user->created_at,
                'status' => $user->status,
                'role' => $user->role
            ],
            'license' => $license_data
        ];
    }
    
    public function handleLogout($request) {
        if (!session_id()) {
            session_start();
        }
        
        unset($_SESSION['ccc_user_id']);
        unset($_SESSION['ccc_user_role']);
        session_destroy();
        
        return [
            'success' => true,
            'message' => 'Logged out successfully'
        ];
    }
    
    public function checkAuth($request) {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['ccc_user_id'])) {
            return new \WP_Error('not_authenticated', 'Not authenticated', ['status' => 401]);
        }
        
        $user_id = $_SESSION['ccc_user_id'];
        
        global $wpdb;
        $table = $wpdb->prefix . 'ccc_users';
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $user_id
        ));
        
        if (!$user) {
            return new \WP_Error('user_not_found', 'User not found', ['status' => 404]);
        }
        
        $license_data = $this->getUserLicense($user->id);
        
        return [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'phone' => $user->phone,
                'created_at' => $user->created_at,
                'status' => $user->status,
                'role' => $user->role
            ],
            'license' => $license_data
        ];
    }
    
    public function requestLicense($request) {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['ccc_user_id'])) {
            return new \WP_Error('not_authenticated', 'Not authenticated', ['status' => 401]);
        }
        
        $user_id = $_SESSION['ccc_user_id'];
        
        global $wpdb;
        $table = $wpdb->prefix . 'ccc_users';
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $user_id
        ));
        
        if (!$user) {
            return new \WP_Error('user_not_found', 'User not found', ['status' => 404]);
        }
        
        $user_manager = new \CCC\Services\UserManager();
        $result = $user_manager->requestLicense($_SERVER['HTTP_HOST'], $user->email);
        
        if ($result) {
                    return [
            'success' => true,
            'message' => 'License request submitted successfully'
        ];
    } else {
        return new \WP_Error('request_failed', 'License request failed', ['status' => 500]);
    }
}

/**
 * Get all plugin installations (ADMIN/SUPER_ADMIN ONLY)
 */
    public function getAllInstallations($request) {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['ccc_user_id'])) {
            return new \WP_Error('not_authenticated', 'Not authenticated', ['status' => 401]);
        }
        
        $user_id = $_SESSION['ccc_user_id'];
        
        global $wpdb;
        $table = $wpdb->prefix . 'ccc_users';
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $user_id
        ));
        
        if (!$user) {
            return new \WP_Error('user_not_found', 'User not found', ['status' => 404]);
        }
        
        // Check if user has admin privileges - ONLY admin and super_admin can see all installations
        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return new \WP_Error('insufficient_permissions', 'Access denied. Only administrators can view installation data.', ['status' => 403]);
        }
        
        $user_manager = new \CCC\Services\UserManager();
        $installations = $user_manager->getAllInstallations($user->role);
        
        if ($installations === false) {
            return new \WP_Error('access_denied', 'Access denied', ['status' => 403]);
        }
        
        return [
            'success' => true,
            'installations' => $installations
        ];
    }

/**
 * Get installation statistics (ADMIN/SUPER_ADMIN ONLY)
 */
    public function getInstallationStats($request) {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['ccc_user_id'])) {
            return new \WP_Error('not_authenticated', 'Not authenticated', ['status' => 401]);
        }
        
        $user_id = $_SESSION['ccc_user_id'];
        
        global $wpdb;
        $table = $wpdb->prefix . 'ccc_users';
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $user_id
        ));
        
        if (!$user) {
            return new \WP_Error('user_not_found', 'User not found', ['status' => 404]);
        }
        
        // Check if user has admin privileges - ONLY admin and super_admin can see installation statistics
        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return new \WP_Error('insufficient_permissions', 'Access denied. Only administrators can view installation statistics.', ['status' => 403]);
        }
        
        $user_manager = new \CCC\Services\UserManager();
        $stats = $user_manager->getInstallationStats($user->role);
        
        if ($stats === false) {
            return new \WP_Error('access_denied', 'Access denied', ['status' => 403]);
        }
        
        return [
            'success' => true,
            'stats' => $stats
        ];
    }
    
    private function getUserLicense($user_id) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'ccc_users';
        $table_installations = $wpdb->prefix . 'ccc_plugin_installations';
        $table_licenses = $wpdb->prefix . 'ccc_licenses';
        
        // Get user email first
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT email FROM $table_users WHERE id = %d",
            $user_id
        ));
        
        if (!$user) {
            return null;
        }
        
        // Get user's own installation data only
        $license = $wpdb->get_row($wpdb->prepare("
            SELECT l.*, i.domain
            FROM $table_licenses l
            JOIN $table_installations i ON l.installation_id = i.id
            WHERE i.email = %s
            ORDER BY l.created_date DESC
            LIMIT 1
        ", $user->email));
        
        if ($license) {
            return [
                'license_key' => $license->license_key,
                'proxy_api_key' => $license->proxy_api_key,
                'status' => $license->status,
                'created_date' => $license->created_date,
                'activated_date' => $license->activated_date,
                'expires_date' => $license->expires_date
            ];
        }
        
        return null;
    }
    
    private function checkUserAuth($request) {
        if (!session_id()) {
            session_start();
        }
        
        return isset($_SESSION['ccc_user_id']);
    }
}
