/*global path*/
function linkCallnumbers(callnumber, callnumber_handler) {
  if (callnumber_handler) {
    var cns = callnumber.split(',\t');
    for (var i = 0; i < cns.length; i++) {
      cns[i] = '<a href="' + path + '/Alphabrowse/Home?source=' + encodeURI(callnumber_handler) + '&amp;from=' + encodeURI(cns[i]) + '">' + cns[i] + '</a>';
    }
    return cns.join(',\t');
  }
  return callnumber;
}

function checkItemStatuses() {
    var id = $.map($('.ajaxItemId'), function(i) {
        return $(i).find('.hiddenId')[0].value;
    });
    if (id.length) {
        $(".ajax_availability").show();
        $.post(
            path + '/AJAX/JSON?method=getItemStatuses',
            {id:id},
            function(response) {
                if (response.status == 'OK') {
                    $.each(response.data, function(i, result) {
                        var item = $($('.ajaxItemId')[result.record_number]);
                        console.log(result);

                        item.find('.status').empty().append(result.availability_message);
                        if (typeof(result.missing_data) != 'undefined'
                            && result.missing_data
                        ) {
                            // No data is available -- hide the entire status area:
                            item.find('.callnumAndLocation').hide();
                            item.find('.status').hide();
                        } else if (result.locationList) {
                            // Not supported in this theme:
                            item.find('.callnumAndLocation').hide();
                            item.find('.status').hide();
                        } else {
                            // Default case -- load call number and location into appropriate containers:
                            item.find('.callnumber').empty().append(linkCallnumbers(result.callnumber, result.callnumber_handler));
                            item.find('.location').empty().append(
                                result.reserve == 'true'
                                ? result.reserve_message
                                : result.location
                            );
                        }
                    });
                } else {
                    // display the error message on each of the ajax status place holder
                    $(".ajax_availability").empty().append(response.data);
                }
                $(".ajax_availability").removeClass('ajax_availability');
            },
            'json'
        );
    }
}

$('.results-page').live('pageshow', function() {
    checkItemStatuses();
});
