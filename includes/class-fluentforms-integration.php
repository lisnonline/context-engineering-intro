<?php
/**
 * FluentForms Integration Class
 *
 * Handles integration with FluentForms plugin for form tracking
 *
 * @package SimpleFunnelTracker
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFT_FluentForms_Integration {
    
    /**
     * Minimum FluentForms version required
     */
    const MIN_FLUENTFORMS_VERSION = '4.0.0';
    
    /**
     * Initialize FluentForms integration
     *
     * @since 1.0.0
     * @return void
     */
    public function init() {
        // Check if FluentForms is active and compatible
        if (!$this->is_fluentforms_active()) {
            return;
        }
        
        if (!$this->is_fluentforms_compatible()) {
            add_action('admin_notices', array($this, 'show_version_notice'));
            return;
        }
        
        // Hook into FluentForms
        add_action('fluentform/loaded', array($this, 'on_fluentforms_loaded'));
        add_action('fluentform/submission_inserted', array($this, 'on_form_submission'), 10, 3);
        add_filter('fluentform/rendering_field_data_{elementName}', array($this, 'add_tracking_attributes'), 10, 2);
        
        // Add admin hooks for form management
        add_action('admin_init', array($this, 'register_form_settings'));
        add_action('wp_ajax_sfft_get_fluentforms', array($this, 'ajax_get_forms'));
    }
    
    /**
     * Check if FluentForms is active
     *
     * @since 1.0.0
     * @return bool True if active, false otherwise
     */
    public function is_fluentforms_active() {
        return class_exists('FluentForm\Framework\Foundation\Application') || 
               function_exists('fluentFormApi');
    }
    
    /**
     * Check if FluentForms version is compatible
     *
     * @since 1.0.0
     * @return bool True if compatible, false otherwise
     */
    public function is_fluentforms_compatible() {
        if (!$this->is_fluentforms_active()) {
            return false;
        }
        
        if (defined('FLUENTFORM_VERSION')) {
            return version_compare(FLUENTFORM_VERSION, self::MIN_FLUENTFORMS_VERSION, '>=');
        }
        
        // Fallback check
        return true;
    }
    
    /**
     * Called when FluentForms is loaded
     *
     * @since 1.0.0
     * @return void
     */
    public function on_fluentforms_loaded() {
        // Initialize form tracking for existing forms
        $this->setup_form_tracking();
        
        // Add custom form fields if needed
        $this->register_custom_fields();
    }
    
    /**
     * Handle form submission tracking
     *
     * @since 1.0.0
     * @param int $insertId Submission ID
     * @param array $formData Form data
     * @param object $form Form object
     * @return void
     */
    public function on_form_submission($insertId, $formData, $form) {
        // Check if tracking is enabled
        if (!get_option('sfft_utm_tracking_enabled', true)) {
            return;
        }
        
        // Check if this form is part of any funnel
        $funnel_steps = $this->get_form_funnel_steps($form->id);
        
        if (empty($funnel_steps)) {
            return;
        }
        
        // Get session information
        $session_id = $this->get_or_create_session_id();
        
        // Get UTM parameters from session or form data
        $utm_data = $this->get_utm_parameters($formData);
        
        // Track submission for each funnel step
        foreach ($funnel_steps as $step) {
            $this->track_form_submission($step, $insertId, $session_id, $utm_data, $form);
        }
    }
    
    /**
     * Add tracking attributes to form fields
     *
     * @since 1.0.0
     * @param array $data Field data
     * @param object $form Form object
     * @return array Modified field data
     */
    public function add_tracking_attributes($data, $form) {
        // Add tracking data attributes to form containers
        if (!isset($data['attributes'])) {
            $data['attributes'] = array();
        }
        
        // Add form ID for tracking
        $data['attributes']['data-sfft-form-id'] = $form->id;
        
        // Add funnel information if this form is part of a funnel
        $funnel_steps = $this->get_form_funnel_steps($form->id);
        if (!empty($funnel_steps)) {
            $funnel_ids = array_column($funnel_steps, 'funnel_id');
            $data['attributes']['data-sfft-funnel-ids'] = implode(',', array_unique($funnel_ids));
        }
        
        return $data;
    }
    
    /**
     * Register form settings for funnel integration
     *
     * @since 1.0.0
     * @return void
     */
    public function register_form_settings() {
        if (!$this->is_fluentforms_active()) {
            return;
        }
        
        // Add settings to FluentForms form settings page
        add_action('fluentform/form_settings_menu', array($this, 'add_form_settings_menu'));
        add_action('fluentform/save_form_settings', array($this, 'save_form_settings'), 10, 2);
    }
    
    /**
     * Add form settings menu for funnel tracking
     *
     * @since 1.0.0
     * @param array $menus Existing menus
     * @return array Modified menus
     */
    public function add_form_settings_menu($menus) {
        $menus['sfft_tracking'] = array(
            'title' => __('Funnel Tracking', 'simple-funnel-tracker'),
            'slug' => 'sfft_tracking',
            'hash' => 'sfft-tracking',
            'route' => '/sfft-tracking'
        );
        
        return $menus;
    }
    
    /**
     * Save form settings for funnel tracking
     *
     * @since 1.0.0
     * @param int $formId Form ID
     * @param array $settings Settings data
     * @return void
     */
    public function save_form_settings($formId, $settings) {
        if (!isset($settings['sfft_tracking'])) {
            return;
        }
        
        $tracking_settings = $settings['sfft_tracking'];
        
        // Save tracking enabled status
        update_option("sfft_form_{$formId}_tracking_enabled", 
            isset($tracking_settings['enabled']) ? true : false);
        
        // Save funnel association
        if (isset($tracking_settings['funnel_id'])) {
            update_option("sfft_form_{$formId}_funnel_id", 
                intval($tracking_settings['funnel_id']));
        }
        
        // Save step tracking settings
        if (isset($tracking_settings['track_steps'])) {
            update_option("sfft_form_{$formId}_track_steps", 
                $tracking_settings['track_steps'] ? true : false);
        }
    }
    
    /**
     * AJAX handler to get FluentForms for funnel step selection
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_get_forms() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['security'], 'sfft_admin_nonce')) {
            wp_die(__('Security check failed.', 'simple-funnel-tracker'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'simple-funnel-tracker'));
        }
        
        if (!$this->is_fluentforms_active()) {
            wp_send_json_error(__('FluentForms is not active.', 'simple-funnel-tracker'));
        }
        
        try {
            $forms = fluentFormApi('forms')->all();
            $formatted_forms = array();
            
            foreach ($forms as $form) {
                $formatted_forms[] = array(
                    'id' => $form->id,
                    'title' => $form->title,
                    'status' => $form->status,
                    'created_at' => $form->created_at,
                    'is_multi_step' => $this->is_multi_step_form($form)
                );
            }
            
            wp_send_json_success($formatted_forms);
            
        } catch (Exception $e) {
            wp_send_json_error(__('Failed to retrieve forms.', 'simple-funnel-tracker'));
        }
    }
    
    /**
     * Check if a form is multi-step
     *
     * @since 1.0.0
     * @param object $form Form object
     * @return bool True if multi-step, false otherwise
     */
    private function is_multi_step_form($form) {
        if (!isset($form->form_fields)) {
            return false;
        }
        
        $form_fields = json_decode($form->form_fields, true);
        
        if (!$form_fields) {
            return false;
        }
        
        // Look for step containers
        foreach ($form_fields as $field) {
            if (isset($field['element']) && in_array($field['element'], array('step_start', 'step_end'))) {
                return true;
            }
            
            if (isset($field['settings']['container_type']) && 
                in_array($field['settings']['container_type'], array('step_start', 'step_end'))) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get form funnel steps
     *
     * @since 1.0.0
     * @param int $form_id Form ID
     * @return array Funnel steps
     */
    private function get_form_funnel_steps($form_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ffst_funnel_steps WHERE form_id = %d",
            $form_id
        ), ARRAY_A);
    }
    
    /**
     * Get or create session ID
     *
     * @since 1.0.0
     * @return string Session ID
     */
    private function get_or_create_session_id() {
        // Try to get from cookie first
        if (isset($_COOKIE['sfft_session_id'])) {
            return sanitize_text_field($_COOKIE['sfft_session_id']);
        }
        
        // Generate new session ID
        $session_id = wp_generate_uuid4();
        
        // Set cookie (if consent allows)
        if ($this->has_tracking_consent()) {
            setcookie('sfft_session_id', $session_id, time() + (30 * DAY_IN_SECONDS), '/');
        }
        
        return $session_id;
    }
    
    /**
     * Get UTM parameters from various sources
     *
     * @since 1.0.0
     * @param array $formData Form submission data
     * @return array UTM parameters
     */
    private function get_utm_parameters($formData = array()) {
        $utm_params = array(
            'utm_source' => '',
            'utm_medium' => '',
            'utm_campaign' => '',
            'utm_content' => '',
            'utm_term' => ''
        );
        
        // Try to get from form data first (hidden fields)
        foreach ($utm_params as $param => $default) {
            if (isset($formData[$param])) {
                $utm_params[$param] = sanitize_text_field($formData[$param]);
            }
        }
        
        // Try to get from session storage (JavaScript will set these)
        if (isset($_POST['sfft_utm_data'])) {
            $posted_utm = json_decode(stripslashes($_POST['sfft_utm_data']), true);
            if (is_array($posted_utm)) {
                foreach ($utm_params as $param => $value) {
                    if (!empty($posted_utm[$param])) {
                        $utm_params[$param] = sanitize_text_field($posted_utm[$param]);
                    }
                }
            }
        }
        
        // Fallback to current URL parameters
        foreach ($utm_params as $param => $value) {
            if (empty($value) && isset($_GET[$param])) {
                $utm_params[$param] = sanitize_text_field($_GET[$param]);
            }
        }
        
        return $utm_params;
    }
    
    /**
     * Track form submission
     *
     * @since 1.0.0
     * @param array $step Funnel step
     * @param int $submission_id Submission ID
     * @param string $session_id Session ID
     * @param array $utm_data UTM parameters
     * @param object $form Form object
     * @return void
     */
    private function track_form_submission($step, $submission_id, $session_id, $utm_data, $form) {
        global $wpdb;
        
        $tracking_data = array(
            'funnel_id' => $step['funnel_id'],
            'step_id' => $step['id'],
            'session_id' => $session_id,
            'event_type' => 'form_submission',
            'form_id' => $form->id,
            'form_step_index' => null, // Will be updated by JavaScript for multi-step forms
            'utm_source' => $utm_data['utm_source'],
            'utm_medium' => $utm_data['utm_medium'],
            'utm_campaign' => $utm_data['utm_campaign'],
            'utm_content' => $utm_data['utm_content'],
            'utm_term' => $utm_data['utm_term'],
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'ip_address' => $this->get_client_ip(),
            'created_at' => current_time('mysql')
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'ffst_tracking_events',
            $tracking_data,
            array('%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Check if user has given tracking consent
     *
     * @since 1.0.0
     * @return bool True if consent given, false otherwise
     */
    private function has_tracking_consent() {
        // If cookie consent is disabled, assume consent
        if (!get_option('sfft_cookie_consent_enabled', true)) {
            return true;
        }
        
        // Check for consent cookie
        return isset($_COOKIE['sfft_consent_status']) && 
               $_COOKIE['sfft_consent_status'] === 'accepted';
    }
    
    /**
     * Get client IP address
     *
     * @since 1.0.0
     * @return string Client IP address
     */
    private function get_client_ip() {
        // Check if IP tracking is enabled
        if (!get_option('sfft_enable_ip_tracking', true)) {
            return '';
        }
        
        // Check for IP from various headers
        $ip_headers = array(
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }
    
    /**
     * Setup form tracking for existing forms
     *
     * @since 1.0.0
     * @return void
     */
    private function setup_form_tracking() {
        // This method can be used to initialize tracking for existing forms
        // if needed in the future
    }
    
    /**
     * Register custom fields for funnel tracking
     *
     * @since 1.0.0
     * @return void
     */
    private function register_custom_fields() {
        // Register hidden fields for UTM tracking if needed
        // This can be extended in future versions
    }
    
    /**
     * Show version compatibility notice
     *
     * @since 1.0.0
     * @return void
     */
    public function show_version_notice() {
        $current_version = defined('FLUENTFORM_VERSION') ? FLUENTFORM_VERSION : __('Unknown', 'simple-funnel-tracker');
        
        echo '<div class="notice notice-warning">';
        echo '<p>' . sprintf(
            __('Simple Funnel Tracker requires FluentForms version %s or higher. You are running version %s. Please update FluentForms to enable form tracking.', 'simple-funnel-tracker'),
            self::MIN_FLUENTFORMS_VERSION,
            $current_version
        ) . '</p>';
        echo '</div>';
    }
    
    /**
     * Get available forms for funnel step selection
     *
     * @since 1.0.0
     * @return array Available forms
     */
    public function get_available_forms() {
        if (!$this->is_fluentforms_active()) {
            return array();
        }
        
        global $wpdb;
        
        try {
            // Query FluentForms table directly
            $forms_table = $wpdb->prefix . 'fluentform_forms';
            
            // Check if table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '{$forms_table}'") !== $forms_table) {
                return array();
            }
            
            $forms = $wpdb->get_results(
                "SELECT id, title, status, form_fields FROM {$forms_table} WHERE status = 'published' ORDER BY title ASC"
            );
            
            $available_forms = array();
            
            foreach ($forms as $form) {
                $available_forms[] = array(
                    'id' => $form->id,
                    'title' => $form->title,
                    'status' => $form->status,
                    'is_multi_step' => $this->is_multi_step_form($form)
                );
            }
            
            return $available_forms;
            
        } catch (Exception $e) {
            error_log('SFFT: Failed to get FluentForms: ' . $e->getMessage());
            return array();
        }
    }
}