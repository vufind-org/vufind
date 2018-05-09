/*global VuFind*/
/*exported checkRelaisAvailability, relaisAddItem*/

function hideRelaisAvailabilityCheckMessages() {
  $('.relaisLink').addClass('hidden');
}

function checkRelaisAvailability(addLink, oclc) {
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
    success: function relaisAvailabilitySuccessCallback(response) {
      if (response.data === "ok") {
        $("span[class='relaisLink']").each(function relaisLinkFormatter() {
          var $current = $(this);
          var text = VuFind.translate('relais_request');
          $current.html('<a class="relaisRecordButton" class="modal-link">' + text + '</a>');
          $current.find('.relaisRecordButton').click(function relaisAddOnClick() { VuFind.lightbox.ajax({url: addLink + '?' + $.param({oclc: oclc})}); });
        });
      } else {
        hideRelaisAvailabilityCheckMessages();
      }
    },
    error: hideRelaisAvailabilityCheckMessages
  });
}

function calcelRelaisRequestOnClick() {
  $('#modal').modal('hide'); // hide the modal 
  $('#modal-dynamic-content').empty(); // empties dynamic content
  $('.modal-backdrop').remove(); // removes all modal-backdrops
}

function relaisRequestErrorCallback(failLink) {
  $('#requestButton').html("<input class='btn btn-primary' data-dismiss='modal' id='cancelRelaisRequest' type='submit' value='" + VuFind.translate('close') + "'>");
  $('#requestMessage').html(VuFind.translate('relais_error_html', {'%%url%%': failLink}));
  $('#cancelRelaisRequest').unbind('click').click(calcelRelaisRequestOnClick);
}

function makeRelaisRequest(url, failLink) {
  $('#requestButton').html(
    '<i class="fa fa-spinner fa-spin"></i>' + VuFind.translate('relais_requesting')
  );
  $.ajax({
    dataType: 'json',
    url: url,
    success: function relaisRequestSuccessCallback(response) {
      status = response.status;
      var obj = jQuery.parseJSON(response.data);
      //alert("in success");
      if (status === "ERROR") {
        relaisRequestErrorCallback(failLink)
      } else {
        $('#requestButton').html("<input class='btn btn-primary' data-dismiss='modal' id='cancelRelaisRequest' type='submit' value='" + VuFind.translate('close') + "'>");
        $('#requestMessage').html("<b>" + VuFind.translate('relais_success_label') + "</b> " + VuFind.translate('relais_success_message', {'%%id%%': obj.RequestNumber}));
        $('#cancelRelaisRequest').unbind('click').click(calcelRelaisRequestOnClick);
      }
    },
    error: function makeRelaisRequestErrorWrapper() { relaisRequestErrorCallback(failLink); }
  });
}

function relaisAddItem(oclc, failLink) {
  var url = VuFind.path + '/AJAX/JSON?' + $.param({
    method: 'relaisInfo',
    oclcNumber: oclc
  });
  $.ajax({
    dataType: 'json',
    url: url,
    success: function relaisInfoSuccessCallback(response) {
      var obj = response.status === "ERROR" ? {} : jQuery.parseJSON(response.data);
      if (obj.Available) {
        $('#requestMessage').html(VuFind.translate('relais_available'));
        $('#requestButton').html("<input class='btn btn-primary' id='makeRelaisRequest' type='submit' value='" + VuFind.translate('confirm_dialog_yes') + "'>"
                                 + "&nbsp;<input class='btn btn-primary' data-dismiss='modal' id='cancelRelaisRequest' type='submit' value='" + VuFind.translate('confirm_dialog_no') + "'>");
        $('#makeRelaisRequest').unbind('click').click(function makeRelaisRequestOnClick() {
          var orderUrl = VuFind.path + '/AJAX/JSON?' + $.param({
            method: 'relaisOrder',
            oclcNumber: oclc
          });
          makeRelaisRequest(orderUrl, failLink);
        });
        $('#cancelRelaisRequest').unbind('click').click(calcelRelaisRequestOnClick);
      } else {
        relaisRequestErrorCallback(failLink);
      }
    },
    error: function relaisAddItemErrorWrapper() { relaisRequestErrorCallback(failLink); }
  });
}
