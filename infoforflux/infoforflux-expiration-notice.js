// js/infoforflux-expiration-notice.js
jQuery(document).ready(function($) {
    $('.infoforflux-expiration-notice .notice-dismiss').on('click', function() {
        // Send AJAX request to mark the notice as dismissed
        $.post(infoforflux_ajax.ajax_url, {
            action: infoforflux_ajax.action
        });
    });
});
