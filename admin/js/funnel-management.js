/**
 * Simple Funnel Tracker - Admin Funnel Management JavaScript
 * 
 * Handles funnel creation, editing, and step management
 */
(function($) {
    'use strict';

    var FunnelManager = {
        stepCounter: 0,

        init: function() {
            this.bindEvents();
            this.initializeExistingSteps();
        },

        bindEvents: function() {
            // Add new step
            $(document).on('click', '#sfft-add-step', this.addStep.bind(this));
            
            // Remove step
            $(document).on('click', '.sfft-remove-step', this.removeStep.bind(this));
            
            // Move step up
            $(document).on('click', '.sfft-move-step-up', this.moveStepUp.bind(this));
            
            // Move step down
            $(document).on('click', '.sfft-move-step-down', this.moveStepDown.bind(this));
            
            // Step type change
            $(document).on('change', '.sfft-step-type', this.handleStepTypeChange.bind(this));
            
            // Form validation
            $('#sfft-funnel-form').on('submit', this.validateForm.bind(this));
        },

        initializeExistingSteps: function() {
            var steps = $('.sfft-step-item');
            this.stepCounter = steps.length;
            this.updateStepNumbers();
        },

        addStep: function(e) {
            e.preventDefault();
            
            var template = $('#sfft-step-template').html();
            if (!template) {
                console.error('Step template not found');
                return;
            }
            
            this.stepCounter++;
            
            // Replace template placeholders
            var stepHtml = template
                .replace(/\{\{step_number\}\}/g, this.stepCounter)
                .replace(/\{\{step_index\}\}/g, this.stepCounter - 1);
            
            $('#sfft-steps-container').append(stepHtml);
            this.updateStepNumbers();
            
            // Scroll to new step
            var newStep = $('.sfft-step-item').last();
            $('html, body').animate({
                scrollTop: newStep.offset().top - 100
            }, 500);
        },

        removeStep: function(e) {
            e.preventDefault();
            
            if ($('.sfft-step-item').length <= 1) {
                alert(sfftAdmin.strings.minOneStep || 'At least one step is required.');
                return;
            }
            
            if (confirm(sfftAdmin.strings.confirmRemoveStep || 'Are you sure you want to remove this step?')) {
                $(e.target).closest('.sfft-step-item').remove();
                this.updateStepNumbers();
                this.updateStepIndices();
            }
        },

        moveStepUp: function(e) {
            e.preventDefault();
            
            var step = $(e.target).closest('.sfft-step-item');
            var prevStep = step.prev('.sfft-step-item');
            
            if (prevStep.length) {
                step.insertBefore(prevStep);
                this.updateStepNumbers();
                this.updateStepIndices();
            }
        },

        moveStepDown: function(e) {
            e.preventDefault();
            
            var step = $(e.target).closest('.sfft-step-item');
            var nextStep = step.next('.sfft-step-item');
            
            if (nextStep.length) {
                step.insertAfter(nextStep);
                this.updateStepNumbers();
                this.updateStepIndices();
            }
        },

        updateStepNumbers: function() {
            $('.sfft-step-item').each(function(index) {
                var stepNumber = index + 1;
                $(this).attr('data-step', stepNumber);
                $(this).find('.sfft-step-header h4').text('Step ' + stepNumber);
            });
        },

        updateStepIndices: function() {
            $('.sfft-step-item').each(function(index) {
                var stepItem = $(this);
                
                // Update input names with new indices
                stepItem.find('input, select').each(function() {
                    var name = $(this).attr('name');
                    if (name && name.indexOf('funnel_steps[') === 0) {
                        var newName = name.replace(/funnel_steps\[\d+\]/, 'funnel_steps[' + index + ']');
                        $(this).attr('name', newName);
                    }
                });
            });
        },

        handleStepTypeChange: function(e) {
            var stepType = $(e.target).val();
            var stepItem = $(e.target).closest('.sfft-step-item');
            
            var pageSelection = stepItem.find('.sfft-page-selection');
            var formSelection = stepItem.find('.sfft-form-selection');
            
            if (stepType === 'page') {
                pageSelection.show();
                formSelection.hide();
                
                // Clear form selection
                formSelection.find('select').val('');
            } else if (stepType === 'form') {
                pageSelection.hide();
                formSelection.show();
                
                // Clear page selection
                pageSelection.find('select').val('');
            }
        },

        validateForm: function(e) {
            var isValid = true;
            var errors = [];
            
            // Validate funnel name
            var funnelName = $('#funnel_name').val().trim();
            if (!funnelName) {
                errors.push(sfftAdmin.strings.funnelNameRequired || 'Funnel name is required.');
                $('#funnel_name').addClass('error');
                isValid = false;
            } else {
                $('#funnel_name').removeClass('error');
            }
            
            // Validate steps
            var steps = $('.sfft-step-item');
            if (steps.length === 0) {
                errors.push(sfftAdmin.strings.minOneStep || 'At least one step is required.');
                isValid = false;
            }
            
            // Validate each step
            steps.each(function(index) {
                var stepItem = $(this);
                var stepType = stepItem.find('.sfft-step-type').val();
                var pageId = stepItem.find('[name*="[page_id]"]').val();
                var formId = stepItem.find('[name*="[form_id]"]').val();
                
                if (stepType === 'page' && !pageId) {
                    errors.push('Step ' + (index + 1) + ': ' + (sfftAdmin.strings.pageRequired || 'Page selection is required.'));
                    stepItem.find('[name*="[page_id]"]').addClass('error');
                    isValid = false;
                } else {
                    stepItem.find('[name*="[page_id]"]').removeClass('error');
                }
                
                if (stepType === 'form' && !formId) {
                    errors.push('Step ' + (index + 1) + ': ' + (sfftAdmin.strings.formRequired || 'Form selection is required.'));
                    stepItem.find('[name*="[form_id]"]').addClass('error');
                    isValid = false;
                } else {
                    stepItem.find('[name*="[form_id]"]').removeClass('error');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                
                // Show errors
                var errorMessage = errors.join('\n');
                alert(errorMessage);
                
                return false;
            }
            
            // Show loading state
            var submitButton = $('#submit');
            submitButton.prop('disabled', true);
            submitButton.val(sfftAdmin.strings.savingFunnel || 'Saving...');
            
            return true;
        }
    };

    // Analytics Dashboard functionality
    var AnalyticsDashboard = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Chart.js integration would go here
            // UTM filtering functionality
            $(document).on('click', '#update_utm_table', this.updateUTMTable.bind(this));
        },

        updateUTMTable: function(e) {
            e.preventDefault();
            
            var button = $(e.target);
            var originalText = button.text();
            
            button.prop('disabled', true).text('Updating...');
            
            var data = {
                action: 'sfft_get_utm_report',
                funnel_id: $('#funnel_id').val(),
                date_from: $('#date_from').val(),
                date_to: $('#date_to').val(),
                utm_source: $('#utm_source_filter').val(),
                utm_medium: $('#utm_medium_filter').val(),
                utm_campaign: $('#utm_campaign_filter').val(),
                security: sfftAdmin.security
            };
            
            $.ajax({
                url: sfftAdmin.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        // Update the UTM table with new data
                        console.log('UTM data updated:', response.data);
                        // Implementation would depend on the exact table structure needed
                    } else {
                        console.error('Error updating UTM data:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        // Check if we're on the funnel edit page
        if ($('#sfft-funnel-form').length) {
            FunnelManager.init();
        }
        
        // Check if we're on the analytics page
        if ($('#sfft-analytics-filters-form').length) {
            AnalyticsDashboard.init();
        }
    });

    // Expose to global scope for debugging
    window.SFFTFunnelManager = FunnelManager;
    window.SFFTAnalyticsDashboard = AnalyticsDashboard;

})(jQuery);