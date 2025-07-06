<?php
/**
 * Cookie Consent Class
 *
 * Handles GDPR-compliant cookie consent management
 *
 * @package SimpleFunnelTracker
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFT_Cookie_Consent {
    
    /**
     * Consent cookie name
     */
    const CONSENT_COOKIE_NAME = 'sfft_consent_status';
    
    /**
     * Consent categories cookie name
     */
    const CONSENT_CATEGORIES_COOKIE_NAME = 'sfft_consent_categories';
    
    /**
     * Consent expiry (6 months in seconds)
     */
    const CONSENT_EXPIRY = 15552000; // 6 * 30 * 24 * 60 * 60
    
    /**
     * Available consent categories
     */
    const CONSENT_CATEGORIES = array(
        'necessary' => 'Necessary',
        'analytics' => 'Analytics',
        'marketing' => 'Marketing',
        'preferences' => 'Preferences'
    );
    
    /**
     * Initialize cookie consent
     *
     * @since 1.0.0
     * @return void
     */
    public function init() {
        // Only initialize if cookie consent is enabled
        if (!get_option('sfft_cookie_consent_enabled', true)) {
            return;
        }
        
        // Add consent banner to frontend
        add_action('wp_footer', array($this, 'render_consent_banner'));
        
        // Enqueue consent scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_consent_assets'));
        
        // Handle consent form submissions
        add_action('wp_ajax_sfft_update_consent', array($this, 'ajax_update_consent'));
        add_action('wp_ajax_nopriv_sfft_update_consent', array($this, 'ajax_update_consent'));
        
        // Add consent status to localized data
        add_filter('sfft_localized_data', array($this, 'add_consent_data_to_script'));
        
        // Handle Google Consent Mode
        add_action('wp_head', array($this, 'output_google_consent_mode'), 1);
        
        // Store consent records in database
        add_action('sfft_consent_updated', array($this, 'store_consent_record'), 10, 2);
    }
    
    /**
     * Check if user has given consent for specific category
     *
     * @since 1.0.0
     * @param string $category Consent category
     * @return bool True if consent given, false otherwise
     */
    public function has_consent($category = 'analytics') {
        // If consent system is disabled, assume consent for necessary functions
        if (!get_option('sfft_cookie_consent_enabled', true)) {
            return true;
        }
        
        // Always allow necessary cookies
        if ($category === 'necessary') {
            return true;
        }
        
        // Check overall consent status
        $consent_status = $this->get_consent_status();
        if ($consent_status !== 'accepted' && $consent_status !== 'partial') {
            return false;
        }
        
        // For partial consent, check specific categories
        if ($consent_status === 'partial') {
            $categories = $this->get_consent_categories();
            return isset($categories[$category]) && $categories[$category] === true;
        }
        
        // Full consent given
        return true;
    }
    
    /**
     * Get current consent status
     *
     * @since 1.0.0
     * @return string Consent status (accepted, declined, partial, pending)
     */
    public function get_consent_status() {
        if (isset($_COOKIE[self::CONSENT_COOKIE_NAME])) {
            return sanitize_text_field($_COOKIE[self::CONSENT_COOKIE_NAME]);
        }
        return 'pending';
    }
    
    /**
     * Get consent categories
     *
     * @since 1.0.0
     * @return array Consent categories with their status
     */
    public function get_consent_categories() {
        $default_categories = array_fill_keys(array_keys(self::CONSENT_CATEGORIES), false);
        
        if (isset($_COOKIE[self::CONSENT_CATEGORIES_COOKIE_NAME])) {
            $stored_categories = json_decode(stripslashes($_COOKIE[self::CONSENT_CATEGORIES_COOKIE_NAME]), true);
            if (is_array($stored_categories)) {
                return array_merge($default_categories, $stored_categories);
            }
        }
        
        return $default_categories;
    }
    
    /**
     * Set consent status
     *
     * @since 1.0.0
     * @param string $status Consent status
     * @param array $categories Consent categories
     * @return bool True on success
     */
    public function set_consent($status, $categories = array()) {
        $expiry = time() + self::CONSENT_EXPIRY;
        
        // Set consent status cookie
        $status_set = setcookie(
            self::CONSENT_COOKIE_NAME,
            $status,
            $expiry,
            '/',
            '',
            is_ssl(),
            false // Allow JavaScript access for consent management
        );
        
        // Set categories cookie if provided
        $categories_set = true;
        if (!empty($categories)) {
            $categories_json = json_encode($categories);
            $categories_set = setcookie(
                self::CONSENT_CATEGORIES_COOKIE_NAME,
                $categories_json,
                $expiry,
                '/',
                '',
                is_ssl(),
                false
            );
        }
        
        // Update session for immediate effect
        $_COOKIE[self::CONSENT_COOKIE_NAME] = $status;
        if (!empty($categories)) {
            $_COOKIE[self::CONSENT_CATEGORIES_COOKIE_NAME] = json_encode($categories);
        }
        
        // Trigger action for other plugins/functions
        do_action('sfft_consent_updated', $status, $categories);
        
        return $status_set && $categories_set;
    }
    
    /**
     * Clear consent cookies
     *
     * @since 1.0.0
     * @return bool True on success
     */
    public function clear_consent() {
        // Clear cookies
        setcookie(self::CONSENT_COOKIE_NAME, '', time() - 3600, '/');
        setcookie(self::CONSENT_CATEGORIES_COOKIE_NAME, '', time() - 3600, '/');
        
        // Clear from current session
        unset($_COOKIE[self::CONSENT_COOKIE_NAME]);
        unset($_COOKIE[self::CONSENT_CATEGORIES_COOKIE_NAME]);
        
        do_action('sfft_consent_cleared');
        
        return true;
    }
    
    /**
     * Render consent banner
     *
     * @since 1.0.0
     * @return void
     */
    public function render_consent_banner() {
        // Don't show if user has already consented or declined
        $consent_status = $this->get_consent_status();
        if (in_array($consent_status, array('accepted', 'declined', 'partial'))) {
            return;
        }
        
        $banner_text = get_option('sfft_consent_banner_text', 
            __('This website uses cookies to track funnel performance. By continuing to use this site, you consent to our use of cookies.', 'simple-funnel-tracker')
        );
        
        $accept_text = get_option('sfft_consent_accept_text', __('Accept', 'simple-funnel-tracker'));
        $decline_text = get_option('sfft_consent_decline_text', __('Decline', 'simple-funnel-tracker'));
        $customize_text = get_option('sfft_consent_customize_text', __('Customize', 'simple-funnel-tracker'));
        $position = get_option('sfft_consent_position', 'bottom');
        $style = get_option('sfft_consent_style', 'bar');
        
        ?>
        <div id="sfft-consent-banner" class="sfft-consent-banner sfft-consent-<?php echo esc_attr($position); ?> sfft-consent-<?php echo esc_attr($style); ?>" style="display: none;">
            <div class="sfft-consent-content">
                <div class="sfft-consent-text">
                    <?php echo wp_kses_post($banner_text); ?>
                </div>
                <div class="sfft-consent-buttons">
                    <button type="button" id="sfft-consent-accept" class="sfft-consent-btn sfft-consent-accept">
                        <?php echo esc_html($accept_text); ?>
                    </button>
                    <button type="button" id="sfft-consent-decline" class="sfft-consent-btn sfft-consent-decline">
                        <?php echo esc_html($decline_text); ?>
                    </button>
                    <button type="button" id="sfft-consent-customize" class="sfft-consent-btn sfft-consent-customize">
                        <?php echo esc_html($customize_text); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Customization Modal -->
        <div id="sfft-consent-modal" class="sfft-consent-modal" style="display: none;">
            <div class="sfft-consent-modal-content">
                <div class="sfft-consent-modal-header">
                    <h3><?php echo esc_html__('Cookie Preferences', 'simple-funnel-tracker'); ?></h3>
                    <button type="button" id="sfft-consent-modal-close" class="sfft-consent-modal-close">&times;</button>
                </div>
                <div class="sfft-consent-modal-body">
                    <p><?php echo esc_html__('We use cookies to enhance your experience and analyze website traffic. Please choose which types of cookies you consent to:', 'simple-funnel-tracker'); ?></p>
                    
                    <div class="sfft-consent-categories">
                        <?php foreach (self::CONSENT_CATEGORIES as $key => $label): ?>
                            <div class="sfft-consent-category">
                                <label>
                                    <input type="checkbox" 
                                           name="sfft_consent_categories[]" 
                                           value="<?php echo esc_attr($key); ?>"
                                           <?php if ($key === 'necessary') echo 'checked disabled'; ?>
                                           class="sfft-consent-category-checkbox">
                                    <span class="sfft-consent-category-label">
                                        <?php echo esc_html(__($label, 'simple-funnel-tracker')); ?>
                                        <?php if ($key === 'necessary'): ?>
                                            <em>(<?php echo esc_html__('Required', 'simple-funnel-tracker'); ?>)</em>
                                        <?php endif; ?>
                                    </span>
                                </label>
                                <div class="sfft-consent-category-description">
                                    <?php echo esc_html($this->get_category_description($key)); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="sfft-consent-modal-footer">
                    <button type="button" id="sfft-consent-save-preferences" class="sfft-consent-btn sfft-consent-primary">
                        <?php echo esc_html__('Save Preferences', 'simple-funnel-tracker'); ?>
                    </button>
                    <button type="button" id="sfft-consent-accept-all" class="sfft-consent-btn sfft-consent-secondary">
                        <?php echo esc_html__('Accept All', 'simple-funnel-tracker'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get category description
     *
     * @since 1.0.0
     * @param string $category Category key
     * @return string Description
     */
    private function get_category_description($category) {
        $descriptions = array(
            'necessary' => __('Essential cookies for website functionality.', 'simple-funnel-tracker'),
            'analytics' => __('Cookies for tracking website usage and performance.', 'simple-funnel-tracker'),
            'marketing' => __('Cookies for targeted advertising and marketing campaigns.', 'simple-funnel-tracker'),
            'preferences' => __('Cookies for remembering your preferences and settings.', 'simple-funnel-tracker')
        );
        
        return $descriptions[$category] ?? '';
    }
    
    /**
     * Enqueue consent assets
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueue_consent_assets() {
        wp_enqueue_script(
            'sfft-cookie-consent',
            SFFT_PLUGIN_URL . 'public/js/cookie-consent.js',
            array('jquery'),
            SFFT_VERSION,
            true
        );
        
        wp_enqueue_style(
            'sfft-cookie-consent',
            SFFT_PLUGIN_URL . 'public/css/cookie-consent.css',
            array(),
            SFFT_VERSION
        );
    }
    
    /**
     * AJAX handler for updating consent
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_update_consent() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['security'], 'sfft_tracking_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'simple-funnel-tracker')));
        }
        
        $action_type = sanitize_text_field($_POST['consent_action']);
        $categories = isset($_POST['categories']) ? $_POST['categories'] : array();
        
        // Sanitize categories
        $sanitized_categories = array();
        if (is_array($categories)) {
            foreach ($categories as $category => $status) {
                if (array_key_exists($category, self::CONSENT_CATEGORIES)) {
                    $sanitized_categories[$category] = (bool) $status;
                }
            }
        }
        
        // Always allow necessary cookies
        $sanitized_categories['necessary'] = true;
        
        switch ($action_type) {
            case 'accept':
                $this->set_consent('accepted', array_fill_keys(array_keys(self::CONSENT_CATEGORIES), true));
                break;
                
            case 'decline':
                $this->set_consent('declined', array('necessary' => true));
                break;
                
            case 'customize':
                $status = !empty(array_filter($sanitized_categories)) ? 'partial' : 'declined';
                $this->set_consent($status, $sanitized_categories);
                break;
                
            default:
                wp_send_json_error(array('message' => __('Invalid consent action.', 'simple-funnel-tracker')));
        }
        
        wp_send_json_success(array(
            'message' => __('Consent updated successfully.', 'simple-funnel-tracker'),
            'status' => $this->get_consent_status(),
            'categories' => $this->get_consent_categories()
        ));
    }
    
    /**
     * Add consent data to localized script data
     *
     * @since 1.0.0
     * @param array $data Existing localized data
     * @return array Modified localized data
     */
    public function add_consent_data_to_script($data) {
        $data['consentStatus'] = $this->get_consent_status();
        $data['consentCategories'] = $this->get_consent_categories();
        $data['hasAnalyticsConsent'] = $this->has_consent('analytics');
        $data['hasMarketingConsent'] = $this->has_consent('marketing');
        
        return $data;
    }
    
    /**
     * Output Google Consent Mode script
     *
     * @since 1.0.0
     * @return void
     */
    public function output_google_consent_mode() {
        if (!get_option('sfft_google_consent_mode_enabled', false)) {
            return;
        }
        
        $analytics_consent = $this->has_consent('analytics') ? 'granted' : 'denied';
        $marketing_consent = $this->has_consent('marketing') ? 'granted' : 'denied';
        
        ?>
        <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        
        gtag('consent', 'default', {
            'analytics_storage': '<?php echo esc_js($analytics_consent); ?>',
            'ad_storage': '<?php echo esc_js($marketing_consent); ?>',
            'ad_user_data': '<?php echo esc_js($marketing_consent); ?>',
            'ad_personalization': '<?php echo esc_js($marketing_consent); ?>'
        });
        </script>
        <?php
    }
    
    /**
     * Store consent record in database
     *
     * @since 1.0.0
     * @param string $status Consent status
     * @param array $categories Consent categories
     * @return void
     */
    public function store_consent_record($status, $categories) {
        global $wpdb;
        
        $session_id = isset($_COOKIE['sfft_session_id']) ? $_COOKIE['sfft_session_id'] : '';
        if (empty($session_id)) {
            $session_id = wp_generate_uuid4();
        }
        
        $consent_data = array(
            'session_id' => $session_id,
            'consent_status' => $status,
            'consent_categories' => json_encode($categories),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'created_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', time() + self::CONSENT_EXPIRY)
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'ffst_cookie_consent',
            $consent_data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get client IP address
     *
     * @since 1.0.0
     * @return string Client IP address
     */
    private function get_client_ip() {
        $ip_headers = array(
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP', 
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }
    
    /**
     * Get consent statistics
     *
     * @since 1.0.0
     * @param int $days Number of days to look back
     * @return array Statistics
     */
    public function get_consent_statistics($days = 30) {
        global $wpdb;
        
        $date_from = date('Y-m-d', strtotime("-{$days} days"));
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_consents,
                SUM(CASE WHEN consent_status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                SUM(CASE WHEN consent_status = 'declined' THEN 1 ELSE 0 END) as declined,
                SUM(CASE WHEN consent_status = 'partial' THEN 1 ELSE 0 END) as partial
             FROM {$wpdb->prefix}ffst_cookie_consent 
             WHERE DATE(created_at) >= %s",
            $date_from
        ), ARRAY_A);
        
        return $stats ?: array(
            'total_consents' => 0,
            'accepted' => 0,
            'declined' => 0,
            'partial' => 0
        );
    }
}