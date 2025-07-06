<?php
/**
 * Database Management Class
 *
 * Handles database table creation, updates, and cleanup for Simple Funnel Tracker
 *
 * @package SimpleFunnelTracker
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFT_Database {
    
    /**
     * Database version for tracking schema updates
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Database version option name
     */
    const DB_VERSION_OPTION = 'sfft_db_version';
    
    /**
     * Create all plugin database tables
     *
     * @since 1.0.0
     * @return bool True on success, false on failure
     */
    public static function create_tables() {
        global $wpdb;
        
        // Require upgrade functions
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_prefix = $wpdb->prefix;
        
        // Create funnels table
        $funnels_table = "CREATE TABLE {$table_prefix}ffst_funnels (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            status varchar(20) DEFAULT 'active' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY name (name(191))
        ) $charset_collate;";
        
        // Create funnel steps table
        $funnel_steps_table = "CREATE TABLE {$table_prefix}ffst_funnel_steps (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            funnel_id mediumint(9) NOT NULL,
            page_id mediumint(9) DEFAULT NULL,
            form_id mediumint(9) DEFAULT NULL,
            step_order int(11) NOT NULL,
            step_type varchar(20) NOT NULL,
            step_name varchar(255) DEFAULT '' NOT NULL,
            PRIMARY KEY  (id),
            KEY funnel_id (funnel_id),
            KEY page_id (page_id),
            KEY form_id (form_id),
            KEY step_order (step_order)
        ) $charset_collate;";
        
        // Create tracking events table
        $tracking_events_table = "CREATE TABLE {$table_prefix}ffst_tracking_events (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            funnel_id mediumint(9) NOT NULL,
            step_id mediumint(9) DEFAULT NULL,
            session_id varchar(255) NOT NULL,
            event_type varchar(50) NOT NULL,
            page_id mediumint(9) DEFAULT NULL,
            form_id mediumint(9) DEFAULT NULL,
            form_step_index int(11) DEFAULT NULL,
            utm_source varchar(255) DEFAULT '' NOT NULL,
            utm_medium varchar(255) DEFAULT '' NOT NULL,
            utm_campaign varchar(255) DEFAULT '' NOT NULL,
            utm_content varchar(255) DEFAULT '' NOT NULL,
            utm_term varchar(255) DEFAULT '' NOT NULL,
            user_agent text,
            ip_address varchar(45) DEFAULT '' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY funnel_id (funnel_id),
            KEY session_id (session_id(191)),
            KEY event_type (event_type),
            KEY created_at (created_at),
            KEY page_id (page_id),
            KEY form_id (form_id)
        ) $charset_collate;";
        
        // Create cookie consent table
        $cookie_consent_table = "CREATE TABLE {$table_prefix}ffst_cookie_consent (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            consent_status varchar(20) NOT NULL,
            consent_categories text,
            ip_address varchar(45) DEFAULT '' NOT NULL,
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            expires_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id(191)),
            KEY consent_status (consent_status),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        // Execute table creation
        $tables_created = true;
        
        $result1 = dbDelta($funnels_table);
        if (empty($result1)) {
            $tables_created = false;
            error_log('SFFT: Failed to create funnels table');
        }
        
        $result2 = dbDelta($funnel_steps_table);
        if (empty($result2)) {
            $tables_created = false;
            error_log('SFFT: Failed to create funnel steps table');
        }
        
        $result3 = dbDelta($tracking_events_table);
        if (empty($result3)) {
            $tables_created = false;
            error_log('SFFT: Failed to create tracking events table');
        }
        
        $result4 = dbDelta($cookie_consent_table);
        if (empty($result4)) {
            $tables_created = false;
            error_log('SFFT: Failed to create cookie consent table');
        }
        
        // Update database version
        if ($tables_created) {
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
        }
        
        return $tables_created;
    }
    
    /**
     * Check if database tables exist
     *
     * @since 1.0.0
     * @return bool True if all tables exist, false otherwise
     */
    public static function tables_exist() {
        global $wpdb;
        
        $table_prefix = $wpdb->prefix;
        $tables = array(
            $table_prefix . 'ffst_funnels',
            $table_prefix . 'ffst_funnel_steps',
            $table_prefix . 'ffst_tracking_events',
            $table_prefix . 'ffst_cookie_consent'
        );
        
        foreach ($tables as $table) {
            $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if ($result !== $table) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get current database version
     *
     * @since 1.0.0
     * @return string Database version
     */
    public static function get_db_version() {
        return get_option(self::DB_VERSION_OPTION, '0.0.0');
    }
    
    /**
     * Check if database needs updating
     *
     * @since 1.0.0
     * @return bool True if update needed, false otherwise
     */
    public static function needs_update() {
        $current_version = self::get_db_version();
        return version_compare($current_version, self::DB_VERSION, '<');
    }
    
    /**
     * Update database schema if needed
     *
     * @since 1.0.0
     * @return bool True on success, false on failure
     */
    public static function update_if_needed() {
        if (!self::needs_update()) {
            return true;
        }
        
        return self::create_tables();
    }
    
    /**
     * Drop all plugin database tables (for uninstall)
     *
     * @since 1.0.0
     * @return bool True on success, false on failure
     */
    public static function drop_tables() {
        global $wpdb;
        
        $table_prefix = $wpdb->prefix;
        $tables = array(
            $table_prefix . 'ffst_cookie_consent',
            $table_prefix . 'ffst_tracking_events',
            $table_prefix . 'ffst_funnel_steps',
            $table_prefix . 'ffst_funnels'
        );
        
        $success = true;
        
        // Drop tables in reverse order to handle foreign key constraints
        foreach ($tables as $table) {
            $result = $wpdb->query("DROP TABLE IF EXISTS $table");
            if ($result === false) {
                $success = false;
                error_log("SFFT: Failed to drop table $table");
            }
        }
        
        // Remove database version option
        delete_option(self::DB_VERSION_OPTION);
        
        return $success;
    }
    
    /**
     * Clean up old tracking data
     *
     * @since 1.0.0
     * @param int $days Number of days to keep data (default: 90)
     * @return int Number of records deleted
     */
    public static function cleanup_old_data($days = 90) {
        global $wpdb;
        
        $table_prefix = $wpdb->prefix;
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Clean tracking events
        $tracking_deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_prefix}ffst_tracking_events WHERE created_at < %s",
            $cutoff_date
        ));
        
        // Clean expired cookie consent records
        $consent_deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_prefix}ffst_cookie_consent WHERE expires_at < %s",
            current_time('mysql')
        ));
        
        $total_deleted = intval($tracking_deleted) + intval($consent_deleted);
        
        if ($total_deleted > 0) {
            error_log("SFFT: Cleaned up {$total_deleted} old records");
        }
        
        return $total_deleted;
    }
    
    /**
     * Get database table status
     *
     * @since 1.0.0
     * @return array Table status information
     */
    public static function get_table_status() {
        global $wpdb;
        
        $table_prefix = $wpdb->prefix;
        $status = array();
        
        $tables = array(
            'funnels' => $table_prefix . 'ffst_funnels',
            'funnel_steps' => $table_prefix . 'ffst_funnel_steps',
            'tracking_events' => $table_prefix . 'ffst_tracking_events',
            'cookie_consent' => $table_prefix . 'ffst_cookie_consent'
        );
        
        foreach ($tables as $key => $table) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
            $status[$key] = array(
                'table_name' => $table,
                'exists' => ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table),
                'record_count' => intval($count)
            );
        }
        
        return $status;
    }
    
    /**
     * Optimize database tables
     *
     * @since 1.0.0
     * @return bool True on success, false on failure
     */
    public static function optimize_tables() {
        global $wpdb;
        
        $table_prefix = $wpdb->prefix;
        $tables = array(
            $table_prefix . 'ffst_funnels',
            $table_prefix . 'ffst_funnel_steps',
            $table_prefix . 'ffst_tracking_events',
            $table_prefix . 'ffst_cookie_consent'
        );
        
        $success = true;
        
        foreach ($tables as $table) {
            $result = $wpdb->query("OPTIMIZE TABLE $table");
            if ($result === false) {
                $success = false;
                error_log("SFFT: Failed to optimize table $table");
            }
        }
        
        return $success;
    }
}