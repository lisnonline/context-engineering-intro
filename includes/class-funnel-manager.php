<?php
/**
 * Funnel Manager Class
 *
 * Handles CRUD operations for funnels and funnel steps
 *
 * @package SimpleFunnelTracker
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFT_Funnel_Manager {
    
    /**
     * Create a new funnel with steps
     *
     * @since 1.0.0
     * @param string $name Funnel name
     * @param string $description Funnel description
     * @param array $steps Array of funnel steps
     * @return int|WP_Error Funnel ID on success, WP_Error on failure
     */
    public function create_funnel($name, $description = '', $steps = array()) {
        global $wpdb;
        
        // Validate input
        $name = sanitize_text_field($name);
        $description = sanitize_textarea_field($description);
        
        if (empty($name)) {
            return new WP_Error('invalid_name', __('Funnel name is required.', 'simple-funnel-tracker'));
        }
        
        if (strlen($name) > 255) {
            return new WP_Error('name_too_long', __('Funnel name must be less than 255 characters.', 'simple-funnel-tracker'));
        }
        
        // Check if funnel name already exists
        if ($this->funnel_name_exists($name)) {
            return new WP_Error('name_exists', __('A funnel with this name already exists.', 'simple-funnel-tracker'));
        }
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Insert funnel
            $result = $wpdb->insert(
                $wpdb->prefix . 'ffst_funnels',
                array(
                    'name' => $name,
                    'description' => $description,
                    'status' => 'active',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s')
            );
            
            if ($result === false) {
                throw new Exception(__('Failed to create funnel.', 'simple-funnel-tracker'));
            }
            
            $funnel_id = $wpdb->insert_id;
            
            // Insert funnel steps if provided
            if (!empty($steps) && is_array($steps)) {
                $step_result = $this->create_funnel_steps($funnel_id, $steps);
                if (is_wp_error($step_result)) {
                    throw new Exception($step_result->get_error_message());
                }
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Clear cache
            $this->clear_funnel_cache();
            
            return $funnel_id;
            
        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            return new WP_Error('create_failed', $e->getMessage());
        }
    }
    
    /**
     * Get a funnel by ID with its steps
     *
     * @since 1.0.0
     * @param int $funnel_id Funnel ID
     * @param bool $include_steps Whether to include steps
     * @return array|null Funnel data or null if not found
     */
    public function get_funnel($funnel_id, $include_steps = true) {
        global $wpdb;
        
        $funnel_id = intval($funnel_id);
        
        if ($funnel_id <= 0) {
            return null;
        }
        
        // Get funnel data
        $funnel = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ffst_funnels WHERE id = %d",
            $funnel_id
        ), ARRAY_A);
        
        if (!$funnel) {
            return null;
        }
        
        // Get steps if requested
        if ($include_steps) {
            $funnel['steps'] = $this->get_funnel_steps($funnel_id);
        }
        
        return $funnel;
    }
    
    /**
     * Update a funnel
     *
     * @since 1.0.0
     * @param int $funnel_id Funnel ID
     * @param array $data Funnel data to update
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update_funnel($funnel_id, $data) {
        global $wpdb;
        
        $funnel_id = intval($funnel_id);
        
        if ($funnel_id <= 0) {
            return new WP_Error('invalid_id', __('Invalid funnel ID.', 'simple-funnel-tracker'));
        }
        
        // Check if funnel exists
        if (!$this->funnel_exists($funnel_id)) {
            return new WP_Error('not_found', __('Funnel not found.', 'simple-funnel-tracker'));
        }
        
        $update_data = array();
        $format = array();
        
        // Validate and prepare update data
        if (isset($data['name'])) {
            $name = sanitize_text_field($data['name']);
            if (empty($name)) {
                return new WP_Error('invalid_name', __('Funnel name is required.', 'simple-funnel-tracker'));
            }
            if (strlen($name) > 255) {
                return new WP_Error('name_too_long', __('Funnel name must be less than 255 characters.', 'simple-funnel-tracker'));
            }
            
            // Check if name already exists (excluding current funnel)
            if ($this->funnel_name_exists($name, $funnel_id)) {
                return new WP_Error('name_exists', __('A funnel with this name already exists.', 'simple-funnel-tracker'));
            }
            
            $update_data['name'] = $name;
            $format[] = '%s';
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
            $format[] = '%s';
        }
        
        if (isset($data['status'])) {
            $status = sanitize_text_field($data['status']);
            if (!in_array($status, array('active', 'inactive', 'draft'))) {
                return new WP_Error('invalid_status', __('Invalid funnel status.', 'simple-funnel-tracker'));
            }
            $update_data['status'] = $status;
            $format[] = '%s';
        }
        
        if (empty($update_data)) {
            return new WP_Error('no_data', __('No valid data to update.', 'simple-funnel-tracker'));
        }
        
        // Add updated timestamp
        $update_data['updated_at'] = current_time('mysql');
        $format[] = '%s';
        
        // Update funnel
        $result = $wpdb->update(
            $wpdb->prefix . 'ffst_funnels',
            $update_data,
            array('id' => $funnel_id),
            $format,
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('update_failed', __('Failed to update funnel.', 'simple-funnel-tracker'));
        }
        
        // Update steps if provided
        if (isset($data['steps']) && is_array($data['steps'])) {
            $step_result = $this->update_funnel_steps($funnel_id, $data['steps']);
            if (is_wp_error($step_result)) {
                return $step_result;
            }
        }
        
        // Clear cache
        $this->clear_funnel_cache($funnel_id);
        
        return true;
    }
    
    /**
     * Delete a funnel and its steps
     *
     * @since 1.0.0
     * @param int $funnel_id Funnel ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function delete_funnel($funnel_id) {
        global $wpdb;
        
        $funnel_id = intval($funnel_id);
        
        if ($funnel_id <= 0) {
            return new WP_Error('invalid_id', __('Invalid funnel ID.', 'simple-funnel-tracker'));
        }
        
        // Check if funnel exists
        if (!$this->funnel_exists($funnel_id)) {
            return new WP_Error('not_found', __('Funnel not found.', 'simple-funnel-tracker'));
        }
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Delete funnel steps first
            $wpdb->delete(
                $wpdb->prefix . 'ffst_funnel_steps',
                array('funnel_id' => $funnel_id),
                array('%d')
            );
            
            // Delete tracking events
            $wpdb->delete(
                $wpdb->prefix . 'ffst_tracking_events',
                array('funnel_id' => $funnel_id),
                array('%d')
            );
            
            // Delete funnel
            $result = $wpdb->delete(
                $wpdb->prefix . 'ffst_funnels',
                array('id' => $funnel_id),
                array('%d')
            );
            
            if ($result === false) {
                throw new Exception(__('Failed to delete funnel.', 'simple-funnel-tracker'));
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Clear cache
            $this->clear_funnel_cache($funnel_id);
            
            return true;
            
        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            return new WP_Error('delete_failed', $e->getMessage());
        }
    }
    
    /**
     * Get all funnels with pagination
     *
     * @since 1.0.0
     * @param array $args Query arguments
     * @return array Funnels data with pagination info
     */
    public function get_all_funnels($args = array()) {
        global $wpdb;
        
        // Default arguments
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'status' => '',
            'search' => '',
            'include_steps' => false
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Validate arguments
        $args['per_page'] = max(1, intval($args['per_page']));
        $args['page'] = max(1, intval($args['page']));
        $args['orderby'] = sanitize_sql_orderby($args['orderby']);
        $args['order'] = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $args['status'] = sanitize_text_field($args['status']);
        $args['search'] = sanitize_text_field($args['search']);
        
        // Build WHERE clause
        $where_conditions = array();
        $prepare_values = array();
        
        if (!empty($args['status'])) {
            $where_conditions[] = 'status = %s';
            $prepare_values[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $where_conditions[] = '(name LIKE %s OR description LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $prepare_values[] = $search_term;
            $prepare_values[] = $search_term;
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}ffst_funnels $where_clause";
        if (!empty($prepare_values)) {
            $count_sql = $wpdb->prepare($count_sql, $prepare_values);
        }
        $total_items = $wpdb->get_var($count_sql);
        
        // Get funnels
        $offset = ($args['page'] - 1) * $args['per_page'];
        $funnels_sql = "SELECT * FROM {$wpdb->prefix}ffst_funnels $where_clause ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d";
        
        $all_prepare_values = $prepare_values;
        $all_prepare_values[] = $args['per_page'];
        $all_prepare_values[] = $offset;
        
        $funnels_sql = $wpdb->prepare($funnels_sql, $all_prepare_values);
        $funnels = $wpdb->get_results($funnels_sql, ARRAY_A);
        
        // Add steps if requested
        if ($args['include_steps'] && !empty($funnels)) {
            foreach ($funnels as &$funnel) {
                $funnel['steps'] = $this->get_funnel_steps($funnel['id']);
            }
        }
        
        return array(
            'funnels' => $funnels,
            'total_items' => intval($total_items),
            'total_pages' => ceil($total_items / $args['per_page']),
            'current_page' => $args['page'],
            'per_page' => $args['per_page']
        );
    }
    
    /**
     * Create funnel steps
     *
     * @since 1.0.0
     * @param int $funnel_id Funnel ID
     * @param array $steps Steps data
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    private function create_funnel_steps($funnel_id, $steps) {
        global $wpdb;
        
        foreach ($steps as $order => $step) {
            $step_data = array(
                'funnel_id' => $funnel_id,
                'step_order' => intval($order) + 1,
                'step_type' => sanitize_text_field($step['type']),
                'step_name' => sanitize_text_field($step['name'] ?? ''),
                'page_id' => isset($step['page_id']) ? intval($step['page_id']) : null,
                'form_id' => isset($step['form_id']) ? intval($step['form_id']) : null
            );
            
            $result = $wpdb->insert(
                $wpdb->prefix . 'ffst_funnel_steps',
                $step_data,
                array('%d', '%d', '%s', '%s', '%d', '%d')
            );
            
            if ($result === false) {
                return new WP_Error('step_creation_failed', __('Failed to create funnel step.', 'simple-funnel-tracker'));
            }
        }
        
        return true;
    }
    
    /**
     * Get funnel steps
     *
     * @since 1.0.0
     * @param int $funnel_id Funnel ID
     * @return array Funnel steps
     */
    private function get_funnel_steps($funnel_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ffst_funnel_steps WHERE funnel_id = %d ORDER BY step_order ASC",
            $funnel_id
        ), ARRAY_A);
    }
    
    /**
     * Update funnel steps
     *
     * @since 1.0.0
     * @param int $funnel_id Funnel ID
     * @param array $steps Steps data
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    private function update_funnel_steps($funnel_id, $steps) {
        global $wpdb;
        
        // Delete existing steps
        $wpdb->delete(
            $wpdb->prefix . 'ffst_funnel_steps',
            array('funnel_id' => $funnel_id),
            array('%d')
        );
        
        // Create new steps
        return $this->create_funnel_steps($funnel_id, $steps);
    }
    
    /**
     * Check if funnel exists
     *
     * @since 1.0.0
     * @param int $funnel_id Funnel ID
     * @return bool True if exists, false otherwise
     */
    private function funnel_exists($funnel_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ffst_funnels WHERE id = %d",
            $funnel_id
        ));
        
        return $count > 0;
    }
    
    /**
     * Check if funnel name exists
     *
     * @since 1.0.0
     * @param string $name Funnel name
     * @param int $exclude_id Funnel ID to exclude from check
     * @return bool True if exists, false otherwise
     */
    private function funnel_name_exists($name, $exclude_id = 0) {
        global $wpdb;
        
        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}ffst_funnels WHERE name = %s";
        $values = array($name);
        
        if ($exclude_id > 0) {
            $sql .= " AND id != %d";
            $values[] = $exclude_id;
        }
        
        $count = $wpdb->get_var($wpdb->prepare($sql, $values));
        
        return $count > 0;
    }
    
    /**
     * Get funnel steps count
     *
     * @since 1.0.0
     * @param int $funnel_id Funnel ID
     * @return int Number of steps
     */
    public function get_funnel_steps_count($funnel_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ffst_funnel_steps WHERE funnel_id = %d",
            $funnel_id
        ));
        
        return intval($count);
    }
    
    /**
     * Clear funnel cache
     *
     * @since 1.0.0
     * @param int $funnel_id Optional specific funnel ID
     * @return void
     */
    private function clear_funnel_cache($funnel_id = null) {
        if ($funnel_id) {
            delete_transient("sfft_funnel_{$funnel_id}");
        }
        delete_transient('sfft_all_funnels');
        delete_transient('sfft_funnel_stats');
    }
}