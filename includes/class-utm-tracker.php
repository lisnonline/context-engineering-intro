<?php
/**
 * UTM Tracker Class
 *
 * Handles UTM parameter capture, storage, and management
 *
 * @package SimpleFunnelTracker
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFT_UTM_Tracker {
    
    /**
     * UTM parameter names
     */
    const UTM_PARAMETERS = array(
        'utm_source',
        'utm_medium', 
        'utm_campaign',
        'utm_content',
        'utm_term'
    );
    
    /**
     * Cookie name for UTM storage
     */
    const UTM_COOKIE_NAME = 'sfft_utm_params';
    
    /**
     * UTM cookie expiration (30 days)
     */
    const UTM_COOKIE_EXPIRY = 2592000; // 30 * 24 * 60 * 60
    
    /**
     * Initialize UTM tracker
     *
     * @since 1.0.0
     * @return void
     */
    public function init() {
        // Capture UTM parameters on page load
        add_action('init', array($this, 'capture_utm_parameters'));
        
        // Add UTM data to localized script data
        add_filter('sfft_localized_data', array($this, 'add_utm_data_to_script'));
        
        // Handle UTM parameter persistence
        add_action('wp_head', array($this, 'output_utm_persistence_script'));
        
        // Add admin AJAX handlers
        add_action('wp_ajax_sfft_get_utm_report', array($this, 'ajax_get_utm_report'));
        add_action('wp_ajax_sfft_clear_utm_data', array($this, 'ajax_clear_utm_data'));
    }
    
    /**
     * Capture UTM parameters from current request
     *
     * @since 1.0.0
     * @return array Captured UTM parameters
     */
    public function capture_utm_parameters() {
        if (!get_option('sfft_utm_tracking_enabled', true)) {
            return array();
        }
        
        $utm_params = array();
        $has_new_params = false;
        
        // Check URL parameters
        foreach (self::UTM_PARAMETERS as $param) {
            if (isset($_GET[$param]) && !empty($_GET[$param])) {
                $utm_params[$param] = sanitize_text_field($_GET[$param]);
                $has_new_params = true;
            }
        }
        
        // If we have new UTM parameters, store them
        if ($has_new_params && $this->has_tracking_consent()) {
            $this->store_utm_parameters($utm_params);
        }
        
        // Return current UTM parameters (new or stored)
        return $this->get_utm_parameters();
    }
    
    /**
     * Get stored UTM parameters
     *
     * @since 1.0.0
     * @return array UTM parameters
     */
    public function get_utm_parameters() {
        $utm_params = array_fill_keys(self::UTM_PARAMETERS, '');
        
        // Try to get from cookie first
        if (isset($_COOKIE[self::UTM_COOKIE_NAME])) {
            $stored_params = json_decode(stripslashes($_COOKIE[self::UTM_COOKIE_NAME]), true);
            if (is_array($stored_params)) {
                $utm_params = array_merge($utm_params, $stored_params);
            }
        }
        
        // Fallback to current URL parameters
        foreach (self::UTM_PARAMETERS as $param) {
            if (isset($_GET[$param]) && !empty($_GET[$param])) {
                $utm_params[$param] = sanitize_text_field($_GET[$param]);
            }
        }
        
        return $utm_params;
    }
    
    /**
     * Store UTM parameters in cookie
     *
     * @since 1.0.0
     * @param array $utm_params UTM parameters to store
     * @return bool True on success, false on failure
     */
    private function store_utm_parameters($utm_params) {
        if (!$this->has_tracking_consent()) {
            return false;
        }
        
        // Get existing parameters
        $existing_params = $this->get_utm_parameters();
        
        // Merge with new parameters (new ones take precedence)
        $merged_params = array_merge($existing_params, $utm_params);
        
        // Store in cookie
        $cookie_value = json_encode($merged_params);
        $expiry = time() + self::UTM_COOKIE_EXPIRY;
        
        return setcookie(
            self::UTM_COOKIE_NAME,
            $cookie_value,
            $expiry,
            '/',
            '',
            is_ssl(),
            true // HTTP only
        );
    }
    
    /**
     * Clear stored UTM parameters
     *
     * @since 1.0.0
     * @return bool True on success
     */
    public function clear_utm_parameters() {
        // Clear cookie
        setcookie(self::UTM_COOKIE_NAME, '', time() - 3600, '/');
        unset($_COOKIE[self::UTM_COOKIE_NAME]);
        
        return true;
    }
    
    /**
     * Add UTM data to localized script data
     *
     * @since 1.0.0
     * @param array $data Existing localized data
     * @return array Modified localized data
     */
    public function add_utm_data_to_script($data) {
        $utm_params = $this->get_utm_parameters();
        
        $data['utmParams'] = $utm_params;
        $data['hasUtmParams'] = !empty(array_filter($utm_params));
        
        return $data;
    }
    
    /**
     * Output UTM persistence script in head
     *
     * @since 1.0.0
     * @return void
     */
    public function output_utm_persistence_script() {
        if (!get_option('sfft_utm_tracking_enabled', true)) {
            return;
        }
        
        $utm_params = $this->get_utm_parameters();
        $has_utm = !empty(array_filter($utm_params));
        
        if (!$has_utm) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        (function() {
            // Store UTM parameters in sessionStorage for JavaScript access
            if (typeof(Storage) !== "undefined" && window.sessionStorage) {
                var utmParams = <?php echo json_encode($utm_params); ?>;
                sessionStorage.setItem('sfft_utm_params', JSON.stringify(utmParams));
            }
        })();
        </script>
        <?php
    }
    
    /**
     * Get UTM report data
     *
     * @since 1.0.0
     * @param array $args Query arguments
     * @return array Report data
     */
    public function get_utm_report($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d'),
            'funnel_id' => 0,
            'limit' => 100
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where_conditions = array();
        $prepare_values = array();
        
        // Date range
        $where_conditions[] = "DATE(created_at) BETWEEN %s AND %s";
        $prepare_values[] = $args['date_from'];
        $prepare_values[] = $args['date_to'];
        
        // Funnel filter
        if ($args['funnel_id'] > 0) {
            $where_conditions[] = "funnel_id = %d";
            $prepare_values[] = $args['funnel_id'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get UTM performance data
        $sql = "SELECT 
                    utm_source,
                    utm_medium,
                    utm_campaign,
                    utm_content,
                    utm_term,
                    COUNT(DISTINCT session_id) as unique_sessions,
                    COUNT(*) as total_events,
                    DATE(created_at) as event_date
                FROM {$wpdb->prefix}ffst_tracking_events 
                WHERE $where_clause
                GROUP BY utm_source, utm_medium, utm_campaign, utm_content, utm_term, DATE(created_at)
                ORDER BY total_events DESC, event_date DESC
                LIMIT %d";
        
        $prepare_values[] = $args['limit'];
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $prepare_values));
        
        // Get summary statistics
        $summary_sql = "SELECT 
                           COUNT(DISTINCT session_id) as total_sessions,
                           COUNT(*) as total_events,
                           COUNT(DISTINCT CASE WHEN utm_source != '' THEN session_id END) as sessions_with_utm
                       FROM {$wpdb->prefix}ffst_tracking_events 
                       WHERE $where_clause";
        
        // Remove the limit parameter for summary
        array_pop($prepare_values);
        $summary = $wpdb->get_row($wpdb->prepare($summary_sql, $prepare_values));
        
        return array(
            'data' => $results,
            'summary' => $summary,
            'args' => $args
        );
    }
    
    /**
     * AJAX handler for UTM report
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_get_utm_report() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['security'], 'sfft_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'simple-funnel-tracker')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'simple-funnel-tracker')));
        }
        
        $args = array(
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
            'funnel_id' => intval($_POST['funnel_id'] ?? 0),
            'limit' => intval($_POST['limit'] ?? 100)
        );
        
        $report = $this->get_utm_report($args);
        
        wp_send_json_success($report);
    }
    
    /**
     * AJAX handler for clearing UTM data
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_clear_utm_data() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['security'], 'sfft_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'simple-funnel-tracker')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'simple-funnel-tracker')));
        }
        
        $this->clear_utm_parameters();
        
        wp_send_json_success(array('message' => __('UTM data cleared successfully.', 'simple-funnel-tracker')));
    }
    
    /**
     * Get UTM parameter statistics
     *
     * @since 1.0.0
     * @param int $days Number of days to look back
     * @return array Statistics
     */
    public function get_utm_statistics($days = 30) {
        global $wpdb;
        
        $date_from = date('Y-m-d', strtotime("-{$days} days"));
        
        // Get top sources
        $top_sources = $wpdb->get_results($wpdb->prepare(
            "SELECT utm_source, COUNT(DISTINCT session_id) as sessions
             FROM {$wpdb->prefix}ffst_tracking_events 
             WHERE utm_source != '' AND DATE(created_at) >= %s
             GROUP BY utm_source 
             ORDER BY sessions DESC 
             LIMIT 10",
            $date_from
        ));
        
        // Get top mediums
        $top_mediums = $wpdb->get_results($wpdb->prepare(
            "SELECT utm_medium, COUNT(DISTINCT session_id) as sessions
             FROM {$wpdb->prefix}ffst_tracking_events 
             WHERE utm_medium != '' AND DATE(created_at) >= %s
             GROUP BY utm_medium 
             ORDER BY sessions DESC 
             LIMIT 10",
            $date_from
        ));
        
        // Get top campaigns
        $top_campaigns = $wpdb->get_results($wpdb->prepare(
            "SELECT utm_campaign, COUNT(DISTINCT session_id) as sessions
             FROM {$wpdb->prefix}ffst_tracking_events 
             WHERE utm_campaign != '' AND DATE(created_at) >= %s
             GROUP BY utm_campaign 
             ORDER BY sessions DESC 
             LIMIT 10",
            $date_from
        ));
        
        return array(
            'top_sources' => $top_sources,
            'top_mediums' => $top_mediums,
            'top_campaigns' => $top_campaigns,
            'period_days' => $days
        );
    }
    
    /**
     * Generate UTM tracking URL
     *
     * @since 1.0.0
     * @param string $base_url Base URL
     * @param array $utm_params UTM parameters
     * @return string URL with UTM parameters
     */
    public function generate_utm_url($base_url, $utm_params) {
        $url = $base_url;
        $query_params = array();
        
        foreach (self::UTM_PARAMETERS as $param) {
            if (!empty($utm_params[$param])) {
                $query_params[$param] = urlencode($utm_params[$param]);
            }
        }
        
        if (!empty($query_params)) {
            $separator = strpos($url, '?') !== false ? '&' : '?';
            $url .= $separator . http_build_query($query_params);
        }
        
        return $url;
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
     * Clean old UTM data from database
     *
     * @since 1.0.0
     * @param int $days Number of days to keep
     * @return int Number of records deleted
     */
    public function cleanup_old_utm_data($days = 90) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}ffst_tracking_events 
             WHERE created_at < %s AND utm_source != ''",
            $cutoff_date
        ));
        
        return intval($deleted);
    }
    
    /**
     * Get available UTM values for form dropdowns
     *
     * @since 1.0.0
     * @param string $param UTM parameter name
     * @return array Available values
     */
    public function get_available_utm_values($param) {
        global $wpdb;
        
        if (!in_array($param, self::UTM_PARAMETERS)) {
            return array();
        }
        
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT {$param} 
             FROM {$wpdb->prefix}ffst_tracking_events 
             WHERE {$param} != '' 
             ORDER BY {$param} ASC 
             LIMIT 100",
            $param
        ));
        
        return $results ?: array();
    }
}