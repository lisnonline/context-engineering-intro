<?php
/**
 * Simple Funnel Tracker Uninstall Handler
 *
 * Handles complete plugin cleanup when plugin is uninstalled
 *
 * @package SimpleFunnelTracker
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Include the database class for cleanup
require_once plugin_dir_path(__FILE__) . 'includes/class-database.php';

/**
 * Uninstall Simple Funnel Tracker
 *
 * Removes all plugin data, tables, and settings
 */
function sfft_uninstall_plugin() {
    // Check if user has permission to delete plugins
    if (!current_user_can('delete_plugins')) {
        return;
    }
    
    // Get all plugin options to delete
    $plugin_options = array(
        // Core settings
        'sfft_cookie_consent_enabled',
        'sfft_utm_tracking_enabled',
        'sfft_data_retention_days',
        'sfft_enable_ip_tracking',
        'sfft_enable_user_agent_tracking',
        'sfft_cleanup_frequency',
        
        // Cookie consent settings
        'sfft_consent_banner_text',
        'sfft_consent_accept_text',
        'sfft_consent_decline_text',
        'sfft_consent_customize_text',
        'sfft_consent_position',
        'sfft_consent_style',
        'sfft_google_consent_mode_enabled',
        
        // Analytics settings
        'sfft_analytics_chart_type',
        'sfft_analytics_default_period',
        
        // Debug settings
        'sfft_enable_debug_logging',
        
        // Internal options
        'sfft_plugin_activated',
        'sfft_db_version',
        
        // Form-specific options (these might have dynamic IDs)
        // Will be handled separately with a wildcard query
    );
    
    // Delete all standard plugin options
    foreach ($plugin_options as $option) {
        delete_option($option);
    }
    
    // Delete form-specific options (pattern: sfft_form_{id}_*)
    global $wpdb;
    
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE 'sfft_form_%'"
    );
    
    // Delete any transients
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_sfft_%' 
         OR option_name LIKE '_transient_timeout_sfft_%'"
    );
    
    // Drop all plugin database tables
    if (class_exists('SFFT_Database')) {
        SFFT_Database::drop_tables();
    } else {
        // Fallback manual table deletion
        sfft_manual_table_cleanup();
    }
    
    // Clear any cached data
    sfft_clear_cached_data();
    
    // Remove cron jobs
    sfft_clear_cron_jobs();
    
    // Log uninstall (if debug logging was enabled)
    error_log('SFFT: Plugin uninstalled and all data removed');
}

/**
 * Manual table cleanup (fallback)
 *
 * @since 1.0.0
 */
function sfft_manual_table_cleanup() {
    global $wpdb;
    
    $tables = array(
        $wpdb->prefix . 'ffst_cookie_consent',
        $wpdb->prefix . 'ffst_tracking_events', 
        $wpdb->prefix . 'ffst_funnel_steps',
        $wpdb->prefix . 'ffst_funnels'
    );
    
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
}

/**
 * Clear cached data
 *
 * @since 1.0.0
 */
function sfft_clear_cached_data() {
    // Clear WordPress object cache
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // Clear any external cache if available
    if (function_exists('w3tc_flush_all')) {
        w3tc_flush_all();
    }
    
    if (function_exists('wp_rocket_clean_domain')) {
        wp_rocket_clean_domain();
    }
    
    if (function_exists('litespeed_purge_all')) {
        litespeed_purge_all();
    }
}

/**
 * Clear cron jobs
 *
 * @since 1.0.0
 */
function sfft_clear_cron_jobs() {
    // Remove scheduled cleanup job
    wp_clear_scheduled_hook('sfft_cleanup_old_data');
    
    // Remove any other scheduled jobs
    $cron_jobs = _get_cron_array();
    
    if (!empty($cron_jobs)) {
        foreach ($cron_jobs as $timestamp => $jobs) {
            foreach ($jobs as $hook => $job_data) {
                if (strpos($hook, 'sfft_') === 0) {
                    wp_unschedule_event($timestamp, $hook);
                }
            }
        }
    }
}

/**
 * Confirm uninstall with user (for debugging)
 *
 * @since 1.0.0
 */
function sfft_confirm_uninstall() {
    // This function can be used to add confirmation dialogs
    // or additional safety checks if needed
    return true;
}

/**
 * Backup data before uninstall (optional)
 *
 * @since 1.0.0
 */
function sfft_backup_data_before_uninstall() {
    global $wpdb;
    
    // Only create backup if explicitly requested
    if (!get_option('sfft_create_backup_on_uninstall', false)) {
        return;
    }
    
    $backup_data = array();
    
    // Backup funnels
    $funnels = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ffst_funnels", ARRAY_A);
    if (!empty($funnels)) {
        $backup_data['funnels'] = $funnels;
    }
    
    // Backup funnel steps
    $steps = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ffst_funnel_steps", ARRAY_A);
    if (!empty($steps)) {
        $backup_data['funnel_steps'] = $steps;
    }
    
    // Backup settings
    $settings = array();
    $plugin_options = array(
        'sfft_cookie_consent_enabled',
        'sfft_utm_tracking_enabled',
        'sfft_data_retention_days'
    );
    
    foreach ($plugin_options as $option) {
        $settings[$option] = get_option($option);
    }
    
    $backup_data['settings'] = $settings;
    $backup_data['backup_date'] = current_time('mysql');
    
    // Save backup to uploads directory
    $upload_dir = wp_upload_dir();
    $backup_file = $upload_dir['basedir'] . '/sfft-backup-' . date('Y-m-d-H-i-s') . '.json';
    
    file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT));
    
    // Log backup location
    error_log("SFFT: Backup created at {$backup_file}");
}

/**
 * Send uninstall notification (optional)
 *
 * @since 1.0.0
 */
function sfft_send_uninstall_notification() {
    // Only send if explicitly enabled
    if (!get_option('sfft_send_uninstall_notifications', false)) {
        return;
    }
    
    $admin_email = get_option('admin_email');
    $site_name = get_option('blogname');
    $site_url = get_option('siteurl');
    
    $subject = sprintf('Simple Funnel Tracker uninstalled from %s', $site_name);
    $message = sprintf(
        'Simple Funnel Tracker has been uninstalled from %s (%s).

Uninstall details:
- Date: %s
- WordPress Version: %s
- Plugin Version: %s

All plugin data has been removed from the database.',
        $site_name,
        $site_url,
        current_time('mysql'),
        get_bloginfo('version'),
        get_option('sfft_plugin_version', 'Unknown')
    );
    
    wp_mail($admin_email, $subject, $message);
}

// Execute uninstall process
if (sfft_confirm_uninstall()) {
    // Create backup if requested
    sfft_backup_data_before_uninstall();
    
    // Send notification if requested
    sfft_send_uninstall_notification();
    
    // Perform main uninstall
    sfft_uninstall_plugin();
}

// Final cleanup - remove any remaining traces
flush_rewrite_rules();