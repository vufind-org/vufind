/*global VuFind, finna */
finna.changeHolds = (function finnaChangeHolds() {
  function setupChangeHolds() {
    var errorOccured = $('<div></div>').attr('class', 'alert alert-danger hold-change-error').text(VuFind.translate('error_occurred'));

    function pickupSubmitHandler() {
      $().dropdown('toggle');
      var selected = $(this);
      var recordId = selected.data('recordId');
      var itemId = selected.data('itemId');
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
        id: recordId,
        itemId: itemId,
        requestId: requestId,
        pickupLocationId: locationId
      };
      $.ajax({
        data: params,
        dataType: 'json',
        cache: false,
        url: VuFind.path + '/AJAX/JSON'
      })
        .done(function onChangePickupLocationDone(response) {
          spinnerChange.addClass('hidden');
          if (response.data.success) {
            var success = $('<div></div>').attr('class', 'alert alert-success hold-change-success').text(VuFind.translate('change_hold_success'));
            hold.closest('.pickup-location-container').append(success);
          } else {
            hold.closest('.pickup-location-container').append(errorOccured);
          }
        })
        .fail(function onChangePickupLocationFail() {
          spinnerChange.addClass('hidden');
          hold.append(errorOccured);
        });
    }

    var changeHolds = $('.changeHolds');
    changeHolds.click(function onClickChangeHolds() {
      var hold = $(this);
      $('.hold-change-success').remove();
      $('.hold-change-error').remove();
      var pickupLocations = $(this).find('.pickup-locations');
      if (!pickupLocations.data('populated')) {
        pickupLocations.data('populated', 1);
        var spinnerLoad = $(this).find('.pickup-location-load-indicator');
        spinnerLoad.removeClass('hidden');
        var recordId = $(this).data('recordId');
        var itemId = $(this).data('itemId');
        var requestId = $(this).data('requestId');
        var params = {
          method: 'getRequestGroupPickupLocations',
          id: recordId,
          itemId: itemId,
          requestGroupId: '0',
          requestId: requestId
        };
        $.ajax({
          data: params,
          dataType: 'json',
          cache: false,
          url: VuFind.path + '/AJAX/JSON'
        })
          .done(function onPickupLocationsDone(response) {
            $.each(response.data.locations, function addPickupLocation() {
              var item = $('<li class="pickupLocationItem" role="menuitem"></li>')
                .data('locationId', this.locationID)
                .data('locationDisplay', this.locationDisplay)
                .data('recordId', recordId)
                .data('itemId', itemId)
                .data('requestId', requestId)
                .data('hold', hold)
                .click(pickupSubmitHandler);
              var text = $('<a></a>').text(this.locationDisplay);
              item.append(text);
              pickupLocations.append(item);
            });
            spinnerLoad.addClass('hidden');
          })
          .fail(function onPickupLocationsDone() {
            spinnerLoad.addClass('hidden');
            changeHolds.append(errorOccured);
            pickupLocations.data('populated', 0);
          });
      }
    });

    function changeHoldStatus(container, requestId, recordId, itemId, frozen) {
      var spinnerChange = container.find('.status-change-load-indicator');

      $('.hold-change-success').remove();
      $('.hold-change-error').remove();
      spinnerChange.removeClass('hidden');

      var params = {
        method: 'changeRequestStatus',
        requestId: requestId,
        id: recordId,
        itemId: itemId,
        frozen: frozen
      };
      $.ajax({
        data: params,
        dataType: 'json',
        cache: false,
        url: VuFind.path + '/AJAX/JSON'
      })
        .done(function onChangeRequestStatusDone(response) {
          spinnerChange.addClass('hidden');
          if (response.data.success) {
            if (frozen) {
              container.find('.hold-status-active').addClass('hidden');
              container.find('.hold-status-frozen').removeClass('hidden');
            } else {
              container.find('.hold-status-active').removeClass('hidden');
              container.find('.hold-status-frozen').addClass('hidden');
            }
          } else {
            container.append(errorOccured);
          }
        })
        .fail(function onChangeRequestStatusFail() {
          spinnerChange.addClass('hidden');
          container.append(errorOccured);
        });
    }

    $('.hold-status-freeze').click(function onClickHoldFreeze() {
      var container = $(this).closest('.change-hold-status');
      var requestId = container.data('requestId');
      var recordId = container.data('recordId');
      var itemId = container.data('itemId');
      changeHoldStatus(container, requestId, recordId, itemId, 1);
      return false;
    });

    $('.hold-status-release').click(function onClickHoldRelease() {
      var container = $(this).closest('.change-hold-status');
      var requestId = container.data('requestId');
      var recordId = container.data('recordId');
      var itemId = container.data('itemId');
      changeHoldStatus(container, requestId, recordId, itemId, 0);
      return false;
    });
  }

  var my = {
    init: function init() {
      setupChangeHolds();
    }
  };

  return my;

})();