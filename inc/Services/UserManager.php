<?php

namespace CCC\Services;

class UserManager {
    
    /**
     * Track plugin installation (only called when explicitly needed)
     * @param string $domain The domain where plugin is installed
     * @param string $email The email of the user requesting tracking
     * @param bool $force_create Force creation even if domain exists
     * @return int|false Installation ID or false on failure
     */
    public function trackInstallation($domain, $email, $force_create = false) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ccc_plugin_installations';
        
        // Check if domain already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE domain = %s",
            $domain
        ));
        
        if ($existing && !$force_create) {
            // Update existing installation
            $wpdb->update(
                $table,
                [
                    'email' => $email,
                    'last_activity' => current_time('mysql')
                ],
                ['domain' => $domain]
            );
            return $existing->id;
        } else {
            // Create new installation record
            $wpdb->insert(
                $table,
                [
                    'domain' => $domain,
                    'email' => $email,
                    'status' => 'free',
                    'installation_date' => current_time('mysql'),
                    'last_activity' => current_time('mysql')
                ]
            );
            return $wpdb->insert_id;
        }
    }
    
    /**
     * Check if installation tracking is enabled
     * @return bool True if tracking is enabled
     */
    public function isInstallationTrackingEnabled() {
        return get_option('ccc_enable_installation_tracking', false);
    }
    
    /**
     * Enable/disable installation tracking
     * @param bool $enabled Whether to enable tracking
     */
    public function setInstallationTracking($enabled) {
        update_option('ccc_enable_installation_tracking', $enabled);
    }
    
    /**
     * Create initial installation record for the current domain
     * This should only be called once when setting up the plugin
     * @param string $admin_email The admin email for this installation
     * @return int|false Installation ID or false on failure
     */
    public function createInitialInstallation($admin_email) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ccc_plugin_installations';
        
        // Check if already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE domain = %s",
            $_SERVER['HTTP_HOST']
        ));
        
        if ($existing) {
            return $existing->id; // Already exists
        }
        
        // Create initial installation record
        $wpdb->insert(
            $table,
            [
                'domain' => $_SERVER['HTTP_HOST'],
                'email' => $admin_email,
                'status' => 'free',
                'installation_date' => current_time('mysql'),
                'last_activity' => current_time('mysql')
            ]
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Clear all installation records (for testing/reset purposes)
     * @return bool True if successful
     */
    public function clearAllInstallations() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ccc_plugin_installations';
        $licenses_table = $wpdb->prefix . 'ccc_licenses';
        
        // Delete related licenses first
        $wpdb->query("DELETE FROM $licenses_table");
        
        // Delete all installations
        $result = $wpdb->query("DELETE FROM $table");
        
        return $result !== false;
    }
    
    public function requestLicense($domain, $email) {
        // Generate unique license key
        $license_key = $this->generateLicenseKey();
        $proxy_api_key = $this->generateProxyApiKey();
        
        global $wpdb;
        
        $table_installations = $wpdb->prefix . 'ccc_plugin_installations';
        $table_licenses = $wpdb->prefix . 'ccc_licenses';
        
        // Get installation ID
        $installation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_installations WHERE domain = %s",
            $domain
        ));
        
        if (!$installation) {
            return false;
        }
        
        // Create license record
        $wpdb->insert(
            $table_licenses,
            [
                'installation_id' => $installation->id,
                'license_key' => $license_key,
                'proxy_api_key' => $proxy_api_key,
                'status' => 'pending'
            ]
        );
        
        // Update installation status
        $wpdb->update(
            $table_installations,
            [
                'license_key' => $license_key,
                'proxy_api_key' => $proxy_api_key,
                'status' => 'pending'
            ],
            ['id' => $installation->id]
        );
        
        // Send email notification to admin (if configured)
        $email_sent = $this->sendLicenseRequestEmail($domain, $email, $license_key);
        
        // Log the license request for manual review
        error_log("CCC: License request for domain: {$domain}, email: {$email}, license: {$license_key}");
        
        return [
            'license_key' => $license_key,
            'proxy_api_key' => $proxy_api_key,
            'email_sent' => $email_sent
        ];
    }
    
    private function generateLicenseKey() {
        return 'CCC-LIC-' . strtoupper(substr(md5(uniqid()), 0, 8)) . '-' . strtoupper(substr(md5(uniqid()), 0, 4));
    }
    
    private function generateProxyApiKey() {
        return 'ccc-proxy-' . strtolower(substr(md5(uniqid()), 0, 16));
    }
    
    private function sendLicenseRequestEmail($domain, $email, $license_key) {
        $admin_email = get_option('ccc_admin_email', '');
        if (empty($admin_email)) {
            error_log('CCC: No admin email configured for license requests');
            return false;
        }
        $subject = 'New License Request - Custom Craft Component';
        
        $message = "
        A new license request has been submitted:
        
        Domain: {$domain}
        Email: {$email}
        License Key: {$license_key}
        Date: " . current_time('mysql') . "
        
        To approve this license, go to your admin panel and activate the license.
        ";
        
        if ($admin_email) {
            wp_mail($admin_email, $subject, $message);
        }
    }
    
    /**
     * Get all plugin installations (ADMIN/SUPER_ADMIN ONLY)
     * @param string $user_role The role of the requesting user
     * @return array|false Array of installations or false if unauthorized
     */
    public function getAllInstallations($user_role = 'user') {
        // Only admin and super_admin can access all installations
        if (!in_array($user_role, ['admin', 'super_admin'])) {
            error_log('CCC: Unauthorized access attempt to getAllInstallations by role: ' . $user_role);
            return false;
        }
        
        global $wpdb;
        
        $table_installations = $wpdb->prefix . 'ccc_plugin_installations';
        $table_licenses = $wpdb->prefix . 'ccc_licenses';
        
        return $wpdb->get_results("
            SELECT i.*, l.status as license_status, l.activated_date, l.expires_date
            FROM $table_installations i
            LEFT JOIN $table_licenses l ON i.id = l.installation_id
            ORDER BY i.installation_date DESC
        ");
    }
    
    public function validateUserRole($email) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'ccc_users';
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_users WHERE email = %s",
            $email
        ));
        
        if (!$user) {
            return 'user'; // Default role for new users
        }
        
        return $user->role;
    }
    
    /**
     * Get current user's installation data only (for regular users)
     * @param string $user_email The email of the current user
     * @return array|false Array of user's installation or false if not found
     */
    public function getUserInstallation($user_email) {
        global $wpdb;
        
        $table_installations = $wpdb->prefix . 'ccc_plugin_installations';
        $table_licenses = $wpdb->prefix . 'ccc_licenses';
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT i.*, l.status as license_status, l.activated_date, l.expires_date
            FROM $table_installations i
            LEFT JOIN $table_licenses l ON i.id = l.installation_id
            WHERE i.email = %s
            ORDER BY i.installation_date DESC
            LIMIT 1
        ", $user_email));
    }
    
    /**
     * Check if user has access to view installations
     * @param string $user_role The role of the requesting user
     * @return bool True if user can access installations
     */
    public function canAccessInstallations($user_role) {
        return in_array($user_role, ['admin', 'super_admin']);
    }
    
    /**
     * Get installation statistics (ADMIN/SUPER_ADMIN ONLY)
     * @param string $user_role The role of the requesting user
     * @return array|false Array of statistics or false if unauthorized
     */
    public function getInstallationStats($user_role = 'user') {
        // Only admin and super_admin can access installation statistics
        if (!in_array($user_role, ['admin', 'super_admin'])) {
            error_log('CCC: Unauthorized access attempt to getInstallationStats by role: ' . $user_role);
            return false;
        }
        
        global $wpdb;
        
        $table_installations = $wpdb->prefix . 'ccc_plugin_installations';
        $table_licenses = $wpdb->prefix . 'ccc_licenses';
        
        $stats = [
            'total_installations' => $wpdb->get_var("SELECT COUNT(*) FROM $table_installations"),
            'free_installations' => $wpdb->get_var("SELECT COUNT(*) FROM $table_installations WHERE status = 'free'"),
            'pending_licenses' => $wpdb->get_var("SELECT COUNT(*) FROM $table_licenses WHERE status = 'pending'"),
            'active_licenses' => $wpdb->get_var("SELECT COUNT(*) FROM $table_licenses WHERE status = 'active'"),
            'total_revenue' => 0 // Placeholder for future payment integration
        ];
        
        return $stats;
    }
}
