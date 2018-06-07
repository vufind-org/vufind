/*global VuFind*/
VuFind.register('relais', function Relais() {
  function hideAvailabilityCheckMessages(failLink) {
    $("span[class='relaisLink']").each(function linkFormatter() {
      var $current = $(this);
      var text = VuFind.translate('relais_search');
      $current.html('<a class="relaisRecordButton" target="new" href="' + failLink + '">' + text + '</a>');
    });
  }

  function checkAvailability(addLink, oclc, failLink) {
    // Don't waste time checking availability if there are no links!
    if (!$('.relaisLink').length) {
      return false;
    }

    var url = VuFind.path + '/AJAX/JSON?' + $.param({
      method: 'relaisAvailability',
      oclcNumber: oclc
    });
    $.ajax({
      dataType: 'json',
      url: url,
      success: function checkAvailabilitySuccessCallback(response) {
        if (response.data.result === "ok") {
          $("span[class='relaisLink']").each(function linkFormatter() {
            var $current = $(this);
            var text = VuFind.translate('relais_request');
            $current.html('<a class="relaisRecordButton" class="modal-link">' + text + '</a>');
            $current.find('.relaisRecordButton').click(function addRecordButtonOnClick() { VuFind.lightbox.ajax({url: addLink + '?' + $.param({oclc: oclc, failLink: failLink})}); });
          });
        } else {
          hideAvailabilityCheckMessages(failLink);
        }
      },
      error: function checkAvailabilityError() { hideAvailabilityCheckMessages(failLink); }
    });
  }

  function cancelRequestOnClick() {
    $('#modal').modal('hide'); // hide the modal
    $('#modal-dynamic-content').empty(); // empties dynamic content
    $('.modal-backdrop').remove(); // removes all modal-backdrops
  }

  function errorCallback(failLink) {
    $('#requestButton').html("<input class='btn btn-primary' data-dismiss='modal' id='cancelRelaisRequest' type='submit' value='" + VuFind.translate('close') + "'>");
    $('#requestMessage').html(VuFind.translate('relais_error_html', {'%%url%%': failLink}));
    $('#cancelRelaisRequest').unbind('click').click(cancelRequestOnClick);
  }

  function makeRequest(url, failLink) {
    $('#requestButton').html(
      '<i class="fa fa-spinner fa-spin"></i> ' + VuFind.translate('relais_requesting')
    );
    $.ajax({
      dataType: 'json',
      url: url,
      success: function makeRequestSuccessCallback(response) {
        var obj = jQuery.parseJSON(response.data.result);
        $('#requestButton').html("<input class='btn btn-primary' data-dismiss='modal' id='cancelRelaisRequest' type='submit' value='" + VuFind.translate('close') + "'>");
        $('#requestMessage').html("<b>" + VuFind.translate('relais_success_label') + "</b> " + VuFind.translate('relais_success_message', {'%%id%%': obj.RequestNumber}));
        $('#cancelRelaisRequest').unbind('click').click(cancelRequestOnClick);
      },
      error: function makeRequestErrorWrapper() { errorCallback(failLink); }
    });
  }

  function addItem(oclc, failLink) {
    var url = VuFind.path + '/AJAX/JSON?' + $.param({
      method: 'relaisInfo',
      oclcNumber: oclc
    });
    $.ajax({
      dataType: 'json',
      url: url,
      success: function infoSuccessCallback(response) {
        var obj = jQuery.parseJSON(response.data.result);
        if (obj && obj.Available) {
          $('#requestMessage').html(VuFind.translate('relais_available'));
          $('#requestButton').html(
            "<input class='btn btn-primary' id='makeRelaisRequest' type='submit' value='" + VuFind.translate('confirm_dialog_yes') + "'>"
            + "&nbsp;<input class='btn btn-primary' data-dismiss='modal' id='cancelRelaisRequest' type='submit' value='" + VuFind.translate('confirm_dialog_no') + "'>"
          );
          $('#makeRelaisRequest').unbind('click').click(function makeRequestOnClick() {
            var orderUrl = VuFind.path + '/AJAX/JSON?' + $.param({
              method: 'relaisOrder',
              oclcNumber: oclc
            });
            makeRequest(orderUrl, failLink);
          });
          $('#cancelRelaisRequest').unbind('click').click(cancelRequestOnClick);
        } else {
          errorCallback(failLink);
        }
      },
      error: function addItemErrorWrapper() { errorCallback(failLink); }
    });
  }

  return {
    checkAvailability: checkAvailability,
    addItem: addItem
  };
});
