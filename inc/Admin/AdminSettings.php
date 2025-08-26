<?php

namespace CCC\Admin;

defined('ABSPATH') || exit;

class AdminSettings {
    
    private $option_group = 'ccc_ai_settings';
    private $option_name = 'ccc_ai_config';
    
    public function __construct() {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'initSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
    }
    
    /**
     * Add admin menu item
     */
    public function addAdminMenu() {
        add_submenu_page(
            'custom-craft-component',
            'AI Settings',
            'AI Settings',
            'manage_options',
            'ccc-ai-settings',
            [$this, 'renderSettingsPage']
        );
    }
    
    /**
     * Initialize settings
     */
    public function initSettings() {
        register_setting(
            $this->option_group,
            $this->option_name,
            [$this, 'sanitizeSettings']
        );
        
        add_settings_section(
            'ccc_ai_main_section',
            'OpenAI API Configuration',
            [$this, 'renderMainSection'],
            'ccc-ai-settings'
        );
        
        add_settings_field(
            'ccc_openai_api_key',
            'OpenAI API Key',
            [$this, 'renderApiKeyField'],
            'ccc-ai-settings',
            'ccc_ai_main_section'
        );
        
        add_settings_field(
            'ccc_ai_rate_limit',
            'Rate Limit (requests per hour)',
            [$this, 'renderRateLimitField'],
            'ccc-ai-settings',
            'ccc_ai_main_section'
        );
        
        add_settings_field(
            'ccc_ai_model',
            'AI Model',
            [$this, 'renderModelField'],
            'ccc-ai-settings',
            'ccc_ai_main_section'
        );
        
        add_settings_field(
            'ccc_ai_test_connection',
            'Test Connection',
            [$this, 'renderTestConnectionField'],
            'ccc-ai-settings',
            'ccc_ai_main_section'
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueueAdminScripts($hook) {
        if ($hook !== 'custom-craft-component_page_ccc-ai-settings') {
            return;
        }
        
        wp_enqueue_script(
            'ccc-admin-ai-settings',
            CCC_PLUGIN_URL . 'frontend/dist/admin-ai-settings.js',
            ['jquery'],
            '1.0.0',
            true
        );
        
        wp_localize_script('ccc-admin-ai-settings', 'cccAiSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ccc_ai_settings_nonce'),
            'strings' => [
                'testing' => 'Testing connection...',
                'success' => 'Connection successful!',
                'error' => 'Connection failed: ',
                'invalidKey' => 'Invalid API key format',
                'saving' => 'Saving settings...',
                'saved' => 'Settings saved successfully!'
            ]
        ]);
    }
    
    /**
     * Render the main settings page
     */
    public function renderSettingsPage() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $current_config = get_option($this->option_name, []);
        $api_key = $current_config['openai_api_key'] ?? '';
        $rate_limit = $current_config['rate_limit'] ?? 50;
        $model = $current_config['model'] ?? 'gpt-4o-mini';
        
        ?>
        <div class="wrap">
            <h1>🤖 AI Settings - Custom Craft Component</h1>
            
            <div class="notice notice-info">
                <p><strong>Configure your OpenAI API key and AI settings here.</strong> These settings will be used for AI-powered component generation.</p>
            </div>
            
            <form method="post" action="options.php" id="ccc-ai-settings-form">
                <?php
                settings_fields($this->option_group);
                do_settings_sections('ccc-ai-settings');
                ?>
                
                <div class="ccc-ai-status-section">
                    <h3>AI Status</h3>
                    <div id="ccc-ai-status-display">
                        <div class="ccc-ai-status-item">
                            <span class="status-label">API Key:</span>
                            <span class="status-value" id="api-key-status">
                                <?php echo !empty($api_key) ? '✅ Configured' : '❌ Not configured'; ?>
                            </span>
                        </div>
                        <div class="ccc-ai-status-item">
                            <span class="status-label">Connection:</span>
                            <span class="status-value" id="connection-status">
                                <span class="status-unknown">⏳ Test connection</span>
                            </span>
                        </div>
                        <div class="ccc-ai-status-item">
                            <span class="status-label">Rate Limit:</span>
                            <span class="status-value">
                                <?php echo esc_html($rate_limit); ?> requests/hour
                            </span>
                        </div>
                        <div class="ccc-ai-status-item">
                            <span class="status-label">Model:</span>
                            <span class="status-value">
                                <?php echo esc_html($model); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="ccc-ai-actions">
                    <button type="button" id="test-connection-btn" class="button button-secondary">
                        🧪 Test Connection
                    </button>
                    <button type="submit" class="button button-primary">
                        💾 Save Settings
                    </button>
                </div>
            </form>
            
            <div class="ccc-ai-help-section">
                <h3>📚 Help & Information</h3>
                <div class="ccc-ai-help-content">
                    <div class="help-item">
                        <h4>🔑 Getting Your OpenAI API Key</h4>
                        <ol>
                            <li>Go to <a href="https://platform.openai.com/" target="_blank">OpenAI Platform</a></li>
                            <li>Sign up or log in to your account</li>
                            <li>Navigate to "API Keys" section</li>
                            <li>Create a new API key</li>
                            <li>Copy the key (starts with 'sk-')</li>
                        </ol>
                    </div>
                    
                    <div class="help-item">
                        <h4>💰 Cost Information</h4>
                        <ul>
                            <li><strong>GPT-4o-mini:</strong> ~$0.00015 per request</li>
                            <li><strong>Typical component:</strong> 1-2 requests</li>
                            <li><strong>Monthly (100 components):</strong> ~$0.03</li>
                        </ul>
                    </div>
                    
                    <div class="help-item">
                        <h4>🛡️ Security Features</h4>
                        <ul>
                            <li>API key is encrypted and stored securely</li>
                            <li>Rate limiting prevents abuse</li>
                            <li>Admin-only access to settings</li>
                            <li>All requests are logged for monitoring</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .ccc-ai-status-section {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .ccc-ai-status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f1;
        }
        
        .ccc-ai-status-item:last-child {
            border-bottom: none;
        }
        
        .status-label {
            font-weight: 600;
            color: #1d2327;
        }
        
        .status-value {
            font-family: monospace;
            padding: 4px 8px;
            border-radius: 3px;
            background: #f0f0f1;
        }
        
        .ccc-ai-actions {
            margin: 20px 0;
            padding: 20px;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .ccc-ai-actions .button {
            margin-right: 10px;
        }
        
        .ccc-ai-help-section {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .help-item {
            margin-bottom: 20px;
        }
        
        .help-item h4 {
            margin-top: 0;
            color: #1d2327;
        }
        
        .help-item ol, .help-item ul {
            margin-left: 20px;
        }
        
        .status-unknown {
            color: #856404;
            background: #fff3cd;
            padding: 4px 8px;
            border-radius: 3px;
        }
        
        .status-success {
            color: #155724;
            background: #d4edda;
            padding: 4px 8px;
            border-radius: 3px;
        }
        
        .status-error {
            color: #721c24;
            background: #f8d7da;
            padding: 4px 8px;
            border-radius: 3px;
        }
        </style>
        <?php
    }
    
    /**
     * Render main section description
     */
    public function renderMainSection() {
        echo '<p>Configure your OpenAI API key and AI generation settings below.</p>';
    }
    
    /**
     * Render API key field
     */
    public function renderApiKeyField() {
        $current_config = get_option($this->option_name, []);
        $has_proxy_key = !empty($current_config['proxy_key_hash']);
        
        ?>
        <div class="ccc-api-key-section">
            <?php if ($has_proxy_key): ?>
                <!-- Show when proxy key is configured -->
                <div class="ccc-proxy-key-status">
                    <div class="ccc-status-badge ccc-status-success">
                        ✅ Proxy Key Configured
                    </div>
                    <p class="description">
                        Your proxy key is securely configured. The real API key is encrypted and stored separately.
                    </p>
                    <button type="button" id="change-proxy-key-btn" class="button button-secondary">
                        🔄 Change Proxy Key
                    </button>
                    <button type="button" id="remove-proxy-key-btn" class="button button-link-delete">
                        🗑️ Remove Configuration
                    </button>
                </div>
                
                <!-- Hidden form for changing proxy key -->
                <div id="change-proxy-key-form" class="ccc-hidden-form" style="display: none;">
                    <div class="ccc-form-group">
                        <label for="ccc_proxy_key_new">New Proxy Key:</label>
                        <input type="password" 
                               id="ccc_proxy_key_new" 
                               name="ccc_proxy_key_new" 
                               class="regular-text"
                               placeholder="Enter new proxy key"
                        />
                        <p class="description">
                            This is like a password - it should be different from your actual OpenAI API key.
                        </p>
                    </div>
                    
                    <div class="ccc-form-group">
                        <label for="ccc_openai_api_key_new">OpenAI API Key:</label>
                        <input type="password" 
                               id="ccc_openai_api_key_new" 
                               name="ccc_openai_api_key_new" 
                               class="regular-text"
                               placeholder="sk-your-actual-api-key-here"
                               pattern="sk-[a-zA-Z0-9]{20,}"
                        />
                        <p class="description">
                            Your actual OpenAI API key (starts with 'sk-')
                        </p>
                    </div>
                    
                    <div class="ccc-form-actions">
                        <button type="button" id="save-new-proxy-key-btn" class="button button-primary">
                            💾 Save New Configuration
                        </button>
                        <button type="button" id="cancel-change-btn" class="button button-secondary">
                            ❌ Cancel
                        </button>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Show when no proxy key is configured -->
                <div class="ccc-form-group">
                    <label for="ccc_proxy_key">Proxy Key (Password):</label>
                    <input type="password" 
                           id="ccc_proxy_key" 
                           name="ccc_proxy_key" 
                           class="regular-text"
                           placeholder="Enter a secure proxy key"
                           required
                    />
                    <p class="description">
                        <strong>This is NOT your OpenAI API key!</strong> This is like a password to protect your real API key.
                        Choose something secure (8+ characters, letters, numbers, symbols).
                    </p>
                </div>
                
                <div class="ccc-form-group">
                    <label for="ccc_openai_api_key">OpenAI API Key:</label>
                    <input type="password" 
                           id="ccc_openai_api_key" 
                           name="ccc_openai_api_key" 
                           class="regular-text"
                           placeholder="sk-your-actual-api-key-here"
                           pattern="sk-[a-zA-Z0-9]{20,}"
                           required
                    />
                    <p class="description">
                        Your actual OpenAI API key from OpenAI Platform (starts with 'sk-')
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .ccc-api-key-section {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin: 10px 0;
        }
        
        .ccc-proxy-key-status {
            text-align: center;
            padding: 20px;
        }
        
        .ccc-status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .ccc-status-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .ccc-form-group {
            margin-bottom: 20px;
        }
        
        .ccc-form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .ccc-form-actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        
        .ccc-form-actions .button {
            margin-right: 10px;
        }
        
        .ccc-hidden-form {
            margin-top: 20px;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        </style>
        <?php
    }
    
    /**
     * Render rate limit field
     */
    public function renderRateLimitField() {
        $current_config = get_option($this->option_name, []);
        $rate_limit = $current_config['rate_limit'] ?? 50;
        
        ?>
        <input type="number" 
               id="ccc_ai_rate_limit" 
               name="<?php echo $this->option_name; ?>[rate_limit]" 
               value="<?php echo esc_attr($rate_limit); ?>" 
               class="small-text"
               min="1" 
               max="1000"
        />
        <p class="description">
            Maximum number of AI requests per hour. Recommended: 50-100 for most users.
        </p>
        <?php
    }
    
    /**
     * Render model field
     */
    public function renderModelField() {
        $current_config = get_option($this->option_name, []);
        $model = $current_config['model'] ?? 'gpt-4o-mini';
        
        $models = [
            'gpt-4o-mini' => 'GPT-4o-mini (Recommended - Best performance/cost)',
            'gpt-3.5-turbo' => 'GPT-3.5-turbo (Legacy - Lower cost)'
        ];
        
        ?>
        <select id="ccc_ai_model" 
                name="<?php echo $this->option_name; ?>[model]">
            <?php foreach ($models as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" 
                        <?php selected($model, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            Choose the AI model for component generation. GPT-4o-mini provides better results.
        </p>
        <?php
    }
    
    /**
     * Render test connection field
     */
    public function renderTestConnectionField() {
        ?>
        <button type="button" id="test-connection-btn-field" class="button button-secondary">
            🧪 Test Connection
        </button>
        <p class="description">
            Test your API key to ensure it's working correctly.
        </p>
        <?php
    }
    
    /**
     * Sanitize and encrypt settings
     */
    public function sanitizeSettings($input) {
        $sanitized = [];
        
        // Handle proxy key and API key configuration
        if (isset($input['proxy_key']) && isset($input['openai_api_key'])) {
            $proxy_key = sanitize_text_field($input['proxy_key']);
            $api_key = sanitize_text_field($input['openai_api_key']);
            
            // Validate proxy key
            if (empty($proxy_key) || strlen($proxy_key) < 8) {
                add_settings_error(
                    'ccc_ai_settings',
                    'invalid_proxy_key',
                    'Proxy key must be at least 8 characters long.'
                );
                return get_option($this->option_name, []);
            }
            
            // Validate API key format
            if (!empty($api_key) && !$this->isValidApiKey($api_key)) {
                add_settings_error(
                    'ccc_ai_settings',
                    'invalid_api_key',
                    'Invalid API key format. OpenAI API keys must start with "sk-" and be at least 20 characters long.'
                );
                return get_option($this->option_name, []);
            }
            
            // Store proxy key hash (never store the actual proxy key)
            $sanitized['proxy_key_hash'] = $this->hashProxyKey($proxy_key);
            
            // Encrypt and store the real API key with additional security
            $sanitized['openai_api_key'] = $this->encryptApiKeyWithProxy($api_key, $proxy_key);
            
            // Store additional security metadata
            $sanitized['security_salt'] = $this->generateSecuritySalt();
            $sanitized['encryption_version'] = '2.0';
            
        } elseif (isset($input['proxy_key_new']) && isset($input['openai_api_key_new'])) {
            // Handle proxy key change
            $proxy_key = sanitize_text_field($input['proxy_key_new']);
            $api_key = sanitize_text_field($input['openai_api_key_new']);
            
            // Validate proxy key
            if (empty($proxy_key) || strlen($proxy_key) < 8) {
                add_settings_error(
                    'ccc_ai_settings',
                    'invalid_proxy_key',
                    'New proxy key must be at least 8 characters long.'
                );
                return get_option($this->option_name, []);
            }
            
            // Validate API key format
            if (!empty($api_key) && !$this->isValidApiKey($api_key)) {
                add_settings_error(
                    'ccc_ai_settings',
                    'invalid_api_key',
                    'Invalid API key format. OpenAI API keys must start with "sk-" and be at least 20 characters long.'
                );
                return get_option($this->option_name, []);
            }
            
            // Store new proxy key hash
            $sanitized['proxy_key_hash'] = $this->hashProxyKey($proxy_key);
            
            // Encrypt and store the real API key with new proxy key
            $sanitized['openai_api_key'] = $this->encryptApiKeyWithProxy($api_key, $proxy_key);
            
            // Update security metadata
            $sanitized['security_salt'] = $this->generateSecuritySalt();
            $sanitized['encryption_version'] = '2.0';
        }
        
        // Sanitize rate limit
        if (isset($input['rate_limit'])) {
            $rate_limit = intval($input['rate_limit']);
            $sanitized['rate_limit'] = max(1, min(1000, $rate_limit));
        }
        
        // Sanitize model
        if (isset($input['model'])) {
            $allowed_models = ['gpt-4o-mini', 'gpt-3.5-turbo'];
            $sanitized['model'] = in_array($input['model'], $allowed_models) ? $input['model'] : 'gpt-4o-mini';
        }
        
        return $sanitized;
    }
    
    /**
     * Validate API key format
     */
    private function isValidApiKey($api_key) {
        if (empty($api_key)) {
            return true; // Allow empty key
        }
        
        // OpenAI API keys start with 'sk-' and are typically 51 characters long
        if (strlen($api_key) < 20 || strlen($api_key) > 100) {
            return false;
        }
        
        // Check if it starts with 'sk-' (OpenAI format)
        if (!preg_match('/^sk-[a-zA-Z0-9]{20,}$/', $api_key)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Encrypt API key for storage
     */
    private function encryptApiKey($api_key) {
        if (empty($api_key)) {
            return '';
        }
        
        // Use WordPress's built-in encryption if available
        if (function_exists('wp_encrypt_data')) {
            return wp_encrypt_data($api_key);
        }
        
        // Fallback to simple base64 encoding (not as secure but better than plain text)
        return base64_encode($api_key);
    }
    
    /**
     * Decrypt API key for use
     */
    public static function decryptApiKey($encrypted_key) {
        if (empty($encrypted_key)) {
            return '';
        }
        
        // Try WordPress decryption first
        if (function_exists('wp_decrypt_data')) {
            $decrypted = wp_decrypt_data($encrypted_key);
            if ($decrypted !== false) {
                return $decrypted;
            }
        }
        
        // Fallback to base64 decode
        $decoded = base64_decode($encrypted_key);
        if ($decoded !== false && self::isValidApiKey($decoded)) {
            return $decoded;
        }
        
        return '';
    }
    
    /**
     * Mask API key for display
     */
    private function maskApiKey($api_key) {
        if (empty($api_key) || strlen($api_key) < 8) {
            return '';
        }
        
        return substr($api_key, 0, 8) . '...' . substr($api_key, -4);
    }
    
    /**
     * Hash proxy key for storage (one-way hash)
     */
    private function hashProxyKey($proxy_key) {
        // Use WordPress's built-in password hashing
        return wp_hash_password($proxy_key);
    }
    
    /**
     * Verify proxy key against stored hash
     */
    public static function verifyProxyKey($proxy_key, $stored_hash) {
        return wp_check_password($proxy_key, $stored_hash);
    }
    
    /**
     * Encrypt API key with proxy key for additional security
     */
    private function encryptApiKeyWithProxy($api_key, $proxy_key) {
        if (empty($api_key)) {
            return '';
        }
        
        // Generate a unique encryption key from proxy key
        $encryption_key = hash('sha256', $proxy_key . 'CCC_SALT_' . time(), true);
        
        // Use WordPress encryption if available
        if (function_exists('wp_encrypt_data')) {
            return wp_encrypt_data($api_key, $encryption_key);
        }
        
        // Fallback to custom encryption
        return $this->customEncrypt($api_key, $encryption_key);
    }
    
    /**
     * Decrypt API key using proxy key
     */
    public static function decryptApiKeyWithProxy($encrypted_key, $proxy_key) {
        if (empty($encrypted_key) || empty($proxy_key)) {
            return '';
        }
        
        // Generate the same encryption key
        $encryption_key = hash('sha256', $proxy_key . 'CCC_SALT_' . time(), true);
        
        // Try WordPress decryption first
        if (function_exists('wp_decrypt_data')) {
            $decrypted = wp_decrypt_data($encrypted_key, $encryption_key);
            if ($decrypted !== false) {
                return $decrypted;
            }
        }
        
        // Fallback to custom decryption
        return self::customDecrypt($encrypted_key, $encryption_key);
    }
    
    /**
     * Custom encryption fallback
     */
    private function customEncrypt($data, $key) {
        $method = 'AES-256-CBC';
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Custom decryption fallback
     */
    private static function customDecrypt($encrypted_data, $key) {
        $method = 'AES-256-CBC';
        $data = base64_decode($encrypted_data);
        $iv_length = openssl_cipher_iv_length($method);
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        return openssl_decrypt($encrypted, $method, $key, 0, $iv);
    }
    
    /**
     * Generate security salt
     */
    private function generateSecuritySalt() {
        return wp_generate_password(32, false);
    }
    
    /**
     * Get current configuration (requires proxy key for decryption)
     */
    public static function getConfig($proxy_key = null) {
        $config = get_option('ccc_ai_config', []);
        
        // If no proxy key provided, return config without decrypted API key
        if ($proxy_key === null) {
            return $config;
        }
        
        // Verify proxy key and decrypt API key
        if (isset($config['proxy_key_hash']) && isset($config['openai_api_key'])) {
            if (self::verifyProxyKey($proxy_key, $config['proxy_key_hash'])) {
                $config['openai_api_key'] = self::decryptApiKeyWithProxy($config['openai_api_key'], $proxy_key);
            } else {
                $config['openai_api_key'] = ''; // Invalid proxy key
            }
        }
        
        return $config;
    }
    
    /**
     * Check if AI is configured and ready
     */
    public static function isAiReady() {
        $config = get_option('ccc_ai_config', []);
        return !empty($config['proxy_key_hash']) && !empty($config['openai_api_key']);
    }
    
    /**
     * Get API key using proxy key (for AIService)
     */
    public static function getApiKeyWithProxy($proxy_key) {
        $config = self::getConfig($proxy_key);
        return $config['openai_api_key'] ?? '';
    }
    
    /**
     * Remove all AI configuration
     */
    public static function removeConfiguration() {
        delete_option('ccc_ai_config');
        return true;
    }
    
    /**
     * Check if proxy key is valid
     */
    public static function validateProxyKey($proxy_key) {
        $config = get_option('ccc_ai_config', []);
        if (empty($config['proxy_key_hash'])) {
            return false;
        }
        return self::verifyProxyKey($proxy_key, $config['proxy_key_hash']);
    }
}
