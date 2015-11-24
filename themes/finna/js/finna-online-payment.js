/*global VuFind*/
finna.onlinePayment = (function() {

    var registerPayment = function(params) {
        var url = VuFind.getPath() + '/AJAX/registerOnlinePayment';
        $.ajax({
            type: 'POST',
            url:  url,
            data: jQuery.parseJSON(params),
            dataType: 'json',
            success: function(response) {
                location.href = response.data;
            },
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
