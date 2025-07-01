<?php
namespace CCC\Core;

defined('ABSPATH') || exit;

class Database {
    
    /**
     * Current database version
     */
    const DB_VERSION = '1.3.2.0'; // Incremented to trigger auto-update
    
    /**
     * Plugin activation hook
     */
    public static function activate() {
        self::createTables();
        self::createTemplatesDirectory();
        self::setDefaultOptions();
        
        // Set database version
        update_option('ccc_db_version', self::DB_VERSION);
        
        // Log activation
        error_log('CCC Plugin activated - Database version: ' . self::DB_VERSION);
    }
    
    /**
     * Create database tables
     */
    public static function createTables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $components_table = $wpdb->prefix . 'cc_components';
        $fields_table = $wpdb->prefix . 'cc_fields';
        $field_values_table = $wpdb->prefix . 'cc_field_values';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        self::createComponentsTable($components_table, $charset_collate);
        self::createFieldsTable($fields_table, $components_table, $charset_collate);
        self::createFieldValuesTable($field_values_table, $fields_table, $charset_collate);
    }
    
    /**
     * Check and update database schema if needed
     * This now runs on every admin page load to catch new columns automatically
     */
    public static function checkAndUpdateSchema() {
        // Only run in admin to avoid performance issues on frontend
        if (!is_admin()) {
            return;
        }
        
        $current_version = get_option('ccc_db_version', '0.0.0');
        
        // Always check for schema updates, not just version changes
        if (version_compare($current_version, self::DB_VERSION, '<') || self::needsSchemaUpdate()) {
            self::createTables(); // This will run dbDelta and update existing tables
            self::migrateData($current_version); // Run specific migrations
            update_option('ccc_db_version', self::DB_VERSION);
            
            error_log("CCC: Database updated from version {$current_version} to " . self::DB_VERSION);
        }
    }

    /**
     * Check if database schema needs updating by examining table structure
     */
    private static function needsSchemaUpdate() {
        global $wpdb;
        
        $fields_table = $wpdb->prefix . 'cc_fields';
        $field_values_table = $wpdb->prefix . 'cc_field_values';
        
        // Check if required columns exist
        $required_columns = [
            $fields_table => ['placeholder'],
            $field_values_table => ['instance_id']
        ];
        
        foreach ($required_columns as $table => $columns) {
            foreach ($columns as $column) {
                $column_exists = $wpdb->get_results(
                    $wpdb->prepare(
                        "SHOW COLUMNS FROM {$table} LIKE %s",
                        $column
                    )
                );
                
                if (empty($column_exists)) {
                    error_log("CCC: Missing column {$column} in table {$table}");
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Migrate data between versions
     */
    private static function migrateData($from_version) {
        global $wpdb;
        $fields_table = $wpdb->prefix . 'cc_fields';
        $field_values_table = $wpdb->prefix . 'cc_field_values';
        
        // Migration from versions before 1.3.0 - add instance_id column if missing
        if (version_compare($from_version, '1.3.0', '<')) {
            $column_exists = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$field_values_table} LIKE %s",
                    'instance_id'
                )
            );
            
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE {$field_values_table} ADD COLUMN instance_id VARCHAR(255) NOT NULL DEFAULT '' AFTER field_id");
                $wpdb->query("ALTER TABLE {$field_values_table} ADD INDEX instance_id_idx (instance_id)");
                
                error_log('CCC: Added instance_id column to field_values table');
            }
        }
        
        // Migration from versions before 1.3.2 - optimize indexes
        if (version_compare($from_version, '1.3.2', '<')) {
            // Add composite index for better performance
            $wpdb->query("ALTER TABLE {$field_values_table} ADD INDEX post_field_instance_idx (post_id, field_id, instance_id)");
            
            error_log('CCC: Added composite index for better performance');
        }

        // Migration from versions before 1.3.3 - add placeholder column to fields table
        if (version_compare($from_version, '1.3.3', '<')) {
            $column_exists = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$fields_table} LIKE %s",
                    'placeholder'
                )
            );
            
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE {$fields_table} ADD COLUMN placeholder TEXT DEFAULT '' AFTER required");
                error_log('CCC: Added placeholder column to fields table');
            }
        }
    }

    /**
     * Create components table
     */
    private static function createComponentsTable($table_name, $charset_collate) {
        global $wpdb;
        
        $sql = "
            CREATE TABLE $table_name (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                handle_name VARCHAR(255) NOT NULL UNIQUE,
                instruction TEXT,
                hidden BOOLEAN DEFAULT FALSE,
                component_order INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY handle_name_unique (handle_name),
                KEY component_order_idx (component_order),
                KEY hidden_idx (hidden),
                KEY created_at_idx (created_at)
            ) $charset_collate;
        ";
        
        dbDelta($sql);
        
        if ($wpdb->last_error) {
            error_log("CCC: Failed to create $table_name: " . $wpdb->last_error);
        } else {
            error_log("CCC: Successfully created/updated $table_name");
        }
    }

    /**
     * Create fields table
     */
    private static function createFieldsTable($table_name, $components_table, $charset_collate) {
        global $wpdb;
        
        $sql = "
            CREATE TABLE $table_name (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                component_id BIGINT UNSIGNED NOT NULL,
                label VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL DEFAULT 'text',
                config JSON,
                field_order INT DEFAULT 0,
                required BOOLEAN DEFAULT FALSE,
                placeholder TEXT DEFAULT '', -- Added placeholder column
                default_value TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY component_id_idx (component_id),
                KEY name_idx (name),
                KEY type_idx (type),
                KEY field_order_idx (field_order),
                KEY required_idx (required),
                FOREIGN KEY (component_id) REFERENCES $components_table(id) ON DELETE CASCADE
            ) $charset_collate;
        ";
        
        dbDelta($sql);
        
        if ($wpdb->last_error) {
            error_log("CCC: Failed to create $table_name: " . $wpdb->last_error);
        } else {
            error_log("CCC: Successfully created/updated $table_name");
        }
    }

    /**
     * Create field values table
     */
    private static function createFieldValuesTable($table_name, $fields_table, $charset_collate) {
        global $wpdb;
        
        $sql = "
            CREATE TABLE $table_name (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                post_id BIGINT UNSIGNED NOT NULL,
                field_id BIGINT UNSIGNED NOT NULL,
                instance_id VARCHAR(255) NOT NULL DEFAULT '',
                value LONGTEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY post_field_instance_idx (post_id, field_id, instance_id),
                KEY field_id_idx (field_id),
                KEY instance_id_idx (instance_id),
                KEY post_id_idx (post_id),
                KEY created_at_idx (created_at),
                FOREIGN KEY (field_id) REFERENCES $fields_table(id) ON DELETE CASCADE
            ) $charset_collate;
        ";
        
        dbDelta($sql);
        
        if ($wpdb->last_error) {
            error_log("CCC: Failed to create $table_name: " . $wpdb->last_error);
        } else {
            error_log("CCC: Successfully created/updated $table_name");
        }
    }

    /**
     * Create templates directory in theme
     */
    private static function createTemplatesDirectory() {
        $theme_dir = get_stylesheet_directory();
        $templates_dir = $theme_dir . '/ccc-templates';
        
        if (!file_exists($templates_dir)) {
            if (wp_mkdir_p($templates_dir)) {
                // Create .htaccess file for security
                $htaccess_content = "# Protect CCC template files\n<Files \"*.php\">\nOrder Allow,Deny\nDeny from all\n</Files>";
                file_put_contents($templates_dir . '/.htaccess', $htaccess_content);
                
                // Create index.php for security
                file_put_contents($templates_dir . '/index.php', '<?php // Silence is golden');
                
                error_log("CCC: Created templates directory: $templates_dir");
            } else {
                error_log("CCC: Failed to create templates directory: $templates_dir");
            }
        }
        
        // Ensure proper permissions
        if (file_exists($templates_dir) && !is_writable($templates_dir)) {
            chmod($templates_dir, 0755);
        }
    }
    
    /**
     * Set default plugin options
     */
    private static function setDefaultOptions() {
        $default_options = [
            'ccc_enable_debug' => false,
            'ccc_cache_templates' => true,
            'ccc_auto_cleanup' => true,
            'ccc_max_instances' => 10,
            'ccc_allowed_field_types' => ['text', 'textarea', 'url', 'email', 'number']
        ];
        
        foreach ($default_options as $option_name => $default_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $default_value);
            }
        }
    }
    
    /**
     * Get database statistics
     */
    public static function getStats() {
        global $wpdb;
        
        $components_table = $wpdb->prefix . 'cc_components';
        $fields_table = $wpdb->prefix . 'cc_fields';
        $field_values_table = $wpdb->prefix . 'cc_field_values';
        
        $stats = [
            'components' => $wpdb->get_var("SELECT COUNT(*) FROM $components_table"),
            'fields' => $wpdb->get_var("SELECT COUNT(*) FROM $fields_table"),
            'field_values' => $wpdb->get_var("SELECT COUNT(*) FROM $field_values_table"),
            'posts_with_components' => $wpdb->get_var("
                SELECT COUNT(DISTINCT post_id) 
                FROM $field_values_table
            "),
            'db_version' => get_option('ccc_db_version', '0.0.0')
        ];
        
        return $stats;
    }
    
    /**
     * Optimize database tables
     */
    public static function optimizeTables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'cc_components',
            $wpdb->prefix . 'cc_fields',
            $wpdb->prefix . 'cc_field_values'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE $table");
        }
        
        error_log('CCC: Database tables optimized');
    }
    
    /**
     * Clean up plugin data on uninstall
     */
    public static function uninstall() {
        global $wpdb;
        
        // Drop tables
        $tables = [
            $wpdb->prefix . 'cc_field_values',
            $wpdb->prefix . 'cc_fields',
            $wpdb->prefix . 'cc_components'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        // Delete options
        $options = [
            'ccc_db_version',
            'ccc_enable_debug',
            'ccc_cache_templates',
            'ccc_auto_cleanup',
            'ccc_max_instances',
            'ccc_allowed_field_types'
        ];
        
        foreach ($options as $option) {
            delete_option($option);
        }
        
        // Clear scheduled events
        wp_clear_scheduled_hook('ccc_cleanup_temp_files');
        
        error_log('CCC: Plugin data cleaned up on uninstall');
    }
}
