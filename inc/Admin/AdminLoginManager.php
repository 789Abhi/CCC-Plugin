<?php

namespace CCC\Admin;

defined('ABSPATH') || exit;

class AdminLoginManager {
    
    private $master_password_option = 'ccc_master_password';
    private $admin_access_token_option = 'ccc_admin_access_token';
    
    public function __construct() {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'handleAdminLogin']);
        add_action('wp_ajax_ccc_verify_master_password', [$this, 'verifyMasterPassword']);
        add_action('wp_ajax_ccc_promote_user', [$this, 'promoteUser']);
        add_action('wp_ajax_ccc_create_admin_user', [$this, 'createAdminUser']);
        
        // Handle logout
        if (isset($_POST['action']) && $_POST['action'] === 'logout') {
            add_action('admin_init', [$this, 'handleLogout']);
        }
    }
    
    /**
     * Add admin menu for master password login
     */
    public function addAdminMenu() {
        add_submenu_page(
            'custom-craft-settings',
            'Admin Login',
            'Admin Login',
            'manage_options',
            'custom-craft-settings-admin-login',
            [$this, 'renderAdminLoginPage']
        );
    }
    
    /**
     * Render the admin login page
     */
    public function renderAdminLoginPage() {
        // Check if already authenticated
        if ($this->isAdminAuthenticated()) {
            $this->renderAdminDashboard();
            return;
        }
        
        $this->renderLoginForm();
    }
    
    /**
     * Render the master password login form
     */
    private function renderLoginForm() {
        ?>
        <div class="wrap">
            <h1>🔐 Admin Access - Master Password Required</h1>
            
            <div class="notice notice-warning">
                <p><strong>⚠️ Security Notice:</strong> This page requires the master password to access admin functions.</p>
            </div>
            
            <div class="card" style="max-width: 500px;">
                <h2>Enter Master Password</h2>
                
                <form method="post" action="">
                    <?php wp_nonce_field('ccc_admin_login', 'ccc_admin_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="master_password">Master Password</label>
                            </th>
                            <td>
                                <input type="password" 
                                       name="master_password" 
                                       id="master_password" 
                                       class="regular-text" 
                                       required 
                                       autocomplete="off"
                                       placeholder="Enter master password">
                                <p class="description">Enter the master password to access admin functions.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" 
                               name="submit" 
                               id="submit" 
                               class="button button-primary" 
                               value="Access Admin Panel">
                    </p>
                </form>
            </div>
            
            <div class="card" style="max-width: 500px; margin-top: 20px;">
                <h3>📋 What This Gives You Access To:</h3>
                <ul>
                    <li>✅ Promote users to Admin/Super Admin roles</li>
                    <li>✅ Create new admin accounts</li>
                    <li>✅ View all plugin installations</li>
                    <li>✅ Manage licenses and user accounts</li>
                    <li>✅ System-wide plugin management</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render the admin dashboard after successful authentication
     */
    private function renderAdminDashboard() {
        global $wpdb;
        
        // Get all users
        $users_table = $wpdb->prefix . 'ccc_users';
        $users = $wpdb->get_results("SELECT * FROM $users_table ORDER BY created_at DESC");
        
        // Get installation stats
        $installations_table = $wpdb->prefix . 'ccc_plugin_installations';
        $licenses_table = $wpdb->prefix . 'ccc_licenses';
        
        $total_installations = $wpdb->get_var("SELECT COUNT(*) FROM $installations_table");
        $free_installations = $wpdb->get_var("SELECT COUNT(*) FROM $installations_table WHERE status = 'free'");
        $pending_licenses = $wpdb->get_var("SELECT COUNT(*) FROM $licenses_table WHERE status = 'pending'");
        $active_licenses = $wpdb->get_var("SELECT COUNT(*) FROM $licenses_table WHERE status = 'active'");
        
        ?>
        <div class="wrap">
            <h1>👑 Admin Dashboard - Master Access Granted</h1>
            
            <div class="notice notice-success">
                <p><strong>✅ Authenticated:</strong> You have successfully verified the master password.</p>
            </div>
            
            <!-- Statistics Overview -->
            <div class="card" style="margin-bottom: 20px;">
                <h2>📊 System Overview</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div style="text-align: center; padding: 20px; background: #f0f8ff; border-radius: 8px;">
                        <h3 style="margin: 0; color: #0066cc;"><?php echo $total_installations; ?></h3>
                        <p style="margin: 5px 0 0 0;">Total Installations</p>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #fff0f0; border-radius: 8px;">
                        <h3 style="margin: 0; color: #cc0000;"><?php echo $free_installations; ?></h3>
                        <p style="margin: 5px 0 0 0;">Free Installations</p>
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
            
            <!-- User Management -->
            <div class="card">
                <h2>👥 User Management</h2>
                
                <?php if ($users): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo esc_html($user->email); ?></td>
                                    <td><?php echo esc_html($user->phone); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo esc_attr($user->role); ?>">
                                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $user->role))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo esc_attr($user->status); ?>">
                                            <?php echo esc_html(ucfirst($user->status)); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html(date('M j, Y', strtotime($user->created_at))); ?></td>
                                    <td>
                                        <?php if ($user->role === 'user'): ?>
                                            <button class="button button-small promote-user" 
                                                    data-user-id="<?php echo esc_attr($user->id); ?>"
                                                    data-email="<?php echo esc_attr($user->email); ?>">
                                                Promote to Admin
                                            </button>
                                        <?php elseif ($user->role === 'admin'): ?>
                                            <button class="button button-small promote-user" 
                                                    data-user-id="<?php echo esc_attr($user->id); ?>"
                                                    data-email="<?php echo esc_attr($user->email); ?>">
                                                Promote to Super Admin
                                            </button>
                                        <?php else: ?>
                                            <span class="description">Highest role</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No users found.</p>
                <?php endif; ?>
            </div>
            
            <!-- Create New Admin User -->
            <div class="card" style="margin-top: 20px;">
                <h2>➕ Create New Admin User</h2>
                <form id="create-admin-form" method="post">
                    <?php wp_nonce_field('ccc_create_admin', 'ccc_create_admin_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="new_admin_email">Email Address</label>
                            </th>
                            <td>
                                <input type="email" 
                                       name="new_admin_email" 
                                       id="new_admin_email" 
                                       class="regular-text" 
                                       required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="new_admin_phone">Phone Number</label>
                            </th>
                            <td>
                                <input type="text" 
                                       name="new_admin_phone" 
                                       id="new_admin_phone" 
                                       class="regular-text" 
                                       required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="new_admin_password">Password</label>
                            </th>
                            <td>
                                <input type="password" 
                                       name="new_admin_password" 
                                       id="new_admin_password" 
                                       class="regular-text" 
                                       required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="new_admin_role">Role</label>
                            </th>
                            <td>
                                <select name="new_admin_role" id="new_admin_role">
                                    <option value="admin">Admin</option>
                                    <option value="super_admin">Super Admin</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" 
                               name="create_admin" 
                               id="create_admin" 
                               class="button button-primary" 
                               value="Create Admin User">
                    </p>
                </form>
            </div>
            
            <!-- Logout -->
            <div class="card" style="margin-top: 20px;">
                <h2>🚪 Session Management</h2>
                <p>You are currently authenticated with master password access.</p>
                <form method="post" action="">
                    <?php wp_nonce_field('ccc_admin_logout', 'ccc_admin_logout_nonce'); ?>
                    <input type="hidden" name="action" value="logout">
                    <input type="submit" 
                           name="logout" 
                           class="button button-secondary" 
                           value="Logout from Admin Panel">
                </form>
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
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .status-pending { background: #fff3e0; color: #ef6c00; }
        .status-active { background: #e8f5e8; color: #2e7d32; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Handle user promotion
            $('.promote-user').on('click', function() {
                var userId = $(this).data('user-id');
                var email = $(this).data('email');
                
                if (confirm('Are you sure you want to promote ' + email + '?')) {
                    $.post(ajaxurl, {
                        action: 'ccc_promote_user',
                        user_id: userId,
                        nonce: '<?php echo wp_create_nonce('ccc_promote_user'); ?>'
                    }, function(response) {
                        if (response.success) {
                            alert('User promoted successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    });
                }
            });
            
            // Handle admin user creation
            $('#create-admin-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = {
                    action: 'ccc_create_admin_user',
                    email: $('#new_admin_email').val(),
                    phone: $('#new_admin_phone').val(),
                    password: $('#new_admin_password').val(),
                    role: $('#new_admin_role').val(),
                    nonce: '<?php echo wp_create_nonce('ccc_create_admin_user'); ?>'
                };
                
                $.post(ajaxurl, formData, function(response) {
                    if (response.success) {
                        alert('Admin user created successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle admin login form submission
     */
    public function handleAdminLogin() {
        if (!isset($_POST['submit']) || !isset($_POST['master_password'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['ccc_admin_nonce'], 'ccc_admin_login')) {
            wp_die('Security check failed');
        }
        
        $master_password = sanitize_text_field($_POST['master_password']);
        
        if ($this->verifyMasterPassword($master_password)) {
            $this->setAdminAuthenticated();
            wp_redirect(admin_url('admin.php?page=custom-craft-settings-admin-login'));
            exit;
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>❌ Invalid master password. Access denied.</p></div>';
            });
        }
    }
    
    /**
     * Verify master password
     */
    public function verifyMasterPassword($password) {
        $stored_hash = get_option($this->master_password_option);
        
        if (empty($stored_hash)) {
            // Set default master password if none exists
            $default_password = 'admin123456';
            $this->setMasterPassword($default_password);
            $stored_hash = get_option($this->master_password_option);
        }
        
        return wp_check_password($password, $stored_hash);
    }
    
    /**
     * Set master password
     */
    public function setMasterPassword($password) {
        $hash = wp_hash_password($password);
        update_option($this->master_password_option, $hash);
    }
    
    /**
     * Check if admin is authenticated
     */
    private function isAdminAuthenticated() {
        $token = get_option($this->admin_access_token_option);
        $expiry = get_option($this->admin_access_token_option . '_expiry');
        
        if (empty($token) || empty($expiry)) {
            return false;
        }
        
        // Check if token is expired (24 hours)
        if (time() > $expiry) {
            $this->clearAdminAuthentication();
            return false;
        }
        
        return true;
    }
    
    /**
     * Set admin as authenticated
     */
    private function setAdminAuthenticated() {
        $token = wp_generate_password(32, false);
        $expiry = time() + (24 * 60 * 60); // 24 hours
        
        update_option($this->admin_access_token_option, $token);
        update_option($this->admin_access_token_option . '_expiry', $expiry);
    }
    
    /**
     * Handle logout from admin panel
     */
    public function handleLogout() {
        if (!wp_verify_nonce($_POST['ccc_admin_logout_nonce'], 'ccc_admin_logout')) {
            wp_die('Security check failed');
        }
        
        $this->clearAdminAuthentication();
        wp_redirect(admin_url('admin.php?page=custom-craft-settings-admin-login'));
        exit;
    }
    
    /**
     * Clear admin authentication
     */
    private function clearAdminAuthentication() {
        delete_option($this->admin_access_token_option);
        delete_option($this->admin_access_token_option . '_expiry');
    }
    
    /**
     * AJAX: Promote user to higher role
     */
    public function promoteUser() {
        check_ajax_referer('ccc_promote_user', 'nonce');
        
        if (!$this->isAdminAuthenticated()) {
            wp_send_json_error(['message' => 'Not authenticated']);
        }
        
        $user_id = intval($_POST['user_id']);
        
        global $wpdb;
        $users_table = $wpdb->prefix . 'ccc_users';
        
        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE id = %d", $user_id));
        
        if (!$user) {
            wp_send_json_error(['message' => 'User not found']);
        }
        
        $new_role = '';
        if ($user->role === 'user') {
            $new_role = 'admin';
        } elseif ($user->role === 'admin') {
            $new_role = 'super_admin';
        } else {
            wp_send_json_error(['message' => 'User already has highest role']);
        }
        
        $result = $wpdb->update(
            $users_table,
            ['role' => $new_role],
            ['id' => $user_id]
        );
        
        if ($result !== false) {
            wp_send_json_success(['message' => 'User promoted successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to promote user']);
        }
    }
    
    /**
     * AJAX: Create new admin user
     */
    public function createAdminUser() {
        check_ajax_referer('ccc_create_admin_user', 'nonce');
        
        if (!$this->isAdminAuthenticated()) {
            wp_send_json_error(['message' => 'Not authenticated']);
        }
        
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $password = $_POST['password'];
        $role = sanitize_text_field($_POST['role']);
        
        if (empty($email) || empty($phone) || empty($password) || empty($role)) {
            wp_send_json_error(['message' => 'All fields are required']);
        }
        
        if (!in_array($role, ['admin', 'super_admin'])) {
            wp_send_json_error(['message' => 'Invalid role']);
        }
        
        global $wpdb;
        $users_table = $wpdb->prefix . 'ccc_users';
        
        // Check if user already exists
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE email = %s", $email));
        if ($existing) {
            wp_send_json_error(['message' => 'User already exists']);
        }
        
        // Create user
        $result = $wpdb->insert(
            $users_table,
            [
                'email' => $email,
                'phone' => $phone,
                'password' => wp_hash_password($password),
                'role' => $role,
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ]
        );
        
        if ($result !== false) {
            wp_send_json_success(['message' => 'Admin user created successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to create admin user']);
        }
    }
}
