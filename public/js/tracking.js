/**
 * Simple Funnel Tracker - Frontend Tracking Script
 * 
 * Handles FluentForms step tracking, page view tracking, and UTM parameter capture
 * Based on examples/agent/tracking.js but enhanced for production use
 */
(function($) {
    'use strict';

    // Global tracking object
    window.SFFT = window.SFFT || {};

    // Configuration
    var config = {
        debug: sfftData.version && sfftData.version.includes('dev'),
        sessionStorageKey: 'sfft_tracking_data',
        trackedStepsKey: 'sfft_tracked_steps',
        utmParamsKey: 'sfft_utm_params',
        sessionIdKey: 'sfft_session_id'
    };

    /**
     * Utility Functions
     */
    var utils = {
        // Get URL parameter value
        getUrlParam: function(name) {
            name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
            var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
            var results = regex.exec(location.search);
            return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
        },

        // Debug logging
        log: function(message, data) {
            if (config.debug && console && console.log) {
                console.log('[SFFT]', message, data || '');
            }
        },

        // Get or create session ID
        getSessionId: function() {
            var sessionId = sessionStorage.getItem(config.sessionIdKey);
            if (!sessionId) {
                sessionId = 'sfft_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                sessionStorage.setItem(config.sessionIdKey, sessionId);
            }
            return sessionId;
        },

        // Check if tracking consent is given
        hasTrackingConsent: function() {
            // If cookie consent is disabled, assume consent
            if (!sfftData.cookieConsentEnabled) {
                return true;
            }
            
            // Check for consent cookie
            var consentStatus = this.getCookie('sfft_consent_status');
            return consentStatus === 'accepted';
        },

        // Get cookie value
        getCookie: function(name) {
            var value = "; " + document.cookie;
            var parts = value.split("; " + name + "=");
            if (parts.length === 2) {
                return parts.pop().split(";").shift();
            }
            return null;
        },

        // Set cookie
        setCookie: function(name, value, days) {
            var expires = "";
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + (value || "") + expires + "; path=/";
        }
    };

    /**
     * UTM Parameter Management
     */
    var utmManager = {
        // Get all UTM parameters from URL
        getUtmParams: function() {
            var params = {
                utm_source: utils.getUrlParam('utm_source'),
                utm_medium: utils.getUrlParam('utm_medium'),
                utm_campaign: utils.getUrlParam('utm_campaign'),
                utm_content: utils.getUrlParam('utm_content'),
                utm_term: utils.getUrlParam('utm_term')
            };

            // Store in sessionStorage for persistence
            if (Object.values(params).some(function(value) { return value !== ''; })) {
                sessionStorage.setItem(config.utmParamsKey, JSON.stringify(params));
                utils.log('UTM parameters captured and stored', params);
            }

            return params;
        },

        // Get stored UTM parameters
        getStoredUtmParams: function() {
            try {
                var stored = sessionStorage.getItem(config.utmParamsKey);
                return stored ? JSON.parse(stored) : this.getUtmParams();
            } catch (e) {
                utils.log('Error reading stored UTM params', e);
                return this.getUtmParams();
            }
        }
    };

    /**
     * FluentForms Integration
     */
    var fluentFormsTracker = {
        // Initialize FluentForms tracking
        init: function() {
            if ($('.fluentform').length === 0) {
                utils.log('No FluentForms found on page');
                return;
            }

            utils.log('Initializing FluentForms tracking');
            this.trackInitialStep();
            this.bindStepEvents();
        },

        // Get current step information
        getCurrentStep: function() {
            var steps = $('.ff-step-titles li');
            if (steps.length > 0) {
                var activeStep = steps.filter('.ff_active');
                if (activeStep.length > 0) {
                    var stepNum = parseInt(activeStep.data('step-number'));
                    if (isNaN(stepNum)) stepNum = 0;
                    stepNum += 1; // Convert to 1-based indexing
                    
                    return { 
                        current: stepNum, 
                        total: steps.length 
                    };
                }
            }
            return null;
        },

        // Get form ID from form element
        getFormId: function() {
            var form = $('[data-name^="form_step-"]');
            if (form.length > 0) {
                var formName = form.data('name');
                var match = formName && formName.match(/form_step-(\d+)/);
                if (match) {
                    return parseInt(match[1]);
                }
            }

            // Fallback: try to get from form ID attribute
            var fluentForm = $('.fluentform');
            if (fluentForm.length > 0) {
                var formId = fluentForm.attr('id');
                var match = formId && formId.match(/fluentform_(\d+)/);
                if (match) {
                    return parseInt(match[1]);
                }
            }

            return null;
        },

        // Track form step
        trackStep: function(step, total) {
            if (!utils.hasTrackingConsent()) {
                utils.log('Tracking consent not given, skipping step tracking');
                return;
            }

            var formId = this.getFormId();
            if (!formId) {
                utils.log('Could not determine form ID');
                return;
            }

            // Check if step already tracked
            var trackedSteps = this.getTrackedSteps();
            var stepKey = formId + '_' + step;
            if (trackedSteps.includes(stepKey)) {
                utils.log('Step already tracked', { formId: formId, step: step });
                return;
            }

            // Mark step as tracked
            trackedSteps.push(stepKey);
            sessionStorage.setItem(config.trackedStepsKey, JSON.stringify(trackedSteps));

            var utmParams = utmManager.getStoredUtmParams();
            var sessionId = utils.getSessionId();

            utils.log('Tracking form step', {
                formId: formId,
                step: step,
                total: total,
                utmParams: utmParams
            });

            // Send tracking request
            $.ajax({
                url: sfftData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ffst_track_form_step',
                    form_id: formId,
                    step_index: step,
                    total_steps: total,
                    page_id: sfftData.currentPageId,
                    session_id: sessionId,
                    utm_source: utmParams.utm_source,
                    utm_medium: utmParams.utm_medium,
                    utm_campaign: utmParams.utm_campaign,
                    utm_content: utmParams.utm_content,
                    utm_term: utmParams.utm_term,
                    security: sfftData.security
                },
                success: function(response) {
                    utils.log('Form step tracked successfully', response);
                },
                error: function(xhr, status, error) {
                    utils.log('Error tracking form step', { status: status, error: error });
                }
            });
        },

        // Get tracked steps from session storage
        getTrackedSteps: function() {
            try {
                var tracked = sessionStorage.getItem(config.trackedStepsKey);
                return tracked ? JSON.parse(tracked) : [];
            } catch (e) {
                utils.log('Error reading tracked steps', e);
                return [];
            }
        },

        // Track initial step when page loads
        trackInitialStep: function() {
            var initialStep = this.getCurrentStep();
            if (initialStep) {
                this.trackStep(initialStep.current, initialStep.total);
            }
        },

        // Bind events for step navigation
        bindStepEvents: function() {
            var self = this;
            
            // Track on next button click
            $(document).on('click', '.ff-btn-next', function() {
                setTimeout(function() {
                    var stepData = self.getCurrentStep();
                    if (stepData) {
                        self.trackStep(stepData.current, stepData.total);
                    }
                }, 100); // Slight delay to ensure step change has occurred
            });

            // Track on previous button click
            $(document).on('click', '.ff-btn-prev', function() {
                setTimeout(function() {
                    var stepData = self.getCurrentStep();
                    if (stepData) {
                        self.trackStep(stepData.current, stepData.total);
                    }
                }, 100);
            });

            // Track on step title click (if navigation is enabled)
            $(document).on('click', '.ff-step-titles li', function() {
                setTimeout(function() {
                    var stepData = self.getCurrentStep();
                    if (stepData) {
                        self.trackStep(stepData.current, stepData.total);
                    }
                }, 100);
            });
        }
    };

    /**
     * Page View Tracking
     */
    var pageViewTracker = {
        // Track page view
        track: function() {
            if (!utils.hasTrackingConsent()) {
                utils.log('Tracking consent not given, skipping page view tracking');
                return;
            }

            if (!sfftData.currentPageId) {
                utils.log('No page ID available for tracking');
                return;
            }

            var utmParams = utmManager.getStoredUtmParams();
            var sessionId = utils.getSessionId();

            utils.log('Tracking page view', {
                pageId: sfftData.currentPageId,
                utmParams: utmParams
            });

            $.ajax({
                url: sfftData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'track_page_view',
                    page_id: sfftData.currentPageId,
                    session_id: sessionId,
                    utm_source: utmParams.utm_source,
                    utm_medium: utmParams.utm_medium,
                    utm_campaign: utmParams.utm_campaign,
                    utm_content: utmParams.utm_content,
                    utm_term: utmParams.utm_term,
                    security: sfftData.security
                },
                success: function(response) {
                    utils.log('Page view tracked successfully', response);
                },
                error: function(xhr, status, error) {
                    utils.log('Error tracking page view', { status: status, error: error });
                }
            });
        }
    };

    /**
     * UTM Table Management (for admin interface)
     */
    window.ffstUpdateUTMTable = function(funnelName) {
        var utmSource = $('#ffst_utm_source_' + funnelName).val();
        var utmMedium = $('#ffst_utm_medium_' + funnelName).val();
        var utmCampaign = $('#ffst_utm_campaign_' + funnelName).val();
        var utmContent = $('#ffst_utm_content_' + funnelName).val();

        $.ajax({
            url: sfftData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ffst_update_utm_table',
                funnel_name: funnelName,
                utm_source: utmSource,
                utm_medium: utmMedium,
                utm_campaign: utmCampaign,
                utm_content: utmContent,
                security: sfftData.security
            },
            success: function(response) {
                if (response.success) {
                    $('#ffst_utm_table_' + funnelName).html(response.data.html);
                } else {
                    utils.log('Error updating UTM table', response.data);
                }
            },
            error: function() {
                utils.log('UTM table update request failed');
            }
        });
    };

    /**
     * Consent Management Integration
     */
    var consentManager = {
        // Initialize consent monitoring
        init: function() {
            this.monitorConsentChanges();
        },

        // Monitor for consent changes
        monitorConsentChanges: function() {
            // Listen for consent status changes
            $(document).on('sfft_consent_changed', function(event, consentStatus) {
                utils.log('Consent status changed', consentStatus);
                if (consentStatus === 'accepted') {
                    // Re-initialize tracking if consent is given
                    setTimeout(function() {
                        fluentFormsTracker.init();
                        pageViewTracker.track();
                    }, 100);
                }
            });
        }
    };

    /**
     * Main initialization
     */
    $(document).ready(function() {
        utils.log('Simple Funnel Tracker initializing', {
            version: sfftData.version,
            pageId: sfftData.currentPageId,
            consentEnabled: sfftData.cookieConsentEnabled,
            hasConsent: utils.hasTrackingConsent()
        });

        // Initialize UTM parameter capture
        utmManager.getUtmParams();

        // Initialize consent monitoring
        consentManager.init();

        // Initialize tracking if consent is given
        if (utils.hasTrackingConsent()) {
            // Track page view
            pageViewTracker.track();

            // Initialize FluentForms tracking
            fluentFormsTracker.init();
        } else {
            utils.log('Tracking consent not given, waiting for consent');
        }

        // Store session ID in cookie if consent allows
        if (utils.hasTrackingConsent()) {
            var sessionId = utils.getSessionId();
            utils.setCookie('sfft_session_id', sessionId, 30);
        }
    });

    // Expose public API
    window.SFFT = {
        utils: utils,
        utmManager: utmManager,
        fluentFormsTracker: fluentFormsTracker,
        pageViewTracker: pageViewTracker,
        consentManager: consentManager
    };

})(jQuery);