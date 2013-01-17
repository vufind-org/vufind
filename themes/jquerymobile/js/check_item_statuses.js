/*global path*/

function checkItemStatuses() {
    var id = $.map($('.ajaxItemId'), function(i) {
        return $(i).find('.hiddenId')[0].value;
    });
    if (id.length) {
        $(".ajax_availability").show();
        $.ajax({
            dataType: 'json',
            url: path + '/AJAX/JSON?method=getItemStatuses',
            data: {id:id},
            success: function(response) {
                if (response.status == 'OK') {
                    $.each(response.data, function(i, result) {
                        var item = $($('.ajaxItemId')[result.record_number]);

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
                            item.find('.callnumber').empty().append(result.callnumber);
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
            }
        });
    }
}

$('.results-page').live('pageshow', function() {
    checkItemStatuses();
});
