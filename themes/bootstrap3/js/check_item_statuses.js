/*global VuFind */

function checkItemStatuses(container) {
  if (typeof(container) == 'undefined') {
    container = $('body');
  }

  var elements = {};    
  var data = $.map(container.find('.ajaxItem'), function(record) {
    if ($(record).find('.hiddenId').length == 0) {
      return false;
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
    data: {'id':data}
  })
  .done(function(response) {
    $.each(response.data, function(i, result) {
      var item = elements[result.id];
      if (!item) {
        console.log('Unexpected selector from getItemStatuses: ' + sel);
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
        for (var x=0; x<result.locationList.length; x++) {
          locationListHTML += '<div class="groupLocation">';
          if (result.locationList[x].availability) {
            locationListHTML += '<i class="fa fa-ok text-success"></i> <span class="text-success">'
              + result.locationList[x].location + '</span> ';
          } else if (typeof(result.locationList[x].status_unknown) !== 'undefined'
              && result.locationList[x].status_unknown
          ) {
            if (result.locationList[x].location) {
              locationListHTML += '<i class="fa fa-status-unknown text-warning"></i> <span class="text-warning">'
                + result.locationList[x].location + '</span> ';
            }
          } else {
            locationListHTML += '<i class="fa fa-remove text-danger"></i> <span class="text-danger"">'
              + result.locationList[x].location + '</span> ';
          }
          locationListHTML += '</div>';
          locationListHTML += '<div class="groupCallnumber">';
          locationListHTML += (result.locationList[x].callnumbers)
               ?  result.locationList[x].callnumbers : '';
          locationListHTML += '</div>';
        }
        item.find('.locationDetails').removeClass('hidden');
        item.find('.locationDetails').empty().append(locationListHTML);
      } else {
        // Default case -- load call number and location into appropriate containers:
        item.find('.callnumber').empty().append(result.callnumber+'<br/>');
        item.find('.location').empty().append(
          result.reserve == 'true'
          ? result.reserve_message
          : result.location
        );
      }
    });

    $(".ajax-availability").removeClass('ajax-availability');
  })
  .fail(function(response, textStatus) {
    $('.ajax-availability').empty();
    if (textStatus == 'abort' || typeof response.responseJSON === 'undefined') { return; }
    // display the error message on each of the ajax status place holder
    $('.ajax-availability').append(response.responseJSON.data).addClass('text-danger');
  });
}

$(document).ready(function() {
  checkItemStatuses();
});
