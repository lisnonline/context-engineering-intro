name: "Simple Funnel Tracker WordPress Plugin - Complete Implementation PRP"
description: |
  
## Purpose
Comprehensive WordPress plugin implementation PRP optimized for AI agents to build a complete funnel tracking system with FluentForms integration, UTM parameter tracking, and GDPR-compliant analytics.

## Core Principles
1. **Context is King**: Include ALL necessary WordPress documentation, hooks, and patterns
2. **Validation Loops**: Provide executable WordPress-specific tests and lints
3. **Information Dense**: Use WordPress coding standards and existing codebase patterns
4. **Progressive Success**: Start with core plugin structure, validate, then enhance
5. **Global rules**: Be sure to follow all rules in CLAUDE.md

---

## Goal
Build a complete WordPress plugin "Simple Funnel Tracker" that enables users to create marketing funnels, track user interactions through multi-step forms (initially FluentForms), capture UTM parameters, and generate performance reports with GDPR-compliant cookie consent management.

## Why
- **Business value**: Provides comprehensive funnel analytics for WordPress site owners to optimize conversion rates
- **Integration**: Seamlessly integrates with existing WordPress ecosystem and FluentForms plugin
- **Problems solved**: Eliminates need for multiple tracking tools by providing unified funnel analytics within WordPress dashboard
- **Target users**: Digital marketers, agencies, and WordPress site owners running marketing campaigns

## What
A WordPress plugin that creates a complete funnel tracking system with:
- Admin interface for creating/managing funnels
- FluentForms integration for multi-step form tracking
- UTM parameter capture and persistence
- GDPR-compliant cookie consent management
- Real-time analytics dashboard with bar charts
- Custom database tables for high-performance tracking

### Success Criteria
- [ ] Plugin activates successfully and creates database tables
- [ ] Admin can create, edit, and delete funnels through WordPress admin
- [ ] FluentForms multi-step progression is tracked accurately
- [ ] UTM parameters are captured and stored with proper consent
- [ ] Analytics dashboard displays funnel performance with filtering
- [ ] Cookie consent system blocks tracking until user approval
- [ ] All WordPress security best practices implemented (nonces, sanitization, capabilities)
- [ ] Performance optimized with proper caching and database queries

## All Needed Context

### Documentation & References (list all context needed to implement the feature)
```yaml
# MUST READ - Include these in your context window
- url: https://developer.wordpress.org/plugins/
  why: Core WordPress plugin development standards and patterns
  
- url: https://developer.wordpress.org/plugins/plugin-basics/
  why: Plugin structure, activation hooks, and security requirements
  
- url: https://developer.wordpress.org/plugins/hooks/
  why: WordPress action and filter hooks system
  
- url: https://developer.wordpress.org/plugins/javascript/ajax/
  why: WordPress AJAX implementation patterns for frontend tracking
  
- url: https://developer.wordpress.org/plugins/settings/
  why: WordPress Settings API for admin configuration
  
- url: https://developer.wordpress.org/reference/classes/wp_list_table/
  why: Admin table display for funnel management interface
  
- url: https://developers.fluentforms.com/
  why: FluentForms hooks, database structure, and integration patterns
  
- url: https://developers.fluentforms.com/hooks/actions/
  why: FluentForms action hooks for submission tracking
  
- url: https://developers.fluentforms.com/database/
  why: FluentForms database schema for form identification
  
- file: examples/agent/tracking.js
  why: FluentForms JavaScript selectors and step tracking patterns - use as reference for robust tracking implementation
  
- doc: https://developer.wordpress.org/apis/security/
  critical: WordPress security best practices - nonces, sanitization, capabilities, and escaping
  
- docfile: CLAUDE.md
  why: Project-specific instructions for PHP/JavaScript development and WordPress plugin structure
```

### Current Codebase tree (run `tree` in the root of the project) to get an overview of the codebase
```bash
context-engineering-intro/
├── CLAUDE.md                    # Project instructions and constraints
├── INITIAL.md                   # Feature specification
├── PRPs/
│   └── templates/
│       └── prp_base.md         # Base PRP template
└── examples/
    ├── README.md               # FluentForms tracking guidance
    └── agent/
        └── tracking.js         # FluentForms integration example
```

### Desired Codebase tree with files to be added and responsibility of file
```bash
context-engineering-intro/
├── simple-funnel-tracker.php           # Main plugin file with plugin header
├── includes/
│   ├── class-activator.php             # Plugin activation/deactivation hooks
│   ├── class-database.php              # Database table creation and management
│   ├── class-funnel-manager.php        # Core funnel CRUD operations
│   ├── class-tracking-handler.php      # AJAX handlers for tracking requests
│   ├── class-admin-interface.php       # WordPress admin UI components
│   ├── class-fluentforms-integration.php # FluentForms hook integration
│   ├── class-utm-tracker.php           # UTM parameter capture and storage
│   ├── class-cookie-consent.php        # GDPR cookie consent management
│   └── class-analytics-dashboard.php   # Statistics and reporting interface
├── admin/
│   ├── css/
│   │   └── admin.css                   # Admin interface styling
│   ├── js/
│   │   ├── admin.js                    # Admin interface JavaScript
│   │   └── funnel-management.js        # Funnel CRUD operations
│   └── partials/
│       ├── funnel-list.php             # Funnel management table
│       ├── funnel-edit.php             # Funnel editing form
│       └── analytics-dashboard.php     # Analytics display
├── public/
│   ├── css/
│   │   └── tracking.css                # Frontend tracking styles
│   └── js/
│       ├── tracking.js                 # Main tracking script
│       ├── utm-capture.js              # UTM parameter handling
│       └── cookie-consent.js           # Cookie consent UI
├── languages/
│   └── simple-funnel-tracker.pot       # Translation template
└── uninstall.php                       # Plugin cleanup on uninstall
```

### Known Gotchas of our codebase & Library Quirks
```php
// CRITICAL: WordPress requires specific plugin header format
// Example: Must include "Text Domain" for internationalization
// Example: Plugin activation must use register_activation_hook(__FILE__, callback)

// CRITICAL: FluentForms uses specific DOM selectors
// Example: .fluentform for form detection
// Example: .ff-step-titles li for step detection
// Example: .ff_active for current step identification
// Example: .ff-btn-next for next button clicks

// CRITICAL: WordPress AJAX requires proper nonce verification
// Example: wp_verify_nonce($_POST['nonce'], 'action_name') before processing
// Example: wp_localize_script() to pass nonce and ajax_url to JavaScript

// CRITICAL: WordPress database operations require specific patterns
// Example: Use $wpdb->prepare() for all dynamic queries
// Example: dbDelta() requires specific SQL formatting (uppercase keywords, specific spacing)
// Example: Use $wpdb->prefix for table names

// CRITICAL: Cookie consent must block tracking scripts until consent
// Example: Scripts must be loaded conditionally based on consent status
// Example: sessionStorage vs localStorage requires consent consideration
```

## Implementation Blueprint

### Data models and structure

Core database tables for tracking and funnel management:
```sql
-- Funnels table
CREATE TABLE {prefix}ffst_funnels (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    description text,
    status varchar(20) DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY name (name)
);

-- Funnel steps table
CREATE TABLE {prefix}ffst_funnel_steps (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    funnel_id mediumint(9) NOT NULL,
    page_id mediumint(9),
    form_id mediumint(9),
    step_order int(11) NOT NULL,
    step_type varchar(20) NOT NULL,
    PRIMARY KEY (id),
    KEY funnel_id (funnel_id),
    KEY page_id (page_id),
    KEY form_id (form_id)
);

-- Tracking events table
CREATE TABLE {prefix}ffst_tracking_events (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    funnel_id mediumint(9) NOT NULL,
    step_id mediumint(9),
    session_id varchar(255) NOT NULL,
    event_type varchar(50) NOT NULL,
    page_id mediumint(9),
    form_id mediumint(9),
    form_step_index int(11),
    utm_source varchar(255),
    utm_medium varchar(255),
    utm_campaign varchar(255),
    utm_content varchar(255),
    utm_term varchar(255),
    user_agent text,
    ip_address varchar(45),
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY funnel_id (funnel_id),
    KEY session_id (session_id),
    KEY event_type (event_type),
    KEY created_at (created_at)
);

-- Cookie consent table
CREATE TABLE {prefix}ffst_cookie_consent (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    session_id varchar(255) NOT NULL,
    consent_status varchar(20) NOT NULL,
    consent_categories text,
    ip_address varchar(45),
    user_agent text,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    expires_at datetime NOT NULL,
    PRIMARY KEY (id),
    KEY session_id (session_id),
    KEY consent_status (consent_status)
);
```

### list of tasks to be completed to fulfill the PRP in the order they should be completed

```yaml
Task 1: Create Main Plugin File
CREATE simple-funnel-tracker.php:
  - INCLUDE WordPress plugin header with all required fields
  - IMPLEMENT register_activation_hook and register_deactivation_hook
  - DEFINE plugin constants (VERSION, PLUGIN_DIR, PLUGIN_URL)
  - INCLUDE autoloader for plugin classes
  - PREVENT direct access with ABSPATH check

Task 2: Database Setup and Management
CREATE includes/class-database.php:
  - IMPLEMENT create_tables() method using dbDelta()
  - FOLLOW WordPress database table naming conventions with prefix
  - INCLUDE proper charset and collation settings
  - ADD version tracking for future schema updates
  - IMPLEMENT drop_tables() method for clean uninstall

Task 3: Plugin Activation Handler
CREATE includes/class-activator.php:
  - IMPLEMENT activation() static method
  - CALL database table creation
  - SET default plugin options
  - FLUSH rewrite rules if needed
  - CHECK plugin dependencies (WordPress version, PHP version)

Task 4: Core Funnel Management
CREATE includes/class-funnel-manager.php:
  - IMPLEMENT create_funnel($name, $description, $steps) method
  - IMPLEMENT get_funnel($id) method with proper $wpdb->prepare()
  - IMPLEMENT update_funnel($id, $data) method
  - IMPLEMENT delete_funnel($id) method
  - IMPLEMENT get_all_funnels() method with pagination support
  - ADD proper error handling and validation

Task 5: WordPress Admin Interface
CREATE includes/class-admin-interface.php:
  - IMPLEMENT add_admin_menu() method
  - CREATE admin pages for funnel management
  - IMPLEMENT WP_List_Table extension for funnel listing
  - ADD admin_enqueue_scripts() for CSS/JS loading
  - IMPLEMENT admin_init() for settings registration

Task 6: FluentForms Integration
CREATE includes/class-fluentforms-integration.php:
  - IMPLEMENT fluentform_loaded hook integration
  - ADD fluentform_submission_inserted hook for tracking
  - IMPLEMENT form detection and step mapping
  - ADD compatibility checks for FluentForms version
  - MIRROR patterns from examples/agent/tracking.js

Task 7: AJAX Tracking Handler
CREATE includes/class-tracking-handler.php:
  - IMPLEMENT wp_ajax_ffst_track_form_step action
  - IMPLEMENT wp_ajax_track_page_view action
  - IMPLEMENT wp_ajax_ffst_update_utm_table action
  - ADD proper nonce verification for all actions
  - IMPLEMENT data sanitization and validation
  - ADD user capability checks

Task 8: UTM Parameter Tracking
CREATE includes/class-utm-tracker.php:
  - IMPLEMENT capture_utm_parameters() method
  - ADD sessionStorage/localStorage management
  - IMPLEMENT utm parameter persistence across pages
  - ADD cookie consent integration
  - FOLLOW examples/agent/tracking.js patterns

Task 9: Cookie Consent Management
CREATE includes/class-cookie-consent.php:
  - IMPLEMENT GDPR-compliant consent banner
  - ADD script blocking until consent given
  - IMPLEMENT consent status storage and retrieval
  - ADD consent withdrawal functionality
  - INTEGRATE with Google Consent Mode v2

Task 10: Frontend Tracking Scripts
CREATE public/js/tracking.js:
  - MIRROR functionality from examples/agent/tracking.js
  - IMPLEMENT FluentForms step detection (.ff-step-titles, .ff_active)
  - ADD UTM parameter capture and storage
  - IMPLEMENT AJAX calls to WordPress admin-ajax.php
  - ADD sessionStorage management for step tracking
  - INCLUDE cookie consent checks before tracking

Task 11: Analytics Dashboard
CREATE includes/class-analytics-dashboard.php:
  - IMPLEMENT funnel performance calculations
  - ADD conversion rate calculations
  - IMPLEMENT UTM parameter filtering
  - CREATE bar chart data preparation
  - ADD date range filtering capabilities

Task 12: Admin CSS and JavaScript
CREATE admin/css/admin.css:
  - STYLE funnel management interface
  - IMPLEMENT responsive design for admin pages
  - FOLLOW WordPress admin design patterns

CREATE admin/js/admin.js:
  - IMPLEMENT funnel CRUD operations
  - ADD form validation and error handling
  - IMPLEMENT AJAX calls for admin operations
  - ADD chart rendering with Chart.js or similar

Task 13: Internationalization
CREATE languages/simple-funnel-tracker.pot:
  - EXTRACT all translatable strings
  - FOLLOW WordPress i18n best practices
  - IMPLEMENT load_plugin_textdomain()

Task 14: Uninstall Handler
CREATE uninstall.php:
  - IMPLEMENT complete plugin cleanup
  - REMOVE all database tables
  - DELETE all plugin options
  - CLEAN up any cached data
```

### Per task pseudocode as needed added to each task

```php
// Task 1: Main Plugin File
<?php
/**
 * Plugin Name: Simple Funnel Tracker
 * Description: Track funnel performance with FluentForms integration
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: simple-funnel-tracker
 */

// PATTERN: Always prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// PATTERN: Define plugin constants
define('SFFT_VERSION', '1.0.0');
define('SFFT_PLUGIN_DIR', plugin_dir_path(__FILE__));

// PATTERN: Use proper activation/deactivation hooks
register_activation_hook(__FILE__, array('SFFT_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('SFFT_Activator', 'deactivate'));

// Task 4: Funnel Manager
class SFFT_Funnel_Manager {
    public function create_funnel($name, $description, $steps) {
        global $wpdb;
        
        // PATTERN: Always validate and sanitize input
        $name = sanitize_text_field($name);
        $description = sanitize_textarea_field($description);
        
        // PATTERN: Use $wpdb->prepare for all queries
        $result = $wpdb->insert(
            $wpdb->prefix . 'ffst_funnels',
            array(
                'name' => $name,
                'description' => $description
            ),
            array('%s', '%s')
        );
        
        // PATTERN: Check for errors
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create funnel');
        }
        
        return $wpdb->insert_id;
    }
}

// Task 7: AJAX Handler
class SFFT_Tracking_Handler {
    public function handle_track_form_step() {
        // PATTERN: Always verify nonce first
        if (!wp_verify_nonce($_POST['security'], 'ffst_track_nonce')) {
            wp_die('Security check failed');
        }
        
        // PATTERN: Check user capabilities if needed
        // Note: For public tracking, this might not be needed
        
        // PATTERN: Sanitize all input
        $form_id = intval($_POST['form_id']);
        $step_index = intval($_POST['step_index']);
        $utm_source = sanitize_text_field($_POST['utm_source']);
        
        // PATTERN: Use database class for operations
        $tracking_data = array(
            'form_id' => $form_id,
            'step_index' => $step_index,
            'utm_source' => $utm_source,
            'session_id' => session_id(),
            'created_at' => current_time('mysql')
        );
        
        // PATTERN: Return standardized JSON response
        wp_send_json_success($tracking_data);
    }
}

// Task 10: Frontend Tracking JavaScript
// PATTERN: Mirror examples/agent/tracking.js structure
jQuery(document).ready(function($) {
    // PATTERN: Check for FluentForms presence
    if ($('.fluentform').length === 0) {
        return;
    }
    
    // PATTERN: UTM parameter capture
    function getUrlParam(name) {
        // MIRROR: examples/agent/tracking.js implementation
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }
    
    // PATTERN: Step detection using FluentForms selectors
    function getCurrentStep() {
        // MIRROR: examples/agent/tracking.js pattern
        var steps = $('.ff-step-titles li');
        if (steps.length > 0) {
            var activeStep = steps.filter('.ff_active');
            if (activeStep.length > 0) {
                var stepNum = parseInt(activeStep.data('step-number')) + 1;
                return { current: stepNum, total: steps.length };
            }
        }
        return null;
    }
    
    // PATTERN: AJAX tracking call
    function trackStep(step, total) {
        // CRITICAL: Check cookie consent before tracking
        if (!hasTrackingConsent()) {
            return;
        }
        
        $.post(sfftData.ajaxUrl, {
            action: 'ffst_track_form_step',
            form_id: getFormId(),
            step_index: step,
            total_steps: total,
            utm_source: getUrlParam('utm_source'),
            security: sfftData.security
        });
    }
});
```

### Integration Points
```yaml
WORDPRESS_HOOKS:
  - action: "init"
    callback: "initialize_tracking"
    priority: 10
    
  - action: "admin_menu"
    callback: "add_admin_menu_pages"
    
  - action: "wp_enqueue_scripts"
    callback: "enqueue_public_scripts"
    
  - action: "admin_enqueue_scripts"
    callback: "enqueue_admin_scripts"

FLUENTFORMS_HOOKS:
  - action: "fluentform/loaded"
    callback: "initialize_fluentforms_integration"
    
  - action: "fluentform/submission_inserted"
    callback: "track_form_submission"
    
  - filter: "fluentform/rendering_field_data"
    callback: "add_tracking_attributes"

DATABASE_INTEGRATION:
  - create_tables: "Use dbDelta() with proper SQL formatting"
  - table_prefix: "Use $wpdb->prefix for all table names"
  - charset: "Use $wpdb->get_charset_collate()"

JAVASCRIPT_LOCALIZATION:
  - script_name: "sfft-tracking"
  - object_name: "sfftData"
  - data: "ajaxUrl, security nonce, currentPageId, settings"
```

## Validation Loop

### Level 1: WordPress Standards & Security
```bash
# Run WordPress coding standards check
phpcs --standard=WordPress simple-funnel-tracker.php includes/
# Expected: No errors. If errors exist, fix them before proceeding

# Check for WordPress security issues
grep -r "wp_verify_nonce\|check_ajax_referer\|current_user_can" includes/
# Expected: All AJAX handlers have proper security checks

# Validate plugin header
head -20 simple-funnel-tracker.php | grep -E "Plugin Name|Version|Text Domain"
# Expected: All required headers present
```

### Level 2: WordPress Integration Tests
```php
// Test plugin activation
function test_plugin_activation() {
    // Activate plugin
    activate_plugin('simple-funnel-tracker/simple-funnel-tracker.php');
    
    // Check if tables were created
    global $wpdb;
    $tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}ffst_%'");
    assert(count($tables) >= 4, "Database tables not created");
    
    // Check if admin menu was added
    $admin_menu = get_option('_admin_menu_cache');
    assert(isset($admin_menu['funnel-tracker']), "Admin menu not added");
}

// Test FluentForms integration
function test_fluentforms_integration() {
    // Check if FluentForms is active
    if (!class_exists('FluentForm\Framework\Foundation\Application')) {
        return; // Skip if FluentForms not active
    }
    
    // Test form detection
    $forms = fluentFormApi('forms')->all();
    assert(count($forms) > 0, "No FluentForms found for testing");
}

// Test AJAX handlers
function test_ajax_handlers() {
    // Test nonce generation
    $nonce = wp_create_nonce('ffst_track_nonce');
    assert(!empty($nonce), "Nonce generation failed");
    
    // Test AJAX action registration
    $wp_ajax_actions = array(
        'ffst_track_form_step',
        'track_page_view',
        'ffst_update_utm_table'
    );
    
    foreach ($wp_ajax_actions as $action) {
        assert(has_action("wp_ajax_{$action}"), "AJAX action {$action} not registered");
        assert(has_action("wp_ajax_nopriv_{$action}"), "Public AJAX action {$action} not registered");
    }
}
```

```bash
# Run integration tests
wp eval-file tests/integration-tests.php
# Expected: All assertions pass
```

### Level 3: Frontend Tracking Test
```bash
# Start WordPress development server
wp server --host=localhost --port=8080

# Test JavaScript loading
curl -s http://localhost:8080/wp-content/plugins/simple-funnel-tracker/public/js/tracking.js | head -5
# Expected: JavaScript file loads without errors

# Test AJAX endpoints
curl -X POST http://localhost:8080/wp-admin/admin-ajax.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=ffst_track_form_step&form_id=1&step_index=1&security=test_nonce"
# Expected: Valid JSON response (even if security check fails)
```

### Level 4: FluentForms Integration Test
```bash
# Test with actual FluentForms installation
# 1. Install FluentForms plugin
wp plugin install fluentform --activate

# 2. Create a test form
wp eval "
\$form_data = array(
    'title' => 'Test Funnel Form',
    'form_fields' => json_encode(array(
        'container' => array(
            'element' => 'container',
            'settings' => array(
                'container_type' => 'step_start'
            )
        )
    ))
);
fluentFormApi('forms')->create(\$form_data);
"

# 3. Test form detection in our tracking
wp eval-file tests/fluentforms-integration-test.php
# Expected: Form is detected and trackable
```

## Final validation Checklist
- [ ] Plugin activates without errors: `wp plugin activate simple-funnel-tracker`
- [ ] Database tables created: `wp db query "SHOW TABLES LIKE 'wp_ffst_%'"`
- [ ] Admin menu appears: Check WordPress admin for "Funnels" menu
- [ ] FluentForms integration works: Test with multi-step form
- [ ] AJAX handlers respond: Test tracking endpoints
- [ ] JavaScript loads on frontend: Check browser console for errors
- [ ] UTM parameters captured: Test with ?utm_source=test
- [ ] Cookie consent blocks tracking: Test consent flow
- [ ] Analytics dashboard displays data: Check admin analytics page
- [ ] WordPress coding standards: `phpcs --standard=WordPress`
- [ ] Security measures implemented: Verify nonces, sanitization, capabilities
- [ ] Internationalization ready: `wp i18n make-pot`

---

## Anti-Patterns to Avoid
- ❌ Don't bypass WordPress security measures (nonces, capabilities, sanitization)
- ❌ Don't use direct database queries without $wpdb->prepare()
- ❌ Don't ignore FluentForms version compatibility
- ❌ Don't hardcode database table names (always use $wpdb->prefix)
- ❌ Don't load tracking scripts before cookie consent
- ❌ Don't create admin pages without proper capability checks
- ❌ Don't use outdated WordPress functions (check for deprecation)
- ❌ Don't skip internationalization for user-facing strings
- ❌ Don't ignore WordPress coding standards
- ❌ Don't create tables without proper cleanup in uninstall.php

---

## PRP Implementation Confidence Score: 9/10

**Rationale:**
- ✅ Comprehensive WordPress plugin architecture documented
- ✅ All necessary hooks and integration points identified
- ✅ FluentForms integration patterns researched and documented
- ✅ Security best practices explicitly outlined
- ✅ Database schema optimized for performance
- ✅ GDPR compliance requirements addressed
- ✅ Validation loops are executable and specific
- ✅ Real-world examples from codebase included
- ✅ Progressive implementation approach defined

**Remaining 1 point concerns:**
- FluentForms version compatibility edge cases
- WordPress multisite considerations not fully addressed
- Performance optimization under high-traffic conditions

This PRP provides sufficient context and guidance for successful one-pass implementation by an AI agent with access to WordPress development environment.