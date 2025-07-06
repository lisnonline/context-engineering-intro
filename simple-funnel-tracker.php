<?php
/**
 * Simple Funnel Tracker
 *
 * @package           SimpleFunnelTracker
 * @author            Simple Funnel Tracker Team
 * @copyright         2025 Simple Funnel Tracker
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Simple Funnel Tracker
 * Plugin URI:        https://github.com/simplefunneltracker/simple-funnel-tracker
 * Description:       Track funnel performance with FluentForms integration, UTM parameter tracking, and GDPR-compliant analytics.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            Simple Funnel Tracker Team
 * Author URI:        https://github.com/simplefunneltracker
 * Text Domain:       simple-funnel-tracker
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('SFFT_VERSION', '1.0.0');
define('SFFT_PLUGIN_FILE', __FILE__);
define('SFFT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SFFT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SFFT_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('SFFT_INCLUDES_DIR', SFFT_PLUGIN_DIR . 'includes/');
define('SFFT_ADMIN_DIR', SFFT_PLUGIN_DIR . 'admin/');
define('SFFT_PUBLIC_DIR', SFFT_PLUGIN_DIR . 'public/');

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    if (strpos($class, 'SFFT_') !== 0) {
        return;
    }
    
    $class_name = strtolower(str_replace('_', '-', str_replace('SFFT_', '', $class)));
    $file_path = SFFT_INCLUDES_DIR . 'class-' . $class_name . '.php';
    
    if (file_exists($file_path)) {
        require_once $file_path;
    }
});

// Plugin activation hook
register_activation_hook(__FILE__, array('SFFT_Activator', 'activate'));

// Plugin deactivation hook
register_deactivation_hook(__FILE__, array('SFFT_Activator', 'deactivate'));

// Plugin initialization
add_action('plugins_loaded', 'sfft_init');

function sfft_init() {
    // Load text domain for internationalization
    load_plugin_textdomain('simple-funnel-tracker', false, dirname(SFFT_PLUGIN_BASENAME) . '/languages/');
    
    // Initialize plugin components
    if (class_exists('SFFT_Admin_Interface')) {
        $admin = new SFFT_Admin_Interface();
        $admin->init();
    }
    
    if (class_exists('SFFT_Tracking_Handler')) {
        $tracking = new SFFT_Tracking_Handler();
        $tracking->init();
    }
    
    if (class_exists('SFFT_FluentForms_Integration')) {
        $fluentforms = new SFFT_FluentForms_Integration();
        $fluentforms->init();
    }
    
    if (class_exists('SFFT_UTM_Tracker')) {
        $utm_tracker = new SFFT_UTM_Tracker();
        $utm_tracker->init();
    }
    
    if (class_exists('SFFT_Cookie_Consent')) {
        $cookie_consent = new SFFT_Cookie_Consent();
        $cookie_consent->init();
    }
}

// Initialize frontend scripts and styles
add_action('wp_enqueue_scripts', 'sfft_enqueue_public_scripts');

function sfft_enqueue_public_scripts() {
    // Enqueue tracking script
    wp_enqueue_script(
        'sfft-tracking',
        SFFT_PLUGIN_URL . 'public/js/tracking.js',
        array('jquery'),
        SFFT_VERSION,
        true
    );
    
    // Enqueue UTM capture script
    wp_enqueue_script(
        'sfft-utm-capture',
        SFFT_PLUGIN_URL . 'public/js/utm-capture.js',
        array('jquery'),
        SFFT_VERSION,
        true
    );
    
    // Enqueue cookie consent script
    wp_enqueue_script(
        'sfft-cookie-consent',
        SFFT_PLUGIN_URL . 'public/js/cookie-consent.js',
        array('jquery'),
        SFFT_VERSION,
        true
    );
    
    // Enqueue public styles
    wp_enqueue_style(
        'sfft-tracking-styles',
        SFFT_PLUGIN_URL . 'public/css/tracking.css',
        array(),
        SFFT_VERSION
    );
    
    // Localize script for AJAX
    wp_localize_script('sfft-tracking', 'sfftData', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'security' => wp_create_nonce('sfft_tracking_nonce'),
        'currentPageId' => get_the_ID(),
        'cookieConsentEnabled' => get_option('sfft_cookie_consent_enabled', true),
        'utmTrackingEnabled' => get_option('sfft_utm_tracking_enabled', true),
        'version' => SFFT_VERSION
    ));
}

// Initialize admin scripts and styles
add_action('admin_enqueue_scripts', 'sfft_enqueue_admin_scripts');

function sfft_enqueue_admin_scripts($hook) {
    // Only load on our plugin pages
    if (strpos($hook, 'sfft') === false && strpos($hook, 'simple-funnel-tracker') === false) {
        return;
    }
    
    // Enqueue admin styles
    wp_enqueue_style(
        'sfft-admin-styles',
        SFFT_PLUGIN_URL . 'admin/css/admin.css',
        array(),
        SFFT_VERSION
    );
    
    // Enqueue admin scripts
    wp_enqueue_script(
        'sfft-admin',
        SFFT_PLUGIN_URL . 'admin/js/admin.js',
        array('jquery'),
        SFFT_VERSION,
        true
    );
    
    wp_enqueue_script(
        'sfft-funnel-management',
        SFFT_PLUGIN_URL . 'admin/js/funnel-management.js',
        array('jquery'),
        SFFT_VERSION,
        true
    );
    
    // Localize script for admin AJAX
    wp_localize_script('sfft-admin', 'sfftAdmin', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'security' => wp_create_nonce('sfft_admin_nonce'),
        'strings' => array(
            'confirmDelete' => __('Are you sure you want to delete this funnel?', 'simple-funnel-tracker'),
            'savingFunnel' => __('Saving funnel...', 'simple-funnel-tracker'),
            'errorSaving' => __('Error saving funnel. Please try again.', 'simple-funnel-tracker'),
            'funnelSaved' => __('Funnel saved successfully!', 'simple-funnel-tracker')
        )
    ));
}

// Add settings link to plugin page
add_filter('plugin_action_links_' . SFFT_PLUGIN_BASENAME, 'sfft_add_settings_link');

function sfft_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=sfft-settings') . '">' . __('Settings', 'simple-funnel-tracker') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// Plugin row meta links
add_filter('plugin_row_meta', 'sfft_plugin_row_meta', 10, 2);

function sfft_plugin_row_meta($links, $file) {
    if (SFFT_PLUGIN_BASENAME === $file) {
        $row_meta = array(
            'docs' => '<a href="https://github.com/simplefunneltracker/simple-funnel-tracker/wiki" target="_blank">' . __('Documentation', 'simple-funnel-tracker') . '</a>',
            'support' => '<a href="https://github.com/simplefunneltracker/simple-funnel-tracker/issues" target="_blank">' . __('Support', 'simple-funnel-tracker') . '</a>',
        );
        return array_merge($links, $row_meta);
    }
    return $links;
}