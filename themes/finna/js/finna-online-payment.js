/*global VuFind*/
finna.onlinePayment = (function() {

    var registerPayment = function(params) {
        var url = VuFind.path + '/AJAX/registerOnlinePayment';
        $.ajax({
            type: 'POST',
            url:  url,
            data: jQuery.parseJSON(params),
            dataType: 'json'
        }).done(function(response) {
            location.href = response.data;
        })
        .fail(function(response, textStatus) {
            var redirect = '';
            if (typeof response.responseJSON == 'undefined') {
                redirect = window.location.href.split('?')[0];
            } else {
                redirect = response.responseJSON.data;
            }
            location.href = redirect;
        });
    
        return false;
    };

    var my = {
        registerPayment: registerPayment,
        init: function() {
        },
    };

    return my;
})(finna);
