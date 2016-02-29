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
            console.log(response, textStatus);
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
