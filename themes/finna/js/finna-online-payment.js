finna.onlinePayment = (function() {

    var registerPayment = function(returnUrl) {
        var url = path + '/AJAX/JSON?' + $.param({method:'registerOnlinePayment'});
        $.ajax({
            type: 'POST',
            url:  url,
            data: {url: returnUrl},
            dataType: 'json',
            success: function(response) {
                location.href = response.data;
            },
            error: function(jqXHR, status, error) {
                location.reload();
            }
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
