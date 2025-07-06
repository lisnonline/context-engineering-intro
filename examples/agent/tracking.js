jQuery(document).ready(function($) {
    if ($('.fluentform').length === 0) {
        return;
    }

    function getUrlParam(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    var utmSource = getUrlParam('utm_source');
    var utmMedium = getUrlParam('utm_medium');
    var utmCampaign = getUrlParam('utm_campaign');
    var utmContent = getUrlParam('utm_content');

    function getCurrentStep() {
        var steps = $('.ff-step-titles li');
        if (steps.length > 0) {
            var activeStep = steps.filter('.ff_active');
            if (activeStep.length > 0) {
                var stepNum = parseInt(activeStep.data('step-number')) + 1;
                if (isNaN(stepNum)) stepNum = 1;
                return { current: stepNum, total: steps.length };
            }
        }
        return null;
    }

    function trackStep(step, total) {
        var form = $('[data-name^="form_step-"]');
        var formName = form.data('name');
        var formId = formName && formName.match(/form_step-(\d+)/) ? parseInt(formName.match(/form_step-(\d+)/)[1]) : null;

        if (!formId) return;

        var tracked = JSON.parse(sessionStorage.getItem('trackedSteps')) || [];
        if (tracked.includes(step)) return;

        tracked.push(step);
        sessionStorage.setItem('trackedSteps', JSON.stringify(tracked));
        var pageId = parseInt(ffstData.currentPageId);

        console.log('Current Page ID:', pageId);
        console.log('Form:', form);
        console.log('Form ID:', formId);
        console.log('Step Index:', step);
        console.log('Total Steps:', total);
        console.log('utm_source:', utmSource);
        console.log('utm_medium:', utmMedium);
        console.log('utm_campaign:', utmCampaign);
        console.log('utm_content:', utmContent);

        $.post('/wp-admin/admin-ajax.php', {
            action: 'ffst_track_form_step',
            form_id: formId,
            step_index: step,
            total_steps: total,
            page_id: pageId,
            utm_source: utmSource,
            utm_medium: utmMedium,
            utm_campaign: utmCampaign,
            utm_content: utmContent,
            security: ffstData.security
        }).done(function(response) {
            console.log(response);
        }).fail(function() {
            console.log('Die Anforderung ist fehlgeschlagen.');
        });
    }

    var initialStep = getCurrentStep();
    if (initialStep) {
        trackStep(initialStep.current, initialStep.total);
    }

    $('.ff-btn-next').on('click', function() {
        setTimeout(function() {
            var stepData = getCurrentStep();
            if (stepData) {
                trackStep(stepData.current, stepData.total);
            }
        }, 50);
    });
});

jQuery(document).ready(function($) {
    var pageId = parseInt(ffstData.currentPageId);
    console.log('Current Page ID:', pageId);

    function getUrlParam(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    var utmSource = getUrlParam('utm_source');
    var utmMedium = getUrlParam('utm_medium');
    var utmCampaign = getUrlParam('utm_campaign');
    var utmContent = getUrlParam('utm_content');

    $.post(ffstData.ajaxUrl, {
        action: 'track_page_view',
        page_id: pageId,
        utm_source: utmSource,
        utm_medium: utmMedium,
        utm_campaign: utmCampaign,
        utm_content: utmContent,
        security: ffstData.security
    });
});

function ffstUpdateUTMTable(funnelName) {
    var utmSource = jQuery('#ffst_utm_source_' + funnelName).val();
    var utmMedium = jQuery('#ffst_utm_medium_' + funnelName).val();
    var utmCampaign = jQuery('#ffst_utm_campaign_' + funnelName).val();
    var utmContent = jQuery('#ffst_utm_content_' + funnelName).val();

    jQuery.ajax({
        url: ffstData.ajaxUrl,
        type: 'POST',
        data: {
            action: 'ffst_update_utm_table',
            funnel_name: funnelName,
            utm_source: utmSource,
            utm_medium: utmMedium,
            utm_campaign: utmCampaign,
            utm_content: utmContent,
            security: ffstData.security
        },
        success: function(response) {
            jQuery('#ffst_utm_table_' + funnelName).html(response);
        },
        error: function() {
            console.error('Die Anfrage konnte nicht abgeschlossen werden.');
        }
    });
}