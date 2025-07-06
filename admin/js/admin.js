/**
 * Simple Funnel Tracker - Admin JavaScript
 * 
 * General admin functionality and utilities
 */
(function($) {
    'use strict';

    var AdminManager = {
        init: function() {
            this.bindEvents();
            this.initializeComponents();
        },

        bindEvents: function() {
            // Confirm delete actions
            $(document).on('click', '.sfft-delete-confirm', this.confirmDelete.bind(this));
            
            // Settings form validation
            $('#sfft-settings-form').on('submit', this.validateSettings.bind(this));
            
            // Test tracking functionality
            $(document).on('click', '#sfft-test-tracking', this.testTracking.bind(this));
            
            // Clear tracking data
            $(document).on('click', '#sfft-clear-data', this.clearTrackingData.bind(this));
            
            // Export data
            $(document).on('click', '.sfft-export-data', this.exportData.bind(this));
        },

        initializeComponents: function() {
            // Initialize tooltips if available
            if ($.fn.tooltip) {
                $('.sfft-tooltip').tooltip();
            }
            
            // Initialize admin notices auto-hide
            this.initializeNotices();
            
            // Initialize data tables if available
            this.initializeDataTables();
        },

        confirmDelete: function(e) {
            var message = $(e.target).data('confirm-message') || 
                         sfftAdmin.strings.confirmDelete || 
                         'Are you sure you want to delete this item?';
            
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        },

        validateSettings: function(e) {
            var isValid = true;
            var errors = [];
            
            // Validate data retention days
            var retentionDays = $('#sfft_data_retention_days').val();
            if (retentionDays && (retentionDays < 1 || retentionDays > 365)) {
                errors.push('Data retention days must be between 1 and 365.');
                $('#sfft_data_retention_days').addClass('error');
                isValid = false;
            } else {
                $('#sfft_data_retention_days').removeClass('error');
            }
            
            // Validate consent banner text if consent is enabled
            var consentEnabled = $('#sfft_cookie_consent_enabled').is(':checked');
            var bannerText = $('#sfft_consent_banner_text').val().trim();
            
            if (consentEnabled && !bannerText) {
                errors.push('Consent banner text is required when cookie consent is enabled.');
                $('#sfft_consent_banner_text').addClass('error');
                isValid = false;
            } else {
                $('#sfft_consent_banner_text').removeClass('error');
            }
            
            if (!isValid) {
                e.preventDefault();
                alert(errors.join('\n'));
                return false;
            }
            
            return true;
        },

        testTracking: function(e) {
            e.preventDefault();
            
            var button = $(e.target);
            var originalText = button.text();
            
            button.prop('disabled', true).text('Testing...');
            
            $.ajax({
                url: sfftAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sfft_test_tracking',
                    security: sfftAdmin.security
                },
                success: function(response) {
                    if (response.success) {
                        alert('Tracking test successful: ' + response.data.message);
                    } else {
                        alert('Tracking test failed: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Tracking test failed: Network error');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        },

        clearTrackingData: function(e) {
            e.preventDefault();
            
            if (!confirm(sfftAdmin.strings.confirmClearData || 'Are you sure you want to clear all tracking data? This action cannot be undone.')) {
                return;
            }
            
            var button = $(e.target);
            var originalText = button.text();
            
            button.prop('disabled', true).text('Clearing...');
            
            $.ajax({
                url: sfftAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sfft_clear_tracking_data',
                    security: sfftAdmin.security
                },
                success: function(response) {
                    if (response.success) {
                        alert('Tracking data cleared successfully.');
                        location.reload();
                    } else {
                        alert('Failed to clear tracking data: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Failed to clear tracking data: Network error');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        },

        exportData: function(e) {
            e.preventDefault();
            
            var button = $(e.target);
            var format = button.data('format') || 'csv';
            var funnelId = button.data('funnel-id') || 0;
            var originalText = button.text();
            
            button.prop('disabled', true).text('Exporting...');
            
            $.ajax({
                url: sfftAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sfft_export_tracking_data',
                    funnel_id: funnelId,
                    format: format,
                    security: sfftAdmin.security
                },
                success: function(response) {
                    if (response.success) {
                        // Create download link
                        var blob = new Blob([response.data.data], {
                            type: format === 'json' ? 'application/json' : 'text/csv'
                        });
                        
                        var url = window.URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = response.data.filename;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);
                    } else {
                        alert('Export failed: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Export failed: Network error');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        },

        initializeNotices: function() {
            // Auto-hide success notices after 5 seconds
            $('.notice.notice-success').delay(5000).fadeOut();
            
            // Make notices dismissible
            $(document).on('click', '.notice-dismiss', function() {
                $(this).closest('.notice').fadeOut();
            });
        },

        initializeDataTables: function() {
            // Initialize DataTables if available and tables exist
            if ($.fn.DataTable && $('.sfft-data-table').length) {
                $('.sfft-data-table').DataTable({
                    responsive: true,
                    pageLength: 25,
                    order: [[0, 'desc']], // Sort by first column descending by default
                    language: {
                        search: 'Search:',
                        lengthMenu: 'Show _MENU_ entries per page',
                        info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                        infoEmpty: 'No entries found',
                        infoFiltered: '(filtered from _MAX_ total entries)',
                        paginate: {
                            first: 'First',
                            last: 'Last',
                            next: 'Next',
                            previous: 'Previous'
                        }
                    }
                });
            }
        }
    };

    // Utility functions
    var Utils = {
        formatNumber: function(num) {
            return new Intl.NumberFormat().format(num);
        },

        formatPercent: function(num, decimals) {
            decimals = decimals || 1;
            return Number(num).toFixed(decimals) + '%';
        },

        formatDate: function(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString();
        },

        showNotice: function(message, type) {
            type = type || 'info';
            
            var notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after(notice);
            
            // Auto-hide after 5 seconds
            if (type === 'success') {
                notice.delay(5000).fadeOut();
            }
        },

        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        AdminManager.init();
    });

    // Expose to global scope
    window.SFFTAdmin = {
        manager: AdminManager,
        utils: Utils
    };

})(jQuery);