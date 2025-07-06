<?php
/**
 * Admin Interface Class
 *
 * Handles WordPress admin UI components and pages
 *
 * @package SimpleFunnelTracker
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include WP_List_Table if not already loaded
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class SFFT_Admin_Interface {
    
    /**
     * Funnel Manager instance
     *
     * @var SFFT_Funnel_Manager
     */
    private $funnel_manager;
    
    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->funnel_manager = new SFFT_Funnel_Manager();
    }
    
    /**
     * Initialize admin interface
     *
     * @since 1.0.0
     * @return void
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_post_sfft_save_funnel', array($this, 'handle_save_funnel'));
        add_action('admin_post_sfft_delete_funnel', array($this, 'handle_delete_funnel'));
        add_action('wp_ajax_sfft_get_funnel_data', array($this, 'ajax_get_funnel_data'));
    }
    
    /**
     * Add admin menu pages
     *
     * @since 1.0.0
     * @return void
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('Simple Funnel Tracker', 'simple-funnel-tracker'),
            __('Funnel Tracker', 'simple-funnel-tracker'),
            'manage_options',
            'sfft-funnels',
            array($this, 'render_funnels_page'),
            'dashicons-analytics',
            30
        );
        
        // Funnels submenu
        add_submenu_page(
            'sfft-funnels',
            __('All Funnels', 'simple-funnel-tracker'),
            __('All Funnels', 'simple-funnel-tracker'),
            'manage_options',
            'sfft-funnels',
            array($this, 'render_funnels_page')
        );
        
        // Add new funnel
        add_submenu_page(
            'sfft-funnels',
            __('Add New Funnel', 'simple-funnel-tracker'),
            __('Add New', 'simple-funnel-tracker'),
            'manage_options',
            'sfft-add-funnel',
            array($this, 'render_add_funnel_page')
        );
        
        // Analytics submenu
        add_submenu_page(
            'sfft-funnels',
            __('Analytics', 'simple-funnel-tracker'),
            __('Analytics', 'simple-funnel-tracker'),
            'manage_options',
            'sfft-analytics',
            array($this, 'render_analytics_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'sfft-funnels',
            __('Settings', 'simple-funnel-tracker'),
            __('Settings', 'simple-funnel-tracker'),
            'manage_options',
            'sfft-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Initialize admin settings
     *
     * @since 1.0.0
     * @return void
     */
    public function admin_init() {
        // Register settings
        register_setting('sfft_settings', 'sfft_cookie_consent_enabled');
        register_setting('sfft_settings', 'sfft_utm_tracking_enabled');
        register_setting('sfft_settings', 'sfft_data_retention_days');
        register_setting('sfft_settings', 'sfft_enable_ip_tracking');
        register_setting('sfft_settings', 'sfft_enable_user_agent_tracking');
        register_setting('sfft_settings', 'sfft_consent_banner_text');
        register_setting('sfft_settings', 'sfft_consent_position');
        register_setting('sfft_settings', 'sfft_consent_style');
        register_setting('sfft_settings', 'sfft_enable_debug_logging');
        
        // Add settings sections
        add_settings_section(
            'sfft_tracking_settings',
            __('Tracking Settings', 'simple-funnel-tracker'),
            array($this, 'tracking_settings_callback'),
            'sfft_settings'
        );
        
        add_settings_section(
            'sfft_cookie_settings',
            __('Cookie Consent Settings', 'simple-funnel-tracker'),
            array($this, 'cookie_settings_callback'),
            'sfft_settings'
        );
        
        add_settings_section(
            'sfft_privacy_settings',
            __('Privacy Settings', 'simple-funnel-tracker'),
            array($this, 'privacy_settings_callback'),
            'sfft_settings'
        );
        
        // Add settings fields
        $this->add_settings_fields();
    }
    
    /**
     * Add settings fields
     *
     * @since 1.0.0
     * @return void
     */
    private function add_settings_fields() {
        // Tracking settings fields
        add_settings_field(
            'sfft_utm_tracking_enabled',
            __('Enable UTM Tracking', 'simple-funnel-tracker'),
            array($this, 'checkbox_field_callback'),
            'sfft_settings',
            'sfft_tracking_settings',
            array('option' => 'sfft_utm_tracking_enabled', 'description' => __('Track UTM parameters from URL', 'simple-funnel-tracker'))
        );
        
        add_settings_field(
            'sfft_enable_ip_tracking',
            __('Enable IP Tracking', 'simple-funnel-tracker'),
            array($this, 'checkbox_field_callback'),
            'sfft_settings',
            'sfft_privacy_settings',
            array('option' => 'sfft_enable_ip_tracking', 'description' => __('Store visitor IP addresses (may require consent)', 'simple-funnel-tracker'))
        );
        
        // Cookie consent fields
        add_settings_field(
            'sfft_cookie_consent_enabled',
            __('Enable Cookie Consent', 'simple-funnel-tracker'),
            array($this, 'checkbox_field_callback'),
            'sfft_settings',
            'sfft_cookie_settings',
            array('option' => 'sfft_cookie_consent_enabled', 'description' => __('Show cookie consent banner', 'simple-funnel-tracker'))
        );
        
        add_settings_field(
            'sfft_consent_banner_text',
            __('Consent Banner Text', 'simple-funnel-tracker'),
            array($this, 'textarea_field_callback'),
            'sfft_settings',
            'sfft_cookie_settings',
            array('option' => 'sfft_consent_banner_text')
        );
        
        // Privacy settings fields
        add_settings_field(
            'sfft_data_retention_days',
            __('Data Retention (Days)', 'simple-funnel-tracker'),
            array($this, 'number_field_callback'),
            'sfft_settings',
            'sfft_privacy_settings',
            array('option' => 'sfft_data_retention_days', 'min' => 1, 'max' => 365)
        );
    }
    
    /**
     * Render funnels list page
     *
     * @since 1.0.0
     * @return void
     */
    public function render_funnels_page() {
        // Handle actions
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['funnel_id'])) {
            $this->render_edit_funnel_page();
            return;
        }
        
        $list_table = new SFFT_Funnels_List_Table($this->funnel_manager);
        $list_table->prepare_items();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Funnels', 'simple-funnel-tracker'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sfft-add-funnel')); ?>" class="page-title-action">
                <?php echo esc_html__('Add New', 'simple-funnel-tracker'); ?>
            </a>
            <hr class="wp-header-end">
            
            <?php $this->show_admin_notices(); ?>
            
            <form method="get">
                <input type="hidden" name="page" value="sfft-funnels">
                <?php $list_table->search_box(__('Search Funnels', 'simple-funnel-tracker'), 'funnel'); ?>
            </form>
            
            <form method="post">
                <?php
                $list_table->display();
                wp_nonce_field('sfft_bulk_action', 'sfft_bulk_nonce');
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render add funnel page
     *
     * @since 1.0.0
     * @return void
     */
    public function render_add_funnel_page() {
        include_once SFFT_ADMIN_DIR . 'partials/funnel-edit.php';
    }
    
    /**
     * Render edit funnel page
     *
     * @since 1.0.0
     * @return void
     */
    public function render_edit_funnel_page() {
        $funnel_id = intval($_GET['funnel_id']);
        $funnel = $this->funnel_manager->get_funnel($funnel_id);
        
        if (!$funnel) {
            wp_die(__('Funnel not found.', 'simple-funnel-tracker'));
        }
        
        include_once SFFT_ADMIN_DIR . 'partials/funnel-edit.php';
    }
    
    /**
     * Render analytics page
     *
     * @since 1.0.0
     * @return void
     */
    public function render_analytics_page() {
        include_once SFFT_ADMIN_DIR . 'partials/analytics-dashboard.php';
    }
    
    /**
     * Render settings page
     *
     * @since 1.0.0
     * @return void
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Simple Funnel Tracker Settings', 'simple-funnel-tracker'); ?></h1>
            
            <?php $this->show_admin_notices(); ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('sfft_settings');
                do_settings_sections('sfft_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Handle save funnel form submission
     *
     * @since 1.0.0
     * @return void
     */
    public function handle_save_funnel() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['sfft_funnel_nonce'], 'sfft_save_funnel')) {
            wp_die(__('Security check failed.', 'simple-funnel-tracker'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'simple-funnel-tracker'));
        }
        
        $funnel_id = isset($_POST['funnel_id']) ? intval($_POST['funnel_id']) : 0;
        $name = sanitize_text_field($_POST['funnel_name']);
        $description = sanitize_textarea_field($_POST['funnel_description']);
        $steps = isset($_POST['funnel_steps']) ? $_POST['funnel_steps'] : array();
        
        if ($funnel_id > 0) {
            // Update existing funnel
            $result = $this->funnel_manager->update_funnel($funnel_id, array(
                'name' => $name,
                'description' => $description,
                'steps' => $steps
            ));
            
            if (is_wp_error($result)) {
                $redirect_url = add_query_arg(array(
                    'page' => 'sfft-funnels',
                    'action' => 'edit',
                    'funnel_id' => $funnel_id,
                    'error' => urlencode($result->get_error_message())
                ), admin_url('admin.php'));
            } else {
                $redirect_url = add_query_arg(array(
                    'page' => 'sfft-funnels',
                    'message' => 'updated'
                ), admin_url('admin.php'));
            }
        } else {
            // Create new funnel
            $result = $this->funnel_manager->create_funnel($name, $description, $steps);
            
            if (is_wp_error($result)) {
                $redirect_url = add_query_arg(array(
                    'page' => 'sfft-add-funnel',
                    'error' => urlencode($result->get_error_message())
                ), admin_url('admin.php'));
            } else {
                $redirect_url = add_query_arg(array(
                    'page' => 'sfft-funnels',
                    'message' => 'created'
                ), admin_url('admin.php'));
            }
        }
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle delete funnel
     *
     * @since 1.0.0
     * @return void
     */
    public function handle_delete_funnel() {
        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'], 'sfft_delete_funnel_' . $_GET['funnel_id'])) {
            wp_die(__('Security check failed.', 'simple-funnel-tracker'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'simple-funnel-tracker'));
        }
        
        $funnel_id = intval($_GET['funnel_id']);
        $result = $this->funnel_manager->delete_funnel($funnel_id);
        
        if (is_wp_error($result)) {
            $redirect_url = add_query_arg(array(
                'page' => 'sfft-funnels',
                'error' => urlencode($result->get_error_message())
            ), admin_url('admin.php'));
        } else {
            $redirect_url = add_query_arg(array(
                'page' => 'sfft-funnels',
                'message' => 'deleted'
            ), admin_url('admin.php'));
        }
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * AJAX handler to get funnel data
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_get_funnel_data() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['security'], 'sfft_admin_nonce')) {
            wp_die(__('Security check failed.', 'simple-funnel-tracker'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'simple-funnel-tracker'));
        }
        
        $funnel_id = intval($_POST['funnel_id']);
        $funnel = $this->funnel_manager->get_funnel($funnel_id);
        
        if ($funnel) {
            wp_send_json_success($funnel);
        } else {
            wp_send_json_error(__('Funnel not found.', 'simple-funnel-tracker'));
        }
    }
    
    /**
     * Show admin notices
     *
     * @since 1.0.0
     * @return void
     */
    private function show_admin_notices() {
        if (isset($_GET['message'])) {
            $message = '';
            switch ($_GET['message']) {
                case 'created':
                    $message = __('Funnel created successfully.', 'simple-funnel-tracker');
                    break;
                case 'updated':
                    $message = __('Funnel updated successfully.', 'simple-funnel-tracker');
                    break;
                case 'deleted':
                    $message = __('Funnel deleted successfully.', 'simple-funnel-tracker');
                    break;
            }
            
            if ($message) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
        }
        
        if (isset($_GET['error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(urldecode($_GET['error'])) . '</p></div>';
        }
    }
    
    /**
     * Settings section callbacks
     */
    public function tracking_settings_callback() {
        echo '<p>' . esc_html__('Configure tracking behavior and data collection.', 'simple-funnel-tracker') . '</p>';
    }
    
    public function cookie_settings_callback() {
        echo '<p>' . esc_html__('Configure cookie consent banner and GDPR compliance.', 'simple-funnel-tracker') . '</p>';
    }
    
    public function privacy_settings_callback() {
        echo '<p>' . esc_html__('Configure privacy and data retention settings.', 'simple-funnel-tracker') . '</p>';
    }
    
    /**
     * Field callbacks
     */
    public function checkbox_field_callback($args) {
        $option = $args['option'];
        $value = get_option($option, false);
        $description = isset($args['description']) ? $args['description'] : '';
        
        echo '<label>';
        echo '<input type="checkbox" name="' . esc_attr($option) . '" value="1" ' . checked(1, $value, false) . '>';
        if ($description) {
            echo ' ' . esc_html($description);
        }
        echo '</label>';
    }
    
    public function textarea_field_callback($args) {
        $option = $args['option'];
        $value = get_option($option, '');
        
        echo '<textarea name="' . esc_attr($option) . '" rows="3" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
    }
    
    public function number_field_callback($args) {
        $option = $args['option'];
        $value = get_option($option, '');
        $min = isset($args['min']) ? $args['min'] : '';
        $max = isset($args['max']) ? $args['max'] : '';
        
        echo '<input type="number" name="' . esc_attr($option) . '" value="' . esc_attr($value) . '"';
        if ($min !== '') echo ' min="' . esc_attr($min) . '"';
        if ($max !== '') echo ' max="' . esc_attr($max) . '"';
        echo ' class="small-text">';
    }
}

/**
 * Funnels List Table Class
 */
class SFFT_Funnels_List_Table extends WP_List_Table {
    
    /**
     * Funnel Manager instance
     *
     * @var SFFT_Funnel_Manager
     */
    private $funnel_manager;
    
    /**
     * Constructor
     *
     * @since 1.0.0
     * @param SFFT_Funnel_Manager $funnel_manager
     */
    public function __construct($funnel_manager) {
        parent::__construct(array(
            'singular' => 'funnel',
            'plural' => 'funnels',
            'ajax' => false
        ));
        
        $this->funnel_manager = $funnel_manager;
    }
    
    /**
     * Get columns
     *
     * @since 1.0.0
     * @return array
     */
    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'name' => __('Name', 'simple-funnel-tracker'),
            'description' => __('Description', 'simple-funnel-tracker'),
            'status' => __('Status', 'simple-funnel-tracker'),
            'steps' => __('Steps', 'simple-funnel-tracker'),
            'created_at' => __('Created', 'simple-funnel-tracker')
        );
    }
    
    /**
     * Get sortable columns
     *
     * @since 1.0.0
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'name' => array('name', false),
            'status' => array('status', false),
            'created_at' => array('created_at', true)
        );
    }
    
    /**
     * Column default
     *
     * @since 1.0.0
     * @param array $item
     * @param string $column_name
     * @return string
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'description':
                return wp_trim_words($item[$column_name], 10);
            case 'status':
                return ucfirst($item[$column_name]);
            case 'steps':
                return $this->funnel_manager->get_funnel_steps_count($item['id']);
            case 'created_at':
                return date_i18n(get_option('date_format'), strtotime($item[$column_name]));
            default:
                return $item[$column_name];
        }
    }
    
    /**
     * Column checkbox
     *
     * @since 1.0.0
     * @param array $item
     * @return string
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="funnel_ids[]" value="%s" />',
            $item['id']
        );
    }
    
    /**
     * Column name
     *
     * @since 1.0.0
     * @param array $item
     * @return string
     */
    public function column_name($item) {
        $edit_url = add_query_arg(array(
            'page' => 'sfft-funnels',
            'action' => 'edit',
            'funnel_id' => $item['id']
        ), admin_url('admin.php'));
        
        $delete_url = wp_nonce_url(
            add_query_arg(array(
                'action' => 'sfft_delete_funnel',
                'funnel_id' => $item['id']
            ), admin_url('admin-post.php')),
            'sfft_delete_funnel_' . $item['id']
        );
        
        $actions = array(
            'edit' => sprintf('<a href="%s">%s</a>', esc_url($edit_url), __('Edit', 'simple-funnel-tracker')),
            'delete' => sprintf('<a href="%s" onclick="return confirm(\'%s\')">%s</a>', 
                esc_url($delete_url), 
                esc_js(__('Are you sure you want to delete this funnel?', 'simple-funnel-tracker')),
                __('Delete', 'simple-funnel-tracker')
            )
        );
        
        return sprintf('%1$s %2$s', 
            '<strong><a href="' . esc_url($edit_url) . '">' . esc_html($item['name']) . '</a></strong>',
            $this->row_actions($actions)
        );
    }
    
    /**
     * Prepare items
     *
     * @since 1.0.0
     * @return void
     */
    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'created_at';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'desc';
        
        $args = array(
            'per_page' => $per_page,
            'page' => $current_page,
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order
        );
        
        $data = $this->funnel_manager->get_all_funnels($args);
        
        $this->items = $data['funnels'];
        
        $this->set_pagination_args(array(
            'total_items' => $data['total_items'],
            'per_page' => $per_page,
            'total_pages' => $data['total_pages']
        ));
    }
}