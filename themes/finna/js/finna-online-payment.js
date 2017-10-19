/*global VuFind, finna */
finna.onlinePayment = (function finnaOnlinePayment() {

  function registerPayment(params) {
    var url = VuFind.path + '/AJAX/registerOnlinePayment';
    $.ajax({
      type: 'POST',
      url: url,
      data: jQuery.parseJSON(params),
      dataType: 'json'
    })
      .done(function onRegisterPaymentDone(response) {
        location.href = response.data;
      })
      .fail(function onRegisterPaymentFail(response/*, textStatus*/) {
        var redirect = '';
        if (typeof response.responseJSON === 'undefined') {
          redirect = window.location.href.split('?')[0];
        } else {
          redirect = response.responseJSON.data;
        }
        location.href = redirect;
      });

    return false;
  }

  var my = {
    registerPayment: registerPayment
  };

  return my;
})();
