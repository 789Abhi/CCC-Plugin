<?php
namespace CCC\Core;

defined('ABSPATH') || exit;

class Database {
    
    /**
     * Current database version
     */
    const DB_VERSION = '1.3.3.0'; // Incremented to trigger auto-update for handle column
    
    /**
     * Plugin activation hook
     */
    public static function activate() {
        global $wpdb;
        
        // Ensure dbDelta function is available
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Components table
        $components_table = $wpdb->prefix . 'cc_components';
        self::createComponentsTable($components_table, $charset_collate);
        
        // Fields table
        $fields_table = $wpdb->prefix . 'cc_fields';
        self::createFieldsTable($fields_table, $components_table, $charset_collate);
        
        // Field values table
        $field_values_table = $wpdb->prefix . 'cc_field_values';
        self::createFieldValuesTable($field_values_table, $fields_table, $charset_collate);
        
        error_log("CCC Database: Tables created/updated successfully");
        
        // Create templates directory and set default options
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
        
        // First check if tables exist
        $tables_exist = $wpdb->get_var("SHOW TABLES LIKE '{$fields_table}'") == $fields_table &&
                       $wpdb->get_var("SHOW TABLES LIKE '{$field_values_table}'") == $field_values_table;
        
        if (!$tables_exist) {
            error_log("CCC: Required tables don't exist, schema update needed");
            return true;
        }
        
        // Check if required columns exist
        $required_columns = [
            $fields_table => ['placeholder', 'parent_field_id'],
            $field_values_table => ['instance_id']
        ];
        
        foreach ($required_columns as $table => $columns) {
            foreach ($columns as $column) {
                try {
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
                } catch (Exception $e) {
                    error_log("CCC: Error checking column {$column} in table {$table}: " . $e->getMessage());
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

        // Migration for handle column in fields table
        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$fields_table} LIKE %s",
                'handle'
            )
        );
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$fields_table} ADD COLUMN handle VARCHAR(255) NOT NULL AFTER name");
            $wpdb->query("ALTER TABLE {$fields_table} ADD KEY handle_idx (handle)");
            error_log('CCC: Added handle column to fields table');
        }

        // Migration to ensure config column can store JSON data properly
        $column_info = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$fields_table} LIKE %s",
                'config'
            )
        );
        if (!empty($column_info)) {
            $column_type = $column_info[0]->Type;
            // Check if config column is longtext or text (which can store JSON)
            if (strpos($column_type, 'text') === false && strpos($column_type, 'json') === false) {
                $wpdb->query("ALTER TABLE {$fields_table} MODIFY COLUMN config LONGTEXT");
                error_log('CCC: Modified config column to LONGTEXT for JSON storage');
            }
        }

        // Migration for parent_field_id column in fields table
        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$fields_table} LIKE %s",
                'parent_field_id'
            )
        );
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$fields_table} ADD COLUMN parent_field_id BIGINT UNSIGNED DEFAULT NULL AFTER component_id");
            $wpdb->query("ALTER TABLE {$fields_table} ADD KEY parent_field_id_idx (parent_field_id)");
            // Note: Foreign key constraint might fail if table is empty, so we'll skip it for now
            // $wpdb->query("ALTER TABLE {$fields_table} ADD CONSTRAINT fk_parent_field FOREIGN KEY (parent_field_id) REFERENCES {$fields_table}(id) ON DELETE CASCADE");
            error_log('CCC: Added parent_field_id column to fields table');
        }

        // Migration to ensure config column can store JSON data properly
        $column_info = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$fields_table} LIKE %s",
                'config'
            )
        );
        if (!empty($column_info)) {
            $column_type = $column_info[0]->Type;
            // Check if config column is longtext or text (which can store JSON)
            if (strpos($column_type, 'text') === false && strpos($column_type, 'json') === false) {
                $wpdb->query("ALTER TABLE {$fields_table} MODIFY COLUMN config LONGTEXT");
                error_log('CCC: Modified config column to LONGTEXT for JSON storage');
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
                parent_field_id BIGINT UNSIGNED DEFAULT NULL,
                label VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                handle VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL DEFAULT 'text',
                config LONGTEXT,
                field_order INT DEFAULT 0,
                required BOOLEAN DEFAULT FALSE,
                placeholder TEXT DEFAULT '', -- Added placeholder column
                default_value TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY component_id_idx (component_id),
                KEY parent_field_id_idx (parent_field_id),
                KEY name_idx (name),
                KEY handle_idx (handle),
                KEY type_idx (type),
                KEY field_order_idx (field_order),
                KEY required_idx (required),
                FOREIGN KEY (component_id) REFERENCES $components_table(id) ON DELETE CASCADE,
                FOREIGN KEY (parent_field_id) REFERENCES $table_name(id) ON DELETE CASCADE
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

    /**
     * Migrate nested fields in repeater configs to real DB rows with parent_field_id
     */
    public static function migrateNestedFieldsToRows() {
        global $wpdb;
        $fields_table = $wpdb->prefix . 'cc_fields';

        // Add parent_field_id column if it doesn't exist
        $columns = $wpdb->get_col("DESC $fields_table", 0);
        if (!in_array('parent_field_id', $columns)) {
            $wpdb->query("ALTER TABLE $fields_table ADD COLUMN parent_field_id BIGINT UNSIGNED DEFAULT NULL AFTER component_id");
            $wpdb->query("ALTER TABLE $fields_table ADD KEY parent_field_id_idx (parent_field_id)");
            $wpdb->query("ALTER TABLE $fields_table ADD CONSTRAINT fk_parent_field FOREIGN KEY (parent_field_id) REFERENCES $fields_table(id) ON DELETE CASCADE");
        }

        // Get all repeater fields (top-level and nested)
        $repeaters = $wpdb->get_results("SELECT * FROM $fields_table WHERE type = 'repeater'", ARRAY_A);

        foreach ($repeaters as $repeater) {
            $component_id = $repeater['component_id'];
            $parent_field_id = $repeater['id'];
            $config = json_decode($repeater['config'], true);

            if (!empty($config['nested_fields']) && is_array($config['nested_fields'])) {
                self::migrateNestedFieldsToRowsRecursive($component_id, $parent_field_id, $config['nested_fields']);
                // Remove nested_fields from the config of the parent repeater
                unset($config['nested_fields']);
                $wpdb->update($fields_table, ['config' => json_encode($config)], ['id' => $parent_field_id]);
            }
        }
    }

    public static function migrateNestedFieldsToRowsRecursive($component_id, $parent_field_id, $nested_fields) {
        error_log('CCC Database: migrateNestedFieldsToRowsRecursive called with component_id=' . $component_id . ', parent_field_id=' . $parent_field_id . ', nested_fields=' . json_encode($nested_fields));
        global $wpdb;
        $table = $wpdb->prefix . 'cc_fields';
        foreach ($nested_fields as $order => $nested) {
            $insert_data = [
                'component_id' => $component_id,
                'parent_field_id' => $parent_field_id,
                'label' => $nested['label'] ?? '',
                'name' => $nested['name'] ?? '',
                'handle' => sanitize_title($nested['name'] ?? ''),
                'type' => $nested['type'] ?? '',
                'config' => isset($nested['config']) ? json_encode($nested['config']) : '{}',
                'field_order' => $order,
                'required' => isset($nested['required']) ? (int)$nested['required'] : 0,
                'placeholder' => $nested['placeholder'] ?? '',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ];
            error_log('CCC Database: Inserting nested field: ' . json_encode($insert_data));
            $result = $wpdb->insert($table, $insert_data);
            if ($result === false) {
                error_log('CCC Database: DB error inserting nested field: ' . $wpdb->last_error);
            } else {
                error_log('CCC Database: Nested field inserted successfully, insert_id: ' . $wpdb->insert_id);
            }
            // If this nested field is a repeater, recurse
            if (isset($nested['type']) && $nested['type'] === 'repeater' && isset($nested['config']['nested_fields'])) {
                error_log('CCC Database: Recursing into nested repeater field: ' . $nested['name']);
                self::migrateNestedFieldsToRowsRecursive($component_id, $wpdb->insert_id, $nested['config']['nested_fields']);
            }
        }
        error_log('CCC Database: migrateNestedFieldsToRowsRecursive finished for parent_field_id=' . $parent_field_id);
    }

    /**
     * Migration: Convert all nested fields in config.nested_fields to real DB rows with parent_field_id
     */
    public static function migrateAllNestedFieldsToRows() {
        global $wpdb;
        $fields_table = $wpdb->prefix . 'cc_fields';
        $components = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}cc_components", ARRAY_A);
        foreach ($components as $component) {
            $component_id = $component['id'];
            $fields = $wpdb->get_results($wpdb->prepare("SELECT * FROM $fields_table WHERE component_id = %d AND parent_field_id IS NULL", $component_id), ARRAY_A);
            foreach ($fields as $field) {
                if ($field['type'] === 'repeater' && !empty($field['config'])) {
                    $config = json_decode($field['config'], true);
                    if (!empty($config['nested_fields'])) {
                        self::migrateNestedFieldsToRowsRecursive($component_id, $field['id'], $config['nested_fields']);
                        // Remove nested_fields from config after migration
                        unset($config['nested_fields']);
                        $wpdb->update($fields_table, ['config' => json_encode($config)], ['id' => $field['id']]);
                    }
                }
            }
        }
    }
}
