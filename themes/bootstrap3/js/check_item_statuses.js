/*global VuFind */
function linkCallnumbers(callnumber, callnumber_handler) {
  if (callnumber_handler) {
    var cns = callnumber.split(',\t');
    for (var i = 0; i < cns.length; i++) {
      cns[i] = '<a href="' + VuFind.path + '/Alphabrowse/Home?source=' + encodeURI(callnumber_handler) + '&amp;from=' + encodeURI(cns[i]) + '">' + cns[i] + '</a>';
    }
    return cns.join(',\t');
  }
  return callnumber;
}

function checkItemStatuses(_container) {
  var container = _container || $('body');

  var elements = {};
  var data = $.map(container.find('.ajaxItem'), function ajaxItemMap(record) {
    if ($(record).find('.hiddenId').length === 0) {
      return null;
    }
    var datum = $(record).find('.hiddenId').val();
    if (typeof elements[datum] === 'undefined') {
      elements[datum] = $();
    }
    elements[datum] = elements[datum].add($(record));
    return datum;
  });
  if (!data.length) {
    return;
  }

  $(".ajax-availability").removeClass('hidden');
  $.ajax({
    dataType: 'json',
    method: 'POST',
    url: VuFind.path + '/AJAX/JSON?method=getItemStatuses',
    data: {'id': data}
  })
  .done(function checkItemStatusDone(response) {
    $.each(response.data, function checkItemDoneEach(i, result) {
      var item = elements[result.id];
      if (!item) {
        return;
      }

      item.find('.status').empty().append(result.availability_message);
      if (typeof(result.full_status) != 'undefined'
        && result.full_status.length > 0
        && item.find('.callnumAndLocation').length > 0
      ) {
        // Full status mode is on -- display the HTML and hide extraneous junk:
        item.find('.callnumAndLocation').empty().append(result.full_status);
        item.find('.callnumber').addClass('hidden');
        item.find('.location').addClass('hidden');
        item.find('.hideIfDetailed').addClass('hidden');
        item.find('.status').addClass('hidden');
      } else if (typeof(result.missing_data) != 'undefined'
        && result.missing_data
      ) {
        // No data is available -- hide the entire status area:
        item.find('.callnumAndLocation').addClass('hidden');
        item.find('.status').addClass('hidden');
      } else if (result.locationList) {
        // We have multiple locations -- build appropriate HTML and hide unwanted labels:
        item.find('.callnumber').addClass('hidden');
        item.find('.hideIfDetailed').addClass('hidden');
        item.find('.location').addClass('hidden');
        var locationListHTML = "";
        for (var x = 0; x < result.locationList.length; x++) {
          locationListHTML += '<div class="groupLocation">';
          if (result.locationList[x].availability) {
            locationListHTML += '<i class="fa fa-ok text-success" aria-hidden="true"></i> <span class="text-success">'
              + result.locationList[x].location + '</span> ';
          } else if (typeof(result.locationList[x].status_unknown) !== 'undefined'
              && result.locationList[x].status_unknown
          ) {
            if (result.locationList[x].location) {
              locationListHTML += '<i class="fa fa-status-unknown text-warning" aria-hidden="true"></i> <span class="text-warning">'
                + result.locationList[x].location + '</span> ';
            }
          } else {
            locationListHTML += '<i class="fa fa-remove text-danger" aria-hidden="true"></i> <span class="text-danger"">'
              + result.locationList[x].location + '</span> ';
          }
          locationListHTML += '</div>';
          locationListHTML += '<div class="groupCallnumber">';
          locationListHTML += (result.locationList[x].callnumbers)
               ? linkCallnumbers(result.locationList[x].callnumbers, result.locationList[x].callnumber_handler) : '';
          locationListHTML += '</div>';
        }
        item.find('.locationDetails').removeClass('hidden');
        item.find('.locationDetails').empty().append(locationListHTML);
      } else {
        // Default case -- load call number and location into appropriate containers:
        item.find('.callnumber').empty().append(linkCallnumbers(result.callnumber, result.callnumber_handler) + '<br/>');
        item.find('.location').empty().append(
          result.reserve === 'true'
            ? result.reserve_message
            : result.location
        );
      }
    });

    $(".ajax-availability").removeClass('ajax-availability');
  })
  .fail(function checkItemStatusFail(response, textStatus) {
    $('.ajax-availability').empty();
    if (textStatus === 'abort' || typeof response.responseJSON === 'undefined') { return; }
    // display the error message on each of the ajax status place holder
    $('.ajax-availability').append(response.responseJSON.data).addClass('text-danger');
  });
}

$(document).ready(function checkItemStatusReady() {
  checkItemStatuses();
});
