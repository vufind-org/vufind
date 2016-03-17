/*global VuFind,checkSaveStatuses*/
finna.changeHolds = (function() {

    var setupChangeHolds = function () {
        
        var holds = $(".changeHolds");
        var errorOccured = $('<div></div>').attr('class', 'alert alert-danger').text(VuFind.translate('error_occurred'));
        
        holds.click(function(){
            
            var spinner = $(this).find(".pickup-location-load-indicator");
            spinner.removeClass('hidden');
            
            var pickupLocation = $(this).find("#pickupLocation");         
            var defaultValue = $(this).find("#pickupLocation option:selected").text();

            var recordId = $(this).attr('recordId');
            
            var params = {
                    method: 'getRequestGroupPickupLocations',
                    id: recordId,
                    requestGroupId: '1'
                  };
            $.ajax({
              data: params,
              dataType: 'json',
              cache: false,
              url: VuFind.path + '/AJAX/JSON'
            })
        
            .done(function(response) {
                  
                pickupLocation.empty()

                $.each(response.data.locations, function() {
                  var option = $('<option></option>').attr('value', this.locationID).text(this.locationDisplay);
                  if (this.locationDisplay == defaultValue || (defaultValue == '' && this.isDefault && $emptyOption.length == 0)) {
                    option.attr('selected', 'selected');
                  }
                  pickupLocation.append(option);
                });
                
                spinner.addClass('hidden');
                  
            })
            .fail(function(){
                spinner.addClass('hidden');
                hold.append(errorOccured);
            });
        });
                     
     
        holds.change(function(){
            
            var hold = $(this);
            var spinner = $(this).find(".pickup-location-load-indicator");
            spinner.removeClass('hidden');
            
            var reservationId = $(this).attr('reservationId');
            var pickupLocationId = $(this).find("#pickupLocation option:selected").val();
            var params = {
                    method: 'changePickupLocation',
                    reservationId: reservationId,
                    pickupLocationId: pickupLocationId
                  };
            
            $.ajax({
              data: params,
              dataType: 'json',
              cache: false,
              url: VuFind.path + '/AJAX/JSON'
            })
            .done(function(response) {
                spinner.addClass('hidden');
                if (response.data['success']){
                    var success = $('<div></div>').attr('class', 'alert alert-success').text(VuFind.translate('change_reservation_success'));
                    hold.append(success);
                } else {
                    hold.append(errorOccured);
                }  
            })
            .fail(function(){
                spinner.addClass('hidden');        
                hold.append(errorOccured);
            });
        });
    }
    
    var my = {
            init: function() {
                setupChangeHolds();
            }
        };

    return my;

})(finna);


