jQuery(function ($) {
    window.requestFGLeadRequest = function (formData) {
        jQuery.ajax({
            url: fglo_params.ajaxurl,
            data: {action: 'fg_leads_organizer_ajax_request', data: formData},
            dataType: "json"
        }).done(function (data) {
            console.log('Success')
        }).fail(function (err) {
            console.log(err)
        })
    }
})