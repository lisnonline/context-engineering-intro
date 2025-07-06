<?php
/**
 * Plugin Activation and Deactivation Handler
 *
 * Handles plugin activation, deactivation, and dependency checks
 *
 * @package SimpleFunnelTracker
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFT_Activator {
    
    /**
     * Minimum WordPress version required
     */
    const MIN_WP_VERSION = '5.0';
    
    /**
     * Minimum PHP version required
     */
    const MIN_PHP_VERSION = '7.4';
    
    /**
     * Plugin activation handler
     *
     * @since 1.0.0
     * @return void
     */
    public static function activate() {
        // Check system requirements
        if (!self::check_requirements()) {
            return;
        }
        
        // Create database tables
        if (!self::create_database_tables()) {
            deactivate_plugins(SFFT_PLUGIN_BASENAME);
            wp_die(
                __('Simple Funnel Tracker could not create required database tables. Please check your database permissions.', 'simple-funnel-tracker'),
                __('Plugin Activation Error', 'simple-funnel-tracker'),
                array('back_link' => true)
            );
        }
        
        // Set default options
        self::set_default_options();
        
        // Set activation flag for one-time setup
        update_option('sfft_plugin_activated', true);
        
        // Schedule cleanup cron job
        self::schedule_cleanup_job();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log activation
        error_log('SFFT: Plugin activated successfully');
    }
    
    /**
     * Plugin deactivation handler
     *
     * @since 1.0.0
     * @return void
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        self::clear_scheduled_jobs();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear any cached data
        self::clear_cache();
        
        // Log deactivation
        error_log('SFFT: Plugin deactivated');
    }
    
    /**
     * Check if system meets minimum requirements
     *
     * @since 1.0.0
     * @return bool True if requirements met, false otherwise
     */
    private static function check_requirements() {
        // Check WordPress version
        if (!self::check_wp_version()) {
            deactivate_plugins(SFFT_PLUGIN_BASENAME);
            wp_die(
                sprintf(
                    __('Simple Funnel Tracker requires WordPress %s or higher. You are running version %s.', 'simple-funnel-tracker'),
                    self::MIN_WP_VERSION,
                    get_bloginfo('version')
                ),
                __('Plugin Activation Error', 'simple-funnel-tracker'),
                array('back_link' => true)
            );
            return false;
        }
        
        // Check PHP version
        if (!self::check_php_version()) {
            deactivate_plugins(SFFT_PLUGIN_BASENAME);
            wp_die(
                sprintf(
                    __('Simple Funnel Tracker requires PHP %s or higher. You are running version %s.', 'simple-funnel-tracker'),
                    self::MIN_PHP_VERSION,
                    PHP_VERSION
                ),
                __('Plugin Activation Error', 'simple-funnel-tracker'),
                array('back_link' => true)
            );
            return false;
        }
        
        // Check database permissions
        if (!self::check_database_permissions()) {
            deactivate_plugins(SFFT_PLUGIN_BASENAME);
            wp_die(
                __('Simple Funnel Tracker requires database CREATE and ALTER permissions to function properly.', 'simple-funnel-tracker'),
                __('Plugin Activation Error', 'simple-funnel-tracker'),
                array('back_link' => true)
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Check WordPress version
     *
     * @since 1.0.0
     * @return bool True if version is sufficient
     */
    private static function check_wp_version() {
        return version_compare(get_bloginfo('version'), self::MIN_WP_VERSION, '>=');
    }
    
    /**
     * Check PHP version
     *
     * @since 1.0.0
     * @return bool True if version is sufficient
     */
    private static function check_php_version() {
        return version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>=');
    }
    
    /**
     * Check database permissions
     *
     * @since 1.0.0
     * @return bool True if permissions are sufficient
     */
    private static function check_database_permissions() {
        global $wpdb;
        
        // Test CREATE permission by creating a temporary table
        $temp_table = $wpdb->prefix . 'sfft_temp_' . uniqid();
        $sql = "CREATE TABLE $temp_table (test_id int(1))";
        $create_result = $wpdb->query($sql);
        
        if ($create_result === false) {
            return false;
        }
        
        // Test DROP permission
        $drop_result = $wpdb->query("DROP TABLE $temp_table");
        
        return $drop_result !== false;
    }
    
    /**
     * Create database tables
     *
     * @since 1.0.0
     * @return bool True on success, false on failure
     */
    private static function create_database_tables() {
        return SFFT_Database::create_tables();
    }
    
    /**
     * Set default plugin options
     *
     * @since 1.0.0
     * @return void
     */
    private static function set_default_options() {
        $default_options = array(
            'sfft_cookie_consent_enabled' => true,
            'sfft_utm_tracking_enabled' => true,
            'sfft_data_retention_days' => 90,
            'sfft_enable_ip_tracking' => true,
            'sfft_enable_user_agent_tracking' => true,
            'sfft_cleanup_frequency' => 'weekly',
            'sfft_consent_banner_text' => __('This website uses cookies to track funnel performance. By continuing to use this site, you consent to our use of cookies.', 'simple-funnel-tracker'),
            'sfft_consent_accept_text' => __('Accept', 'simple-funnel-tracker'),
            'sfft_consent_decline_text' => __('Decline', 'simple-funnel-tracker'),
            'sfft_consent_customize_text' => __('Customize', 'simple-funnel-tracker'),
            'sfft_consent_position' => 'bottom',
            'sfft_consent_style' => 'bar',
            'sfft_analytics_chart_type' => 'bar',
            'sfft_analytics_default_period' => '30',
            'sfft_enable_debug_logging' => false
        );
        
        foreach ($default_options as $option_name => $default_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $default_value);
            }
        }
    }
    
    /**
     * Schedule cleanup cron job
     *
     * @since 1.0.0
     * @return void
     */
    private static function schedule_cleanup_job() {
        if (!wp_next_scheduled('sfft_cleanup_old_data')) {
            wp_schedule_event(time(), 'weekly', 'sfft_cleanup_old_data');
        }
    }
    
    /**
     * Clear scheduled cron jobs
     *
     * @since 1.0.0
     * @return void
     */
    private static function clear_scheduled_jobs() {
        wp_clear_scheduled_hook('sfft_cleanup_old_data');
    }
    
    /**
     * Clear plugin cache
     *
     * @since 1.0.0
     * @return void
     */
    private static function clear_cache() {
        // Clear any transients
        delete_transient('sfft_analytics_cache');
        delete_transient('sfft_funnel_stats_cache');
        
        // Clear object cache if available
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
    
    /**
     * Get activation requirements status
     *
     * @since 1.0.0
     * @return array Requirements status
     */
    public static function get_requirements_status() {
        return array(
            'wp_version' => array(
                'required' => self::MIN_WP_VERSION,
                'current' => get_bloginfo('version'),
                'meets_requirement' => self::check_wp_version()
            ),
            'php_version' => array(
                'required' => self::MIN_PHP_VERSION,
                'current' => PHP_VERSION,
                'meets_requirement' => self::check_php_version()
            ),
            'database_permissions' => array(
                'required' => 'CREATE, ALTER, DROP',
                'meets_requirement' => self::check_database_permissions()
            ),
            'database_tables' => array(
                'required' => 'Plugin tables',
                'meets_requirement' => SFFT_Database::tables_exist()
            )
        );
    }
    
    /**
     * Check if plugin is properly activated
     *
     * @since 1.0.0
     * @return bool True if properly activated
     */
    public static function is_properly_activated() {
        return get_option('sfft_plugin_activated') && 
               SFFT_Database::tables_exist() && 
               self::check_requirements();
    }
}

// Hook for scheduled cleanup
add_action('sfft_cleanup_old_data', array('SFFT_Database', 'cleanup_old_data'));