/*global VuFind */
/*exported setUpHoldRequestForm, setupHoldEditForm */
function setUpHoldRequestForm(recordId) {
  var $select = $('#pickUpLocation');
  var $icon = $('#pickUpLocationLabel .loading-icon');
  var $emptyOption = $("#pickUpLocation option[value='']");
  var $noResults = $('<span/>').text(VuFind.translate('No pickup locations available'));
  $select.parent().append($noResults);
  $noResults.hide();

  $('#requestGroupId').on("change", function requestGroupChange() {
    var $self = $(this);
    $select.find("option[value!='']").remove();
    if ($self.val() === '') {
      $select.attr('disabled', 'disabled');
      return;
    }
    $icon.removeClass('hidden');
    var params = {
      method: 'getRequestGroupPickupLocations',
      id: recordId,
      requestGroupId: $self.val()
    };
    $.ajax({
      data: params,
      dataType: 'json',
      cache: false,
      url: VuFind.path + '/AJAX/JSON'
    })
      .done(function holdPickupLocationsDone(response) {
        var defaultValue = $select.data('default');
        if (response.data.locations && response.data.locations.length > 0) {
          $noResults.hide();
          $.each(response.data.locations, function holdPickupLocationEach() {
            var option = $('<option/>').attr('value', this.locationID).text(this.locationDisplay);
            // Make sure to compare locationID and defaultValue as Strings since locationID may be an integer
            if (String(this.locationID) === String(defaultValue) ||
              (defaultValue === '' && this.isDefault && $emptyOption.length === 0) ||
              (response.data.locations.length === 1)) {
              option.attr('selected', 'selected');
            }
            $select.append(option);
          });
          if (response.data.locations.length === 1) {
            $emptyOption.attr('hidden', 'hidden');
            $emptyOption.removeAttr('selected');
          }
          else {
            $emptyOption.removeAttr('hidden');
          }
          $select.show();
          $('#pickUpLocationLabel').text($self.find(':selected').data('locations-label'));
        } else {
          $select.hide();
          $noResults.show();
        }
        $icon.addClass('hidden');
        $select.removeAttr('disabled');
      })
      .fail(function holdPickupLocationsFail(/*response*/) {
        $icon.addClass('hidden');
        $select.removeAttr('disabled');
      });
  }).trigger('change');
}

function setupHoldEditForm() {
  $('#frozen').on('change', function updateFrozen() {
    var $frozenThrough = $('#frozen_through');
    if ($(this).val() === '1') {
      $frozenThrough.removeAttr('disabled');
    } else {
      $frozenThrough.val('').attr('disabled', 'disabled');
    }
  }).trigger('change');
}
