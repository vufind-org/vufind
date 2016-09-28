/*global VuFind*/
finna.changeHolds = (function() {
    var setupChangeHolds = function () {
        var holds = $('.changeHolds');
        var errorOccured = $('<div></div>').attr('class', 'alert alert-danger').text(VuFind.translate('error_occurred'));
        
        holds.click(function() {   
            var hold = $(this);
            hold.find('.hold-change-success').remove();
            var pickupLocations = $(this).find('.pickup-locations');
            if (!pickupLocations.data('populated')) {
                pickupLocations.data('populated', 1);
                var spinnerLoad = $(this).find('.pickup-location-load-indicator');
                spinnerLoad.removeClass('hidden');
                var recordId = $(this).data('record-id');
                var requestId = $(this).data('request-id');
                var params = {
                    method: 'getRequestGroupPickupLocations',
                    id: recordId,
                    requestGroupId: '0'
                };
                $.ajax({
                    data: params,
                    dataType: 'json',
                    cache: false,
                    url: VuFind.path + '/AJAX/JSON'
                })
                .done(function(response) {
                    $.each(response.data.locations, function() {
                        var item = $('<li class="pickupLocationItem" role="menuitem"></li>')
                            .data('locationId', this.locationID).data('locationDisplay', this.locationDisplay).data('requestId', requestId).data('hold', hold).click(submitHandler);
                        var text = $('<a></a>').text(this.locationDisplay);
                        item.append(text);
                        pickupLocations.append(item);
                    });
                    spinnerLoad.addClass('hidden');
                })
                .fail(function() {
                    spinnerLoad.addClass('hidden');
                    holds.append(errorOccured);
                    pickupLocations.data('populated', 0);
                });
            }
        });
        
        var submitHandler = function() {
            $().dropdown('toggle');
            var selected = $(this);           
            var requestId = selected.data('requestId');
            var locationId = selected.data('locationId');
            var locationDisplay = selected.data('locationDisplay');            
            var hold = selected.data('hold');
            
            var spinnerChange = hold.find('.pickup-change-load-indicator');
            spinnerChange.removeClass('hidden');

            var pickupLocationsSelected = hold.find('.pickupLocationSelected');
            pickupLocationsSelected.text(locationDisplay);

            var params = {
                method: 'changePickupLocation',
                requestId: requestId,
                pickupLocationId: locationId
            };
            $.ajax({
                data: params,
                dataType: 'json',
                cache: false,
                url: VuFind.path + '/AJAX/JSON'
            })
            .done(function(response) {
                spinnerChange.addClass('hidden');
                if (response.data['success']){
                    var success = $('<div></div>').attr('class', 'alert alert-success hold-change-success').text(VuFind.translate('change_hold_success'));
                    hold.append(success);
                } else {
                    hold.append(errorOccured);
                }  
            })
            .fail(function() {
                spinnerChange.addClass('hidden');        
                hold.append(errorOccured);
            });
        };
    }
    
    var my = {
            init: function() {
                setupChangeHolds();
            }
        };

    return my;

})(finna);