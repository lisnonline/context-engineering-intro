/**
 * Simple Funnel Tracker - Cookie Consent Management
 * 
 * Handles cookie consent banner and user interactions
 */
(function($) {
    'use strict';

    var ConsentManager = {
        init: function() {
            this.bindEvents();
            this.showBannerIfNeeded();
        },

        bindEvents: function() {
            // Accept all cookies
            $(document).on('click', '#sfft-consent-accept', function(e) {
                e.preventDefault();
                ConsentManager.updateConsent('accept', {});
            });

            // Decline all cookies
            $(document).on('click', '#sfft-consent-decline', function(e) {
                e.preventDefault();
                ConsentManager.updateConsent('decline', {});
            });

            // Show customization modal
            $(document).on('click', '#sfft-consent-customize', function(e) {
                e.preventDefault();
                ConsentManager.showCustomizationModal();
            });

            // Close modal
            $(document).on('click', '#sfft-consent-modal-close', function(e) {
                e.preventDefault();
                ConsentManager.hideCustomizationModal();
            });

            // Accept all from modal
            $(document).on('click', '#sfft-consent-accept-all', function(e) {
                e.preventDefault();
                ConsentManager.updateConsent('accept', {});
            });

            // Save custom preferences
            $(document).on('click', '#sfft-consent-save-preferences', function(e) {
                e.preventDefault();
                var categories = ConsentManager.getSelectedCategories();
                ConsentManager.updateConsent('customize', categories);
            });

            // Close modal when clicking outside
            $(document).on('click', '#sfft-consent-modal', function(e) {
                if (e.target.id === 'sfft-consent-modal') {
                    ConsentManager.hideCustomizationModal();
                }
            });
        },

        showBannerIfNeeded: function() {
            // Check if consent decision has already been made
            var consentStatus = this.getCookie('sfft_consent_status');
            
            if (!consentStatus || consentStatus === 'pending') {
                $('#sfft-consent-banner').fadeIn();
            }
        },

        showCustomizationModal: function() {
            // Load current preferences
            var categories = this.getConsentCategories();
            
            // Update checkboxes based on current preferences
            $('.sfft-consent-category-checkbox').each(function() {
                var category = $(this).val();
                var isChecked = categories[category] === true || category === 'necessary';
                $(this).prop('checked', isChecked);
            });

            $('#sfft-consent-modal').fadeIn();
        },

        hideCustomizationModal: function() {
            $('#sfft-consent-modal').fadeOut();
        },

        getSelectedCategories: function() {
            var categories = {};
            
            $('.sfft-consent-category-checkbox').each(function() {
                var category = $(this).val();
                categories[category] = $(this).is(':checked');
            });

            return categories;
        },

        updateConsent: function(action, categories) {
            var self = this;
            
            $.ajax({
                url: sfftData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sfft_update_consent',
                    consent_action: action,
                    categories: categories,
                    security: sfftData.security
                },
                success: function(response) {
                    if (response.success) {
                        // Hide banner and modal
                        $('#sfft-consent-banner').fadeOut();
                        $('#sfft-consent-modal').fadeOut();
                        
                        // Update global consent status
                        if (window.SFFT && window.SFFT.utils) {
                            // Trigger consent change event
                            $(document).trigger('sfft_consent_changed', [response.data.status]);
                        }
                        
                        // Reload page if consent was given to start tracking
                        if (action === 'accept' || (action === 'customize' && self.hasAnalyticsConsent(categories))) {
                            // Small delay to ensure cookies are set
                            setTimeout(function() {
                                location.reload();
                            }, 500);
                        }
                    } else {
                        console.error('Consent update failed:', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Consent update error:', error);
                }
            });
        },

        hasAnalyticsConsent: function(categories) {
            return categories && categories.analytics === true;
        },

        getCookie: function(name) {
            var value = "; " + document.cookie;
            var parts = value.split("; " + name + "=");
            if (parts.length === 2) {
                return parts.pop().split(";").shift();
            }
            return null;
        },

        getConsentCategories: function() {
            var categoriesJson = this.getCookie('sfft_consent_categories');
            if (categoriesJson) {
                try {
                    return JSON.parse(decodeURIComponent(categoriesJson));
                } catch (e) {
                    console.error('Error parsing consent categories:', e);
                }
            }
            return {
                necessary: true,
                analytics: false,
                marketing: false,
                preferences: false
            };
        },

        // Public method to check consent status
        hasConsent: function(category) {
            var consentStatus = this.getCookie('sfft_consent_status');
            
            if (!consentStatus || consentStatus === 'pending') {
                return false;
            }
            
            if (category === 'necessary') {
                return true;
            }
            
            if (consentStatus === 'accepted') {
                return true;
            }
            
            if (consentStatus === 'partial') {
                var categories = this.getConsentCategories();
                return categories[category] === true;
            }
            
            return false;
        },

        // Method to programmatically show consent banner (for testing)
        showBanner: function() {
            $('#sfft-consent-banner').fadeIn();
        },

        // Method to reset consent (for testing)
        resetConsent: function() {
            // Clear consent cookies
            document.cookie = 'sfft_consent_status=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            document.cookie = 'sfft_consent_categories=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            
            // Show banner again
            this.showBannerIfNeeded();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        ConsentManager.init();
    });

    // Expose ConsentManager to global scope for external access
    window.SFFTConsentManager = ConsentManager;

})(jQuery);