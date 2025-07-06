<?php
/**
 * AJAX Tracking Handler Class
 *
 * Handles AJAX requests for tracking form steps, page views, and UTM data
 *
 * @package SimpleFunnelTracker
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFT_Tracking_Handler {
    
    /**
     * Initialize tracking handler
     *
     * @since 1.0.0
     * @return void
     */
    public function init() {
        // Register AJAX handlers for logged-in users
        add_action('wp_ajax_ffst_track_form_step', array($this, 'handle_track_form_step'));
        add_action('wp_ajax_track_page_view', array($this, 'handle_track_page_view'));
        add_action('wp_ajax_ffst_update_utm_table', array($this, 'handle_update_utm_table'));
        
        // Register AJAX handlers for non-logged-in users (public tracking)
        add_action('wp_ajax_nopriv_ffst_track_form_step', array($this, 'handle_track_form_step'));
        add_action('wp_ajax_nopriv_track_page_view', array($this, 'handle_track_page_view'));
        add_action('wp_ajax_nopriv_ffst_update_utm_table', array($this, 'handle_update_utm_table'));
        
        // Additional admin AJAX handlers
        add_action('wp_ajax_sfft_get_funnel_analytics', array($this, 'handle_get_funnel_analytics'));
        add_action('wp_ajax_sfft_export_tracking_data', array($this, 'handle_export_tracking_data'));
    }
    
    /**
     * Handle form step tracking AJAX request
     *
     * @since 1.0.0
     * @return void
     */
    public function handle_track_form_step() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['security'], 'sfft_tracking_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'simple-funnel-tracker')));
        }
        
        // Check if tracking is enabled
        if (!get_option('sfft_utm_tracking_enabled', true)) {
            wp_send_json_error(array('message' => __('Tracking is disabled.', 'simple-funnel-tracker')));
        }
        
        // Check cookie consent
        if (!$this->has_tracking_consent()) {
            wp_send_json_error(array('message' => __('Tracking consent required.', 'simple-funnel-tracker')));
        }
        
        // Sanitize and validate input
        $form_id = intval($_POST['form_id']);
        $step_index = intval($_POST['step_index']);
        $total_steps = intval($_POST['total_steps']);
        $page_id = intval($_POST['page_id']);
        
        if ($form_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid form ID.', 'simple-funnel-tracker')));
        }
        
        if ($step_index <= 0) {
            wp_send_json_error(array('message' => __('Invalid step index.', 'simple-funnel-tracker')));
        }
        
        // Get UTM parameters
        $utm_data = $this->sanitize_utm_parameters($_POST);
        
        // Get session ID
        $session_id = $this->get_or_create_session_id();
        
        // Find funnel steps for this form
        $funnel_steps = $this->get_form_funnel_steps($form_id);
        
        if (empty($funnel_steps)) {
            wp_send_json_error(array('message' => __('Form is not part of any funnel.', 'simple-funnel-tracker')));
        }
        
        // Track step for each funnel
        $tracked_events = array();
        foreach ($funnel_steps as $step) {
            $event_id = $this->track_form_step_event($step, $step_index, $total_steps, $session_id, $utm_data, $page_id);
            if ($event_id) {
                $tracked_events[] = $event_id;
            }
        }
        
        if (!empty($tracked_events)) {
            wp_send_json_success(array(
                'message' => __('Form step tracked successfully.', 'simple-funnel-tracker'),
                'event_ids' => $tracked_events,
                'session_id' => $session_id
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to track form step.', 'simple-funnel-tracker')));
        }
    }
    
    /**
     * Handle page view tracking AJAX request
     *
     * @since 1.0.0
     * @return void
     */
    public function handle_track_page_view() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['security'], 'sfft_tracking_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'simple-funnel-tracker')));
        }
        
        // Check if tracking is enabled
        if (!get_option('sfft_utm_tracking_enabled', true)) {
            wp_send_json_error(array('message' => __('Tracking is disabled.', 'simple-funnel-tracker')));
        }
        
        // Check cookie consent
        if (!$this->has_tracking_consent()) {
            wp_send_json_error(array('message' => __('Tracking consent required.', 'simple-funnel-tracker')));
        }
        
        // Sanitize and validate input
        $page_id = intval($_POST['page_id']);
        
        if ($page_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid page ID.', 'simple-funnel-tracker')));
        }
        
        // Get UTM parameters
        $utm_data = $this->sanitize_utm_parameters($_POST);
        
        // Get session ID
        $session_id = $this->get_or_create_session_id();
        
        // Find funnel steps for this page
        $funnel_steps = $this->get_page_funnel_steps($page_id);
        
        // Track page view for each funnel step
        $tracked_events = array();
        foreach ($funnel_steps as $step) {
            $event_id = $this->track_page_view_event($step, $session_id, $utm_data, $page_id);
            if ($event_id) {
                $tracked_events[] = $event_id;
            }
        }
        
        wp_send_json_success(array(
            'message' => __('Page view tracked successfully.', 'simple-funnel-tracker'),
            'event_ids' => $tracked_events,
            'session_id' => $session_id,
            'funnel_steps_found' => count($funnel_steps)
        ));
    }
    
    /**
     * Handle UTM table update AJAX request
     *
     * @since 1.0.0
     * @return void
     */
    public function handle_update_utm_table() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['security'], 'sfft_tracking_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'simple-funnel-tracker')));
        }
        
        // Check user capabilities for admin functions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'simple-funnel-tracker')));
        }
        
        // Sanitize input
        $funnel_name = sanitize_text_field($_POST['funnel_name']);
        $utm_data = $this->sanitize_utm_parameters($_POST);
        
        if (empty($funnel_name)) {
            wp_send_json_error(array('message' => __('Funnel name is required.', 'simple-funnel-tracker')));
        }
        
        // Get funnel by name
        $funnel = $this->get_funnel_by_name($funnel_name);
        
        if (!$funnel) {
            wp_send_json_error(array('message' => __('Funnel not found.', 'simple-funnel-tracker')));
        }
        
        // Generate UTM table HTML
        $table_html = $this->generate_utm_table($funnel['id'], $utm_data);
        
        wp_send_json_success(array(
            'html' => $table_html,
            'funnel_id' => $funnel['id']
        ));
    }
    
    /**
     * Handle get funnel analytics AJAX request
     *
     * @since 1.0.0
     * @return void
     */
    public function handle_get_funnel_analytics() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['security'], 'sfft_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'simple-funnel-tracker')));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'simple-funnel-tracker')));
        }
        
        $funnel_id = intval($_POST['funnel_id']);
        $date_from = sanitize_text_field($_POST['date_from']);
        $date_to = sanitize_text_field($_POST['date_to']);
        
        if ($funnel_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid funnel ID.', 'simple-funnel-tracker')));
        }
        
        // Get analytics data
        $analytics = $this->get_funnel_analytics($funnel_id, $date_from, $date_to);
        
        wp_send_json_success($analytics);
    }
    
    /**
     * Handle export tracking data AJAX request
     *
     * @since 1.0.0
     * @return void
     */
    public function handle_export_tracking_data() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['security'], 'sfft_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'simple-funnel-tracker')));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'simple-funnel-tracker')));
        }
        
        $funnel_id = intval($_POST['funnel_id']);
        $format = sanitize_text_field($_POST['format']);
        
        if (!in_array($format, array('csv', 'json'))) {
            $format = 'csv';
        }
        
        // Export data
        $export_data = $this->export_funnel_data($funnel_id, $format);
        
        wp_send_json_success(array(
            'data' => $export_data,
            'format' => $format,
            'filename' => "funnel-{$funnel_id}-data.{$format}"
        ));
    }
    
    /**
     * Sanitize UTM parameters from POST data
     *
     * @since 1.0.0
     * @param array $data POST data
     * @return array Sanitized UTM parameters
     */
    private function sanitize_utm_parameters($data) {
        return array(
            'utm_source' => sanitize_text_field($data['utm_source'] ?? ''),
            'utm_medium' => sanitize_text_field($data['utm_medium'] ?? ''),
            'utm_campaign' => sanitize_text_field($data['utm_campaign'] ?? ''),
            'utm_content' => sanitize_text_field($data['utm_content'] ?? ''),
            'utm_term' => sanitize_text_field($data['utm_term'] ?? '')
        );
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
        
        // Try to get from POST data (if sent by JavaScript)
        if (isset($_POST['session_id']) && !empty($_POST['session_id'])) {
            return sanitize_text_field($_POST['session_id']);
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
     * Get form funnel steps
     *
     * @since 1.0.0
     * @param int $form_id Form ID
     * @return array Funnel steps
     */
    private function get_form_funnel_steps($form_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT fs.*, f.name as funnel_name 
             FROM {$wpdb->prefix}ffst_funnel_steps fs 
             JOIN {$wpdb->prefix}ffst_funnels f ON fs.funnel_id = f.id 
             WHERE fs.form_id = %d AND f.status = 'active'",
            $form_id
        ), ARRAY_A);
    }
    
    /**
     * Get page funnel steps
     *
     * @since 1.0.0
     * @param int $page_id Page ID
     * @return array Funnel steps
     */
    private function get_page_funnel_steps($page_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT fs.*, f.name as funnel_name 
             FROM {$wpdb->prefix}ffst_funnel_steps fs 
             JOIN {$wpdb->prefix}ffst_funnels f ON fs.funnel_id = f.id 
             WHERE fs.page_id = %d AND f.status = 'active'",
            $page_id
        ), ARRAY_A);
    }
    
    /**
     * Track form step event
     *
     * @since 1.0.0
     * @param array $step Funnel step
     * @param int $step_index Current step index
     * @param int $total_steps Total number of steps
     * @param string $session_id Session ID
     * @param array $utm_data UTM parameters
     * @param int $page_id Page ID
     * @return int|false Event ID on success, false on failure
     */
    private function track_form_step_event($step, $step_index, $total_steps, $session_id, $utm_data, $page_id) {
        global $wpdb;
        
        $tracking_data = array(
            'funnel_id' => $step['funnel_id'],
            'step_id' => $step['id'],
            'session_id' => $session_id,
            'event_type' => 'form_step',
            'page_id' => $page_id,
            'form_id' => $step['form_id'],
            'form_step_index' => $step_index,
            'utm_source' => $utm_data['utm_source'],
            'utm_medium' => $utm_data['utm_medium'],
            'utm_campaign' => $utm_data['utm_campaign'],
            'utm_content' => $utm_data['utm_content'],
            'utm_term' => $utm_data['utm_term'],
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'ip_address' => $this->get_client_ip(),
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'ffst_tracking_events',
            $tracking_data,
            array('%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Track page view event
     *
     * @since 1.0.0
     * @param array $step Funnel step
     * @param string $session_id Session ID
     * @param array $utm_data UTM parameters
     * @param int $page_id Page ID
     * @return int|false Event ID on success, false on failure
     */
    private function track_page_view_event($step, $session_id, $utm_data, $page_id) {
        global $wpdb;
        
        $tracking_data = array(
            'funnel_id' => $step['funnel_id'],
            'step_id' => $step['id'],
            'session_id' => $session_id,
            'event_type' => 'page_view',
            'page_id' => $page_id,
            'form_id' => null,
            'form_step_index' => null,
            'utm_source' => $utm_data['utm_source'],
            'utm_medium' => $utm_data['utm_medium'],
            'utm_campaign' => $utm_data['utm_campaign'],
            'utm_content' => $utm_data['utm_content'],
            'utm_term' => $utm_data['utm_term'],
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'ip_address' => $this->get_client_ip(),
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'ffst_tracking_events',
            $tracking_data,
            array('%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get funnel by name
     *
     * @since 1.0.0
     * @param string $name Funnel name
     * @return array|null Funnel data
     */
    private function get_funnel_by_name($name) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ffst_funnels WHERE name = %s",
            $name
        ), ARRAY_A);
    }
    
    /**
     * Generate UTM table HTML
     *
     * @since 1.0.0
     * @param int $funnel_id Funnel ID
     * @param array $utm_data UTM filter parameters
     * @return string HTML table
     */
    private function generate_utm_table($funnel_id, $utm_data) {
        global $wpdb;
        
        // Build WHERE clause for UTM filtering
        $where_conditions = array('funnel_id = %d');
        $prepare_values = array($funnel_id);
        
        foreach ($utm_data as $param => $value) {
            if (!empty($value)) {
                $where_conditions[] = "$param = %s";
                $prepare_values[] = $value;
            }
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get tracking data
        $sql = "SELECT utm_source, utm_medium, utm_campaign, utm_content, COUNT(*) as count 
                FROM {$wpdb->prefix}ffst_tracking_events 
                WHERE $where_clause 
                GROUP BY utm_source, utm_medium, utm_campaign, utm_content 
                ORDER BY count DESC";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $prepare_values));
        
        // Generate HTML table
        $html = '<table class="wp-list-table widefat fixed striped">';
        $html .= '<thead><tr>';
        $html .= '<th>' . __('Source', 'simple-funnel-tracker') . '</th>';
        $html .= '<th>' . __('Medium', 'simple-funnel-tracker') . '</th>';
        $html .= '<th>' . __('Campaign', 'simple-funnel-tracker') . '</th>';
        $html .= '<th>' . __('Content', 'simple-funnel-tracker') . '</th>';
        $html .= '<th>' . __('Count', 'simple-funnel-tracker') . '</th>';
        $html .= '</tr></thead><tbody>';
        
        if (empty($results)) {
            $html .= '<tr><td colspan="5">' . __('No data found.', 'simple-funnel-tracker') . '</td></tr>';
        } else {
            foreach ($results as $row) {
                $html .= '<tr>';
                $html .= '<td>' . esc_html($row->utm_source ?: '-') . '</td>';
                $html .= '<td>' . esc_html($row->utm_medium ?: '-') . '</td>';
                $html .= '<td>' . esc_html($row->utm_campaign ?: '-') . '</td>';
                $html .= '<td>' . esc_html($row->utm_content ?: '-') . '</td>';
                $html .= '<td>' . esc_html($row->count) . '</td>';
                $html .= '</tr>';
            }
        }
        
        $html .= '</tbody></table>';
        
        return $html;
    }
    
    /**
     * Get funnel analytics data
     *
     * @since 1.0.0
     * @param int $funnel_id Funnel ID
     * @param string $date_from Start date
     * @param string $date_to End date
     * @return array Analytics data
     */
    private function get_funnel_analytics($funnel_id, $date_from = '', $date_to = '') {
        global $wpdb;
        
        // Default date range (last 30 days)
        if (empty($date_from)) {
            $date_from = date('Y-m-d', strtotime('-30 days'));
        }
        if (empty($date_to)) {
            $date_to = date('Y-m-d');
        }
        
        // Get step statistics
        $sql = "SELECT 
                    fs.step_order,
                    fs.step_name,
                    fs.step_type,
                    COUNT(DISTINCT te.session_id) as unique_visitors,
                    COUNT(te.id) as total_events
                FROM {$wpdb->prefix}ffst_funnel_steps fs
                LEFT JOIN {$wpdb->prefix}ffst_tracking_events te ON fs.id = te.step_id 
                    AND DATE(te.created_at) BETWEEN %s AND %s
                WHERE fs.funnel_id = %d
                GROUP BY fs.id, fs.step_order, fs.step_name, fs.step_type
                ORDER BY fs.step_order ASC";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $date_from, $date_to, $funnel_id));
        
        return array(
            'funnel_id' => $funnel_id,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'steps' => $results
        );
    }
    
    /**
     * Export funnel data
     *
     * @since 1.0.0
     * @param int $funnel_id Funnel ID
     * @param string $format Export format (csv, json)
     * @return string Exported data
     */
    private function export_funnel_data($funnel_id, $format) {
        global $wpdb;
        
        $sql = "SELECT * FROM {$wpdb->prefix}ffst_tracking_events WHERE funnel_id = %d ORDER BY created_at DESC";
        $results = $wpdb->get_results($wpdb->prepare($sql, $funnel_id), ARRAY_A);
        
        if ($format === 'json') {
            return json_encode($results, JSON_PRETTY_PRINT);
        } else {
            // CSV format
            if (empty($results)) {
                return '';
            }
            
            $csv = '';
            
            // Header row
            $csv .= implode(',', array_keys($results[0])) . "\n";
            
            // Data rows
            foreach ($results as $row) {
                $csv .= implode(',', array_map(function($value) {
                    return '"' . str_replace('"', '""', $value) . '"';
                }, $row)) . "\n";
            }
            
            return $csv;
        }
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
}