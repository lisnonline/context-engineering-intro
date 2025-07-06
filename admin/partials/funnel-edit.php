<?php
/**
 * Admin View: Funnel Edit Form
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

// Determine if we're editing an existing funnel
$funnel_id = isset($_GET['funnel_id']) ? intval($_GET['funnel_id']) : 0;
$is_edit = $funnel_id > 0;
$page_title = $is_edit ? __('Edit Funnel', 'simple-funnel-tracker') : __('Add New Funnel', 'simple-funnel-tracker');

// Get funnel data if editing
$funnel = null;
if ($is_edit) {
    $funnel = $this->funnel_manager->get_funnel($funnel_id);
    if (!$funnel) {
        wp_die(__('Funnel not found.', 'simple-funnel-tracker'));
    }
}

// Get available pages and forms for step selection
$pages = get_pages(array('post_status' => 'publish'));
$fluentforms_integration = new SFFT_FluentForms_Integration();
$available_forms = $fluentforms_integration->get_available_forms();
?>

<div class="wrap">
    <h1><?php echo esc_html($page_title); ?></h1>
    
    <?php $this->show_admin_notices(); ?>
    
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="sfft-funnel-form">
        <?php wp_nonce_field('sfft_save_funnel', 'sfft_funnel_nonce'); ?>
        <input type="hidden" name="action" value="sfft_save_funnel">
        <?php if ($is_edit): ?>
            <input type="hidden" name="funnel_id" value="<?php echo esc_attr($funnel_id); ?>">
        <?php endif; ?>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="funnel_name"><?php echo esc_html__('Funnel Name', 'simple-funnel-tracker'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="funnel_name" 
                               name="funnel_name" 
                               value="<?php echo esc_attr($funnel['name'] ?? ''); ?>" 
                               class="regular-text" 
                               required>
                        <p class="description"><?php echo esc_html__('Enter a unique name for this funnel.', 'simple-funnel-tracker'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="funnel_description"><?php echo esc_html__('Description', 'simple-funnel-tracker'); ?></label>
                    </th>
                    <td>
                        <textarea id="funnel_description" 
                                  name="funnel_description" 
                                  rows="3" 
                                  class="large-text"><?php echo esc_textarea($funnel['description'] ?? ''); ?></textarea>
                        <p class="description"><?php echo esc_html__('Optional description for this funnel.', 'simple-funnel-tracker'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label><?php echo esc_html__('Funnel Steps', 'simple-funnel-tracker'); ?></label>
                    </th>
                    <td>
                        <div id="sfft-funnel-steps">
                            <div class="sfft-steps-header">
                                <button type="button" id="sfft-add-step" class="button button-secondary">
                                    <?php echo esc_html__('Add Step', 'simple-funnel-tracker'); ?>
                                </button>
                            </div>
                            
                            <div id="sfft-steps-container">
                                <?php
                                $steps = $funnel['steps'] ?? array();
                                if (empty($steps)) {
                                    // Add one empty step by default
                                    $steps = array(array(
                                        'step_order' => 1,
                                        'step_type' => 'page',
                                        'step_name' => '',
                                        'page_id' => '',
                                        'form_id' => ''
                                    ));
                                }
                                
                                foreach ($steps as $index => $step):
                                ?>
                                <div class="sfft-step-item" data-step="<?php echo esc_attr($index + 1); ?>">
                                    <div class="sfft-step-header">
                                        <h4><?php echo sprintf(esc_html__('Step %d', 'simple-funnel-tracker'), $index + 1); ?></h4>
                                        <div class="sfft-step-actions">
                                            <button type="button" class="sfft-move-step-up button-small" title="<?php echo esc_attr__('Move Up', 'simple-funnel-tracker'); ?>">↑</button>
                                            <button type="button" class="sfft-move-step-down button-small" title="<?php echo esc_attr__('Move Down', 'simple-funnel-tracker'); ?>">↓</button>
                                            <button type="button" class="sfft-remove-step button-small" title="<?php echo esc_attr__('Remove Step', 'simple-funnel-tracker'); ?>">✕</button>
                                        </div>
                                    </div>
                                    
                                    <table class="sfft-step-fields">
                                        <tr>
                                            <td>
                                                <label><?php echo esc_html__('Step Name', 'simple-funnel-tracker'); ?></label>
                                                <input type="text" 
                                                       name="funnel_steps[<?php echo esc_attr($index); ?>][name]" 
                                                       value="<?php echo esc_attr($step['step_name'] ?? ''); ?>" 
                                                       class="regular-text" 
                                                       placeholder="<?php echo esc_attr__('Step name (optional)', 'simple-funnel-tracker'); ?>">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <label><?php echo esc_html__('Step Type', 'simple-funnel-tracker'); ?></label>
                                                <select name="funnel_steps[<?php echo esc_attr($index); ?>][type]" class="sfft-step-type">
                                                    <option value="page" <?php selected($step['step_type'] ?? 'page', 'page'); ?>>
                                                        <?php echo esc_html__('Page View', 'simple-funnel-tracker'); ?>
                                                    </option>
                                                    <option value="form" <?php selected($step['step_type'] ?? '', 'form'); ?>>
                                                        <?php echo esc_html__('Form Submission', 'simple-funnel-tracker'); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr class="sfft-page-selection" <?php echo ($step['step_type'] ?? 'page') !== 'page' ? 'style="display:none;"' : ''; ?>>
                                            <td>
                                                <label><?php echo esc_html__('Select Page', 'simple-funnel-tracker'); ?></label>
                                                <select name="funnel_steps[<?php echo esc_attr($index); ?>][page_id]" class="regular-text">
                                                    <option value=""><?php echo esc_html__('Select a page...', 'simple-funnel-tracker'); ?></option>
                                                    <?php foreach ($pages as $page): ?>
                                                        <option value="<?php echo esc_attr($page->ID); ?>" 
                                                                <?php selected($step['page_id'] ?? '', $page->ID); ?>>
                                                            <?php echo esc_html($page->post_title); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr class="sfft-form-selection" <?php echo ($step['step_type'] ?? 'page') !== 'form' ? 'style="display:none;"' : ''; ?>>
                                            <td>
                                                <label><?php echo esc_html__('Select Form', 'simple-funnel-tracker'); ?></label>
                                                <select name="funnel_steps[<?php echo esc_attr($index); ?>][form_id]" class="regular-text">
                                                    <option value=""><?php echo esc_html__('Select a form...', 'simple-funnel-tracker'); ?></option>
                                                    <?php foreach ($available_forms as $form): ?>
                                                        <option value="<?php echo esc_attr($form['id']); ?>" 
                                                                <?php selected($step['form_id'] ?? '', $form['id']); ?>>
                                                            <?php echo esc_html($form['title']); ?>
                                                            <?php if ($form['is_multi_step']): ?>
                                                                (<?php echo esc_html__('Multi-step', 'simple-funnel-tracker'); ?>)
                                                            <?php endif; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <p class="description">
                            <?php echo esc_html__('Define the steps in your funnel. Each step can be a page view or form submission. Steps will be tracked in the order they appear here.', 'simple-funnel-tracker'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <?php submit_button($is_edit ? __('Update Funnel', 'simple-funnel-tracker') : __('Create Funnel', 'simple-funnel-tracker')); ?>
    </form>
</div>

<script type="text/template" id="sfft-step-template">
    <div class="sfft-step-item" data-step="{{step_number}}">
        <div class="sfft-step-header">
            <h4><?php echo sprintf(esc_html__('Step %s', 'simple-funnel-tracker'), '{{step_number}}'); ?></h4>
            <div class="sfft-step-actions">
                <button type="button" class="sfft-move-step-up button-small" title="<?php echo esc_attr__('Move Up', 'simple-funnel-tracker'); ?>">↑</button>
                <button type="button" class="sfft-move-step-down button-small" title="<?php echo esc_attr__('Move Down', 'simple-funnel-tracker'); ?>">↓</button>
                <button type="button" class="sfft-remove-step button-small" title="<?php echo esc_attr__('Remove Step', 'simple-funnel-tracker'); ?>">✕</button>
            </div>
        </div>
        
        <table class="sfft-step-fields">
            <tr>
                <td>
                    <label><?php echo esc_html__('Step Name', 'simple-funnel-tracker'); ?></label>
                    <input type="text" 
                           name="funnel_steps[{{step_index}}][name]" 
                           value="" 
                           class="regular-text" 
                           placeholder="<?php echo esc_attr__('Step name (optional)', 'simple-funnel-tracker'); ?>">
                </td>
            </tr>
            <tr>
                <td>
                    <label><?php echo esc_html__('Step Type', 'simple-funnel-tracker'); ?></label>
                    <select name="funnel_steps[{{step_index}}][type]" class="sfft-step-type">
                        <option value="page"><?php echo esc_html__('Page View', 'simple-funnel-tracker'); ?></option>
                        <option value="form"><?php echo esc_html__('Form Submission', 'simple-funnel-tracker'); ?></option>
                    </select>
                </td>
            </tr>
            <tr class="sfft-page-selection">
                <td>
                    <label><?php echo esc_html__('Select Page', 'simple-funnel-tracker'); ?></label>
                    <select name="funnel_steps[{{step_index}}][page_id]" class="regular-text">
                        <option value=""><?php echo esc_html__('Select a page...', 'simple-funnel-tracker'); ?></option>
                        <?php foreach ($pages as $page): ?>
                            <option value="<?php echo esc_attr($page->ID); ?>">
                                <?php echo esc_html($page->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr class="sfft-form-selection" style="display:none;">
                <td>
                    <label><?php echo esc_html__('Select Form', 'simple-funnel-tracker'); ?></label>
                    <select name="funnel_steps[{{step_index}}][form_id]" class="regular-text">
                        <option value=""><?php echo esc_html__('Select a form...', 'simple-funnel-tracker'); ?></option>
                        <?php foreach ($available_forms as $form): ?>
                            <option value="<?php echo esc_attr($form['id']); ?>">
                                <?php echo esc_html($form['title']); ?>
                                <?php if ($form['is_multi_step']): ?>
                                    (<?php echo esc_html__('Multi-step', 'simple-funnel-tracker'); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
    </div>
</script>