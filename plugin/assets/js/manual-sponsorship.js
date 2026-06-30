/* global bssManual, ajaxurl */
(function ($) {
    'use strict';

    function fillIfEmpty(selector, value) {
        var $el = $(selector);
        if ($el.val() === '' && value) {
            $el.val(value);
        }
    }

    $('#email').on('blur', function () {
        var email = $.trim($(this).val());
        if (!email) {
            return;
        }

        $.post(ajaxurl, {
            action: 'bss_lookup_contact_by_email',
            email: email,
            _ajax_nonce: bssManual.nonce,
        }, function (response) {
            if (!response.success || !response.data.found) {
                $('#bss-contact-found').hide();
                return;
            }

            var c = response.data;

            fillIfEmpty('#contact_name', c.contact_name);
            fillIfEmpty('#phone', c.phone);
            fillIfEmpty('#address_line1', c.address_line1);
            fillIfEmpty('#address_line2', c.address_line2);
            fillIfEmpty('#city', c.city);
            fillIfEmpty('#county', c.county);
            fillIfEmpty('#postcode', c.postcode);
            fillIfEmpty('#country', c.country);

            if (c.marketing_opt_in) {
                $('#marketing_opt_in').prop('checked', true);
            }
            if (c.gift_aid_declared) {
                $('#gift_aid_declared').prop('checked', true);
            }

            $('#bss-contact-found').show();
        });
    });
}(jQuery));
