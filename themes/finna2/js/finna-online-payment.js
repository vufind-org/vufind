/*global VuFind, finna */
finna.onlinePayment = (function finnaOnlinePayment() {

  function registerPayment(params) {
    var url = VuFind.path + '/AJAX/JSON?method=registerOnlinePayment';
    $.ajax({
      type: 'POST',
      url: url,
      data: jQuery.parseJSON(params),
      dataType: 'json'
    })
      .done(function onRegisterPaymentDone() {
        // Clear account notification cache and reload current page without parameters
        VuFind.account.clearCache();
        location.href = window.location.href.split('?')[0];
      })
      .fail(function onRegisterPaymentFail() {
        // Reload current page without parameters
        location.href = window.location.href.split('?')[0];
      });

    return false;
  }

  var my = {
    registerPayment: registerPayment
  };

  return my;
})();
