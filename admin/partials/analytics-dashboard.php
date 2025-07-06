<?php
/**
 * Admin View: Analytics Dashboard
 *
 * @package SimpleFunnelTracker
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'simple-funnel-tracker'));
}

// Get funnel manager instance
$funnel_manager = new SFFT_Funnel_Manager();

// Get all funnels for selection
$funnels_data = $funnel_manager->get_all_funnels(array('per_page' => 100));
$funnels = $funnels_data['funnels'];

// Get selected funnel and date range
$selected_funnel_id = isset($_GET['funnel_id']) ? intval($_GET['funnel_id']) : 0;
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');

// Get analytics data if funnel is selected
$analytics_data = null;
if ($selected_funnel_id > 0) {
    $tracking_handler = new SFFT_Tracking_Handler();
    $analytics_data = $tracking_handler->get_funnel_analytics($selected_funnel_id, $date_from, $date_to);
}
?>

<div class="wrap">
    <h1><?php echo esc_html__('Funnel Analytics', 'simple-funnel-tracker'); ?></h1>
    
    <?php $this->show_admin_notices(); ?>
    
    <!-- Filters -->
    <div class="sfft-analytics-filters">
        <form method="get" id="sfft-analytics-filters-form">
            <input type="hidden" name="page" value="sfft-analytics">
            
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="funnel_id"><?php echo esc_html__('Select Funnel', 'simple-funnel-tracker'); ?></label>
                        </th>
                        <td>
                            <select name="funnel_id" id="funnel_id" class="regular-text">
                                <option value=""><?php echo esc_html__('Select a funnel...', 'simple-funnel-tracker'); ?></option>
                                <?php foreach ($funnels as $funnel): ?>
                                    <option value="<?php echo esc_attr($funnel['id']); ?>" 
                                            <?php selected($selected_funnel_id, $funnel['id']); ?>>
                                        <?php echo esc_html($funnel['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="date_from"><?php echo esc_html__('Date Range', 'simple-funnel-tracker'); ?></label>
                        </th>
                        <td>
                            <input type="date" 
                                   id="date_from" 
                                   name="date_from" 
                                   value="<?php echo esc_attr($date_from); ?>" 
                                   class="regular-text">
                            
                            <label for="date_to"><?php echo esc_html__('to', 'simple-funnel-tracker'); ?></label>
                            
                            <input type="date" 
                                   id="date_to" 
                                   name="date_to" 
                                   value="<?php echo esc_attr($date_to); ?>" 
                                   class="regular-text">
                            
                            <?php submit_button(__('Update Report', 'simple-funnel-tracker'), 'secondary', 'submit', false); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </form>
    </div>
    
    <?php if ($selected_funnel_id > 0 && $analytics_data): ?>
        
        <!-- Analytics Summary -->
        <div class="sfft-analytics-summary">
            <h2><?php echo esc_html__('Funnel Performance Overview', 'simple-funnel-tracker'); ?></h2>
            
            <?php if (!empty($analytics_data['steps'])): ?>
                
                <!-- Chart Container -->
                <div class="sfft-chart-container">
                    <canvas id="sfft-funnel-chart" width="400" height="200"></canvas>
                </div>
                
                <!-- Data Table -->
                <div class="sfft-analytics-table">
                    <h3><?php echo esc_html__('Step-by-Step Breakdown', 'simple-funnel-tracker'); ?></h3>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Step', 'simple-funnel-tracker'); ?></th>
                                <th><?php echo esc_html__('Step Name', 'simple-funnel-tracker'); ?></th>
                                <th><?php echo esc_html__('Type', 'simple-funnel-tracker'); ?></th>
                                <th><?php echo esc_html__('Unique Visitors', 'simple-funnel-tracker'); ?></th>
                                <th><?php echo esc_html__('Total Events', 'simple-funnel-tracker'); ?></th>
                                <th><?php echo esc_html__('Conversion Rate', 'simple-funnel-tracker'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $previous_visitors = 0;
                            foreach ($analytics_data['steps'] as $index => $step):
                                $conversion_rate = 0;
                                if ($index > 0 && $previous_visitors > 0) {
                                    $conversion_rate = ($step->unique_visitors / $previous_visitors) * 100;
                                }
                                ?>
                                <tr>
                                    <td><?php echo esc_html($step->step_order); ?></td>
                                    <td><?php echo esc_html($step->step_name ?: __('Unnamed Step', 'simple-funnel-tracker')); ?></td>
                                    <td><?php echo esc_html(ucfirst($step->step_type)); ?></td>
                                    <td><?php echo esc_html(number_format($step->unique_visitors)); ?></td>
                                    <td><?php echo esc_html(number_format($step->total_events)); ?></td>
                                    <td>
                                        <?php if ($index === 0): ?>
                                            <span class="sfft-baseline"><?php echo esc_html__('Baseline', 'simple-funnel-tracker'); ?></span>
                                        <?php else: ?>
                                            <span class="sfft-conversion-rate <?php echo $conversion_rate >= 50 ? 'good' : ($conversion_rate >= 20 ? 'average' : 'poor'); ?>">
                                                <?php echo esc_html(number_format($conversion_rate, 1)); ?>%
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                                $previous_visitors = $step->unique_visitors;
                            endforeach;
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- UTM Performance -->
                <div class="sfft-utm-performance">
                    <h3><?php echo esc_html__('UTM Parameter Performance', 'simple-funnel-tracker'); ?></h3>
                    
                    <div class="sfft-utm-filters">
                        <label for="utm_source_filter"><?php echo esc_html__('Source:', 'simple-funnel-tracker'); ?></label>
                        <input type="text" id="utm_source_filter" placeholder="<?php echo esc_attr__('Filter by source...', 'simple-funnel-tracker'); ?>">
                        
                        <label for="utm_medium_filter"><?php echo esc_html__('Medium:', 'simple-funnel-tracker'); ?></label>
                        <input type="text" id="utm_medium_filter" placeholder="<?php echo esc_attr__('Filter by medium...', 'simple-funnel-tracker'); ?>">
                        
                        <label for="utm_campaign_filter"><?php echo esc_html__('Campaign:', 'simple-funnel-tracker'); ?></label>
                        <input type="text" id="utm_campaign_filter" placeholder="<?php echo esc_attr__('Filter by campaign...', 'simple-funnel-tracker'); ?>">
                        
                        <button type="button" id="update_utm_table" class="button button-secondary">
                            <?php echo esc_html__('Update', 'simple-funnel-tracker'); ?>
                        </button>
                    </div>
                    
                    <div id="sfft_utm_table_container">
                        <!-- UTM table will be loaded here via AJAX -->
                    </div>
                </div>
                
            <?php else: ?>
                <div class="notice notice-info">
                    <p><?php echo esc_html__('No tracking data found for the selected funnel and date range.', 'simple-funnel-tracker'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
    <?php elseif ($selected_funnel_id > 0): ?>
        
        <div class="notice notice-warning">
            <p><?php echo esc_html__('No analytics data available for the selected funnel.', 'simple-funnel-tracker'); ?></p>
        </div>
        
    <?php else: ?>
        
        <div class="notice notice-info">
            <p><?php echo esc_html__('Please select a funnel to view analytics.', 'simple-funnel-tracker'); ?></p>
        </div>
        
        <?php if (empty($funnels)): ?>
            <div class="notice notice-warning">
                <p>
                    <?php echo esc_html__('No funnels found.', 'simple-funnel-tracker'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sfft-add-funnel')); ?>" class="button button-primary">
                        <?php echo esc_html__('Create Your First Funnel', 'simple-funnel-tracker'); ?>
                    </a>
                </p>
            </div>
        <?php endif; ?>
        
    <?php endif; ?>
</div>

<?php if ($selected_funnel_id > 0 && $analytics_data && !empty($analytics_data['steps'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Prepare chart data
    var chartData = {
        labels: [<?php
            echo implode(',', array_map(function($step) {
                return '"' . esc_js($step->step_name ?: 'Step ' . $step->step_order) . '"';
            }, $analytics_data['steps']));
        ?>],
        datasets: [{
            label: '<?php echo esc_js(__('Unique Visitors', 'simple-funnel-tracker')); ?>',
            data: [<?php
                echo implode(',', array_map(function($step) {
                    return intval($step->unique_visitors);
                }, $analytics_data['steps']));
            ?>],
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    };
    
    // Check if Chart.js is available
    if (typeof Chart !== 'undefined') {
        var ctx = document.getElementById('sfft-funnel-chart').getContext('2d');
        var chart = new Chart(ctx, {
            type: 'bar',
            data: chartData,
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: '<?php echo esc_js(__('Funnel Step Performance', 'simple-funnel-tracker')); ?>'
                    }
                }
            }
        });
    } else {
        // Fallback if Chart.js is not available
        document.getElementById('sfft-funnel-chart').innerHTML = 
            '<p style="text-align: center; padding: 50px;"><?php echo esc_js(__('Chart.js not loaded. Chart visualization unavailable.', 'simple-funnel-tracker')); ?></p>';
    }
    
    // UTM table update functionality
    document.getElementById('update_utm_table').addEventListener('click', function() {
        var funnelId = <?php echo intval($selected_funnel_id); ?>;
        var utmSource = document.getElementById('utm_source_filter').value;
        var utmMedium = document.getElementById('utm_medium_filter').value;
        var utmCampaign = document.getElementById('utm_campaign_filter').value;
        
        // Make AJAX request
        jQuery.ajax({
            url: sfftAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ffst_update_utm_table',
                funnel_id: funnelId,
                utm_source: utmSource,
                utm_medium: utmMedium,
                utm_campaign: utmCampaign,
                security: sfftAdmin.security
            },
            success: function(response) {
                if (response.success) {
                    document.getElementById('sfft_utm_table_container').innerHTML = response.data.html;
                } else {
                    console.error('Error updating UTM table:', response.data);
                }
            },
            error: function() {
                console.error('UTM table update request failed');
            }
        });
    });
    
    // Load initial UTM table
    document.getElementById('update_utm_table').click();
});
</script>
<?php endif; ?>