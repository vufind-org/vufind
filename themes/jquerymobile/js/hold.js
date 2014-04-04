/*global path */
function setUpHoldRequestForm(recordId) {
  $('#requestGroupId').change(function() {
    var $emptyOption = $("#pickUpLocation option[value='']");
    $("#pickUpLocation option[value!='']").remove();
    try {
        $("#pickUpLocation").selectmenu("refresh", true);
    } catch (e) {}
    if ($('#requestGroupId').val() === '') {
        return;
    }
    $('#pickUpLocationLabel').addClass("ajax_hold_request_loading");
    var params = {
      method: 'getRequestGroupPickupLocations',
      id: recordId,
      requestGroupId: $('#requestGroupId').val()              
    };
    $.ajax({
      data: params,
      dataType: 'json',
      cache: false,
      url: path + '/AJAX/JSON',
      success: function(response) {
        if (response.status == 'OK') {
          var defaultValue = $('#pickUpLocation').data('default');
          $.each(response.data.locations, function() {
            var option = $('<option></option>').attr('value', this.locationID).text(this.locationDisplay);
            if (this.locationID == defaultValue || (defaultValue == '' && this.isDefault && $emptyOption.length == 0)) {
              option.attr('selected', 'selected');
            }
            $('#pickUpLocation').append(option);
          });
          try {
              $("#pickUpLocation").selectmenu("refresh", true);
          } catch (e) {}
        }
        $('#pickUpLocationLabel').removeClass("ajax_hold_request_loading");
      },
      fail: function() {
        $('#pickUpLocationLabel').removeClass("ajax_hold_request_loading");
      }
    });   
  });
  $('#requestGroupId').change();
}
