<?php
/**
 * Analytics Dashboard Class
 *
 * Provides analytics data processing and dashboard functionality
 * for the Simple Funnel Tracker plugin.
 *
 * @package SimpleFunnelTracker
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFT_Analytics_Dashboard {

    /**
     * Database instance
     *
     * @var wpdb
     */
    private $db;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }

    /**
     * Get comprehensive analytics data for a funnel
     *
     * @param int $funnel_id The funnel ID
     * @param string $date_from Start date (Y-m-d format)
     * @param string $date_to End date (Y-m-d format)
     * @return array Analytics data
     */
    public function get_funnel_analytics($funnel_id, $date_from, $date_to) {
        $funnel_id = intval($funnel_id);
        $date_from = sanitize_text_field($date_from);
        $date_to = sanitize_text_field($date_to);

        // Get funnel steps
        $steps = $this->get_funnel_steps($funnel_id);
        if (empty($steps)) {
            return array(
                'steps' => array(),
                'summary' => array(),
                'utm_data' => array()
            );
        }

        // Get step performance data
        $step_performance = $this->get_step_performance($funnel_id, $date_from, $date_to);
        
        // Get UTM performance data
        $utm_performance = $this->get_utm_performance($funnel_id, $date_from, $date_to);
        
        // Calculate summary metrics
        $summary = $this->calculate_summary_metrics($step_performance);

        return array(
            'steps' => $step_performance,
            'summary' => $summary,
            'utm_data' => $utm_performance
        );
    }

    /**
     * Get funnel steps from database
     *
     * @param int $funnel_id
     * @return array
     */
    private function get_funnel_steps($funnel_id) {
        $table_name = $this->db->prefix . 'ffst_funnel_steps';
        
        $query = $this->db->prepare(
            "SELECT * FROM {$table_name} WHERE funnel_id = %d ORDER BY step_order ASC",
            $funnel_id
        );
        
        return $this->db->get_results($query);
    }

    /**
     * Get step performance data with visitor counts
     *
     * @param int $funnel_id
     * @param string $date_from
     * @param string $date_to
     * @return array
     */
    private function get_step_performance($funnel_id, $date_from, $date_to) {
        $steps_table = $this->db->prefix . 'ffst_funnel_steps';
        $events_table = $this->db->prefix . 'ffst_tracking_events';
        
        $query = $this->db->prepare("
            SELECT 
                fs.id,
                fs.step_order,
                fs.step_name,
                fs.step_type,
                fs.page_id,
                fs.form_id,
                COUNT(DISTINCT te.visitor_id) as unique_visitors,
                COUNT(te.id) as total_events,
                MIN(te.created_at) as first_event,
                MAX(te.created_at) as last_event
            FROM {$steps_table} fs
            LEFT JOIN {$events_table} te ON fs.id = te.step_id 
                AND te.created_at BETWEEN %s AND %s
            WHERE fs.funnel_id = %d
            GROUP BY fs.id, fs.step_order, fs.step_name, fs.step_type, fs.page_id, fs.form_id
            ORDER BY fs.step_order ASC
        ", $date_from . ' 00:00:00', $date_to . ' 23:59:59', $funnel_id);
        
        $results = $this->db->get_results($query);
        
        // Add conversion rates
        $previous_visitors = 0;
        foreach ($results as $index => $step) {
            $step->conversion_rate = 0;
            if ($index > 0 && $previous_visitors > 0) {
                $step->conversion_rate = ($step->unique_visitors / $previous_visitors) * 100;
            }
            $previous_visitors = $step->unique_visitors;
        }
        
        return $results;
    }

    /**
     * Get UTM parameter performance data
     *
     * @param int $funnel_id
     * @param string $date_from
     * @param string $date_to
     * @return array
     */
    private function get_utm_performance($funnel_id, $date_from, $date_to) {
        $events_table = $this->db->prefix . 'ffst_tracking_events';
        $steps_table = $this->db->prefix . 'ffst_funnel_steps';
        
        $query = $this->db->prepare("
            SELECT 
                te.utm_source,
                te.utm_medium,
                te.utm_campaign,
                te.utm_term,
                te.utm_content,
                COUNT(DISTINCT te.visitor_id) as unique_visitors,
                COUNT(te.id) as total_events,
                COUNT(DISTINCT CASE WHEN fs.step_order = 1 THEN te.visitor_id END) as funnel_entries,
                COUNT(DISTINCT CASE WHEN fs.step_order = (
                    SELECT MAX(step_order) FROM {$steps_table} WHERE funnel_id = %d
                ) THEN te.visitor_id END) as funnel_completions
            FROM {$events_table} te
            INNER JOIN {$steps_table} fs ON te.step_id = fs.id
            WHERE fs.funnel_id = %d
                AND te.created_at BETWEEN %s AND %s
                AND (te.utm_source IS NOT NULL OR te.utm_medium IS NOT NULL OR te.utm_campaign IS NOT NULL)
            GROUP BY te.utm_source, te.utm_medium, te.utm_campaign, te.utm_term, te.utm_content
            ORDER BY unique_visitors DESC
        ", $funnel_id, $funnel_id, $date_from . ' 00:00:00', $date_to . ' 23:59:59');
        
        $results = $this->db->get_results($query);
        
        // Calculate conversion rates for UTM data
        foreach ($results as $utm_data) {
            $utm_data->conversion_rate = 0;
            if ($utm_data->funnel_entries > 0) {
                $utm_data->conversion_rate = ($utm_data->funnel_completions / $utm_data->funnel_entries) * 100;
            }
        }
        
        return $results;
    }

    /**
     * Calculate summary metrics
     *
     * @param array $step_performance
     * @return array
     */
    private function calculate_summary_metrics($step_performance) {
        if (empty($step_performance)) {
            return array(
                'total_entries' => 0,
                'total_completions' => 0,
                'overall_conversion_rate' => 0,
                'avg_time_to_complete' => 0,
                'drop_off_points' => array()
            );
        }

        $total_entries = $step_performance[0]->unique_visitors ?? 0;
        $total_completions = end($step_performance)->unique_visitors ?? 0;
        
        $overall_conversion_rate = 0;
        if ($total_entries > 0) {
            $overall_conversion_rate = ($total_completions / $total_entries) * 100;
        }

        // Find drop-off points (steps with significant visitor loss)
        $drop_off_points = array();
        for ($i = 1; $i < count($step_performance); $i++) {
            $current_visitors = $step_performance[$i]->unique_visitors;
            $previous_visitors = $step_performance[$i-1]->unique_visitors;
            
            if ($previous_visitors > 0) {
                $drop_off_rate = (($previous_visitors - $current_visitors) / $previous_visitors) * 100;
                if ($drop_off_rate > 30) { // 30% drop-off threshold
                    $drop_off_points[] = array(
                        'step_order' => $step_performance[$i]->step_order,
                        'step_name' => $step_performance[$i]->step_name,
                        'drop_off_rate' => $drop_off_rate
                    );
                }
            }
        }

        return array(
            'total_entries' => $total_entries,
            'total_completions' => $total_completions,
            'overall_conversion_rate' => $overall_conversion_rate,
            'drop_off_points' => $drop_off_points
        );
    }

    /**
     * Get filtered UTM data for AJAX requests
     *
     * @param int $funnel_id
     * @param string $date_from
     * @param string $date_to
     * @param array $filters
     * @return array
     */
    public function get_filtered_utm_data($funnel_id, $date_from, $date_to, $filters = array()) {
        $events_table = $this->db->prefix . 'ffst_tracking_events';
        $steps_table = $this->db->prefix . 'ffst_funnel_steps';
        
        $where_clauses = array();
        $query_params = array($funnel_id, $date_from . ' 00:00:00', $date_to . ' 23:59:59');
        
        // Add UTM filters
        if (!empty($filters['utm_source'])) {
            $where_clauses[] = "te.utm_source LIKE %s";
            $query_params[] = '%' . $this->db->esc_like($filters['utm_source']) . '%';
        }
        
        if (!empty($filters['utm_medium'])) {
            $where_clauses[] = "te.utm_medium LIKE %s";
            $query_params[] = '%' . $this->db->esc_like($filters['utm_medium']) . '%';
        }
        
        if (!empty($filters['utm_campaign'])) {
            $where_clauses[] = "te.utm_campaign LIKE %s";
            $query_params[] = '%' . $this->db->esc_like($filters['utm_campaign']) . '%';
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = ' AND ' . implode(' AND ', $where_clauses);
        }
        
        $query = $this->db->prepare("
            SELECT 
                COALESCE(te.utm_source, 'Direct') as utm_source,
                COALESCE(te.utm_medium, 'None') as utm_medium,
                COALESCE(te.utm_campaign, 'None') as utm_campaign,
                COUNT(DISTINCT te.visitor_id) as unique_visitors,
                COUNT(te.id) as total_events,
                COUNT(DISTINCT CASE WHEN fs.step_order = 1 THEN te.visitor_id END) as funnel_entries,
                COUNT(DISTINCT CASE WHEN fs.step_order = (
                    SELECT MAX(step_order) FROM {$steps_table} WHERE funnel_id = %d
                ) THEN te.visitor_id END) as funnel_completions
            FROM {$events_table} te
            INNER JOIN {$steps_table} fs ON te.step_id = fs.id
            WHERE fs.funnel_id = %d
                AND te.created_at BETWEEN %s AND %s
                {$where_sql}
            GROUP BY te.utm_source, te.utm_medium, te.utm_campaign
            ORDER BY unique_visitors DESC
            LIMIT 50
        ", $query_params);
        
        $results = $this->db->get_results($query);
        
        // Calculate conversion rates
        foreach ($results as $utm_data) {
            $utm_data->conversion_rate = 0;
            if ($utm_data->funnel_entries > 0) {
                $utm_data->conversion_rate = ($utm_data->funnel_completions / $utm_data->funnel_entries) * 100;
            }
        }
        
        return $results;
    }

    /**
     * Export analytics data to CSV
     *
     * @param int $funnel_id
     * @param string $date_from
     * @param string $date_to
     * @return array
     */
    public function export_analytics_csv($funnel_id, $date_from, $date_to) {
        $analytics_data = $this->get_funnel_analytics($funnel_id, $date_from, $date_to);
        
        $csv_data = array();
        
        // Add header
        $csv_data[] = array(
            'Step Order',
            'Step Name',
            'Step Type',
            'Unique Visitors',
            'Total Events',
            'Conversion Rate (%)',
            'First Event',
            'Last Event'
        );
        
        // Add step data
        foreach ($analytics_data['steps'] as $step) {
            $csv_data[] = array(
                $step->step_order,
                $step->step_name ?: 'Unnamed Step',
                ucfirst($step->step_type),
                $step->unique_visitors,
                $step->total_events,
                number_format($step->conversion_rate, 2),
                $step->first_event,
                $step->last_event
            );
        }
        
        return $csv_data;
    }

    /**
     * Export analytics data to JSON
     *
     * @param int $funnel_id
     * @param string $date_from
     * @param string $date_to
     * @return array
     */
    public function export_analytics_json($funnel_id, $date_from, $date_to) {
        return $this->get_funnel_analytics($funnel_id, $date_from, $date_to);
    }

    /**
     * Get top performing UTM combinations
     *
     * @param int $funnel_id
     * @param string $date_from
     * @param string $date_to
     * @param int $limit
     * @return array
     */
    public function get_top_utm_combinations($funnel_id, $date_from, $date_to, $limit = 10) {
        $utm_data = $this->get_utm_performance($funnel_id, $date_from, $date_to);
        
        // Sort by conversion rate, then by unique visitors
        usort($utm_data, function($a, $b) {
            if ($a->conversion_rate == $b->conversion_rate) {
                return $b->unique_visitors - $a->unique_visitors;
            }
            return $b->conversion_rate - $a->conversion_rate;
        });
        
        return array_slice($utm_data, 0, $limit);
    }

    /**
     * Get funnel completion times
     *
     * @param int $funnel_id
     * @param string $date_from
     * @param string $date_to
     * @return array
     */
    public function get_completion_times($funnel_id, $date_from, $date_to) {
        $events_table = $this->db->prefix . 'ffst_tracking_events';
        $steps_table = $this->db->prefix . 'ffst_funnel_steps';
        
        $query = $this->db->prepare("
            SELECT 
                te.visitor_id,
                MIN(te.created_at) as first_step_time,
                MAX(te.created_at) as last_step_time,
                TIMESTAMPDIFF(MINUTE, MIN(te.created_at), MAX(te.created_at)) as completion_time_minutes
            FROM {$events_table} te
            INNER JOIN {$steps_table} fs ON te.step_id = fs.id
            WHERE fs.funnel_id = %d
                AND te.created_at BETWEEN %s AND %s
            GROUP BY te.visitor_id
            HAVING COUNT(DISTINCT fs.step_order) = (
                SELECT COUNT(*) FROM {$steps_table} WHERE funnel_id = %d
            )
            ORDER BY completion_time_minutes ASC
        ", $funnel_id, $date_from . ' 00:00:00', $date_to . ' 23:59:59', $funnel_id);
        
        return $this->db->get_results($query);
    }

    /**
     * Get average completion time
     *
     * @param int $funnel_id
     * @param string $date_from
     * @param string $date_to
     * @return float
     */
    public function get_average_completion_time($funnel_id, $date_from, $date_to) {
        $completion_times = $this->get_completion_times($funnel_id, $date_from, $date_to);
        
        if (empty($completion_times)) {
            return 0;
        }
        
        $total_time = array_sum(array_column($completion_times, 'completion_time_minutes'));
        return $total_time / count($completion_times);
    }
}