/*global path */
function setUpHoldRequestForm(recordId) {
  $('#requestGroupId').change(function() {
    var $emptyOption = $("#pickUpLocation option[value='']");
    $("#pickUpLocation option[value!='']").remove();
    if ($('#requestGroupId').val() === '') {
        $('#pickUpLocation').attr('disabled', 'disabled');
        return;
    }
    $('#pickUpLocationLabel i').addClass("fa fa-spinner icon-spin");
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
        }
        $('#pickUpLocationLabel i').removeClass("fa fa-spinner icon-spin");
        $('#pickUpLocation').removeAttr('disabled');
      },
      fail: function() {
        $('#pickUpLocationLabel i').removeClass("fa fa-spinner icon-spin");
        $('#pickUpLocation').removeAttr('disabled');
      }
    });   
  });
  $('#requestGroupId').change();
}
