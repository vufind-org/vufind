/*global path*/

function checkItemStatus(id) {
  var safeId = jqEscape(id);
  var item = $('.hiddenId[value="' + safeId + '"]').closest('.ajaxItem');
  item.find(".ajax-availability").removeClass('hidden');

  $.ajax({
    dataType: 'json',
    url: path + '/AJAX/JSON?method=getItemStatuses',
    data: {id:[id]},
    success: function(response) {
      if(response.status == 'OK') {
        $.each(response.data, function(i, result) {
          item.find(".ajax-availability").removeClass('hidden');
          item.find('.no-holdings').addClass('hidden');
          
          item.find('.status').empty().append(result.availability_message);
          item.find('.dedup-select').removeAttr('selected').
            find('option[value="' + jqEscape(result.record_number) + '"]').attr('selected', '1');
          
          if (typeof(result.full_status) != 'undefined'
            && result.full_status.length > 0
            && item.find('.callnumAndLocation').length > 0
          ) {
            // Full status mode is on -- display the HTML and hide extraneous junk:
            item.find('.locationDetails').empty().append(result.full_status);
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
            item.find('.no-holdings').removeClass('hidden');
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
              } else {
                locationListHTML += '<i class="fa fa-remove text-error"></i> <span class="text-error"">'
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
      } else {
        // display the error message on each of the ajax status place holder
        $(".ajax-availability").empty().append(response.data);
      }
      $(".ajax-availability").removeClass('ajax-availability');
    }
  });
}

function initDedupRecordSelection() 
{
  $('.dedup-select').change(function() {
    var id = $(this).val();
    var source = $(this).find('option:selected').data('source');
    $.cookie('preferredRecordSource', source);

    var recordContainer = $(this).closest('.ajaxItem');
    var oldRecordId = recordContainer.find('.hiddenId')[0].value;

    // Update IDs of elements
    recordContainer.find('.hiddenId').val(id);
     
    // Update IDs of elements
    recordContainer.find('[id="' + oldRecordId + '"]').each(function() {
      $(this).attr('id', id);
    });

    // Update links as well
    recordContainer.find('a').each(function() {
      if (typeof $(this).attr('href') !== 'undefined') {
        $(this).attr('href', $(this).attr('href').replace(oldRecordId, id));
      }
    })

    recordContainer.find('.locationDetails').addClass('hidden');
    recordContainer.find('.callnumber').removeClass('hidden');
    recordContainer.find('.location').removeClass('hidden');
    checkItemStatus(id);
  });
}

$(document).ready(function() {
  $('.ajaxItem').each(function(i, record) {
    var id = $(record).find('.hiddenId')[0].value;
    checkItemStatus(id);
  });
  initDedupRecordSelection();
});