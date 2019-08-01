/*global VuFind, finna */
finna.MapFacet = (function finnaStreetMap() {
  var geolocationAccuracyThreshold = 20; // If accuracy >= threshold then give a warning for the user
  var searchRadius = 0.1; // Radius of the search area in KM
  var progressContainer;

  function info(message, stopped) {
    if (typeof stopped !== 'undefined' && stopped) {
      progressContainer.find('.fa-spinner').addClass('hidden');
    }
    var div = $('<div class="info-message"></div>').text(message);
    progressContainer.find('.info').empty().append(div);
  }

  function locationSearch(position) {
    if (position.coords.accuracy >= geolocationAccuracyThreshold) {
      info(VuFind.translate('street_search_coordinates_found_accuracy_bad'));
    } else {
      info(VuFind.translate('street_search_coordinates_found'));
    }

    var queryParameters = {
      'filter': [
        '{!geofilt sfield=location_geo pt=' + position.coords.latitude + ',' + position.coords.longitude + ' d=' + searchRadius + '}'
      ]
    };
    var url = $(".user-location-filter").attr("href") + '&' + $.param(queryParameters);
    window.location.href = url;
  }

  function geoLocationError(error) {
    var errorString = 'street_search_geolocation_other_error';
    var additionalInfo = '';
    if (error) {
      switch (error.code) {
      case error.POSITION_UNAVAILABLE:
        errorString = 'street_search_geolocation_position_unavailable';
        break;
      case error.PERMISSION_DENIED:
        errorString = 'street_search_geolocation_inactive';
        break;
      case error.TIMEOUT:
        errorString = 'street_search_timeout';
        break;
      default:
        additionalInfo = error.message;
        break;
      }
    }
    errorString = VuFind.translate(errorString);
    if (additionalInfo) {
      errorString += ' -- ' + additionalInfo;
    }
    info(errorString, true);
  }

  function initMapModal(_options) {
    function closeModalCallback(modal) {
      modal.removeClass('location-service location-service-qrcode');
      modal.find('.modal-dialog').removeClass('modal-lg');
    }
    var modal = $('#modal');
    modal.one('hidden.bs.modal', function onHiddenModal() {
      closeModalCallback($(this));
    });
    modal.find('.modal-dialog').addClass('modal-lg');

    var mapCanvas = $('.modal-map');
    var mapData = finna.map.initMap(mapCanvas, true, _options);
    var drawnItems = mapData.drawnItems;

    $('#modal').on('shown.bs.modal', function onShownModal() {
      mapData.map.invalidateSize();
      var bounds = drawnItems.getBounds();
      var fitZoom = mapData.map.getBoundsZoom(bounds);
      mapData.map.fitBounds(bounds, fitZoom);
    });


    mapCanvas.closest('form').submit(function mapFormSubmit(e) {
      $('input[name="filter[]"]').each(function removeLastSearchLocationFilter() {
        if (this.value.includes("!geofilt sfield=location_geo")){
          this.remove();
        }
      });
      var geoFilters = '';
      drawnItems.eachLayer(function mapLayerToSearchFilter(layer) {
        var latlng = layer.getLatLng();
        var value = '{!geofilt sfield=location_geo pt=' + latlng.lat + ',' + latlng.lng + ' d=' + (layer.getRadius() / 1000) + '}';
        if (geoFilters) {
          geoFilters += ' OR ';
        }
        geoFilters += value;
      });

      if (geoFilters && (window.location.href.includes('/StreetSearch?go=1') || window.location.href.includes('streetsearch=1'))) {
        e.preventDefault();
        var queryParameters = {
          'type': 'AllFields',
          'limit': '100',
          'view': 'grid',
          'filter': [
            '~format:"0/Image/"',
            '~format:"0/Place/"',
            'free_online_boolean:"1"',
            geoFilters
          ],
          'streetsearch': '1'
        };
        var url = VuFind.path + '/Search/Results?' + $.param(queryParameters);

        window.location.href = url;
      }
      else if (geoFilters) {
        var field = $('<input type="hidden" name="filter[]"/>').val(geoFilters);
        mapCanvas.closest('form').append(field);
      }
    });
  }

  function initMapFacet(_options){
    progressContainer = $('.location-search-info');
    $(".user-location-filter").click(function onLocationFilterClick(e){
      e.preventDefault();
      progressContainer.find('.fa-spinner').removeClass('hidden');
      progressContainer.find('.info').empty();
      progressContainer.removeClass('hidden');
      navigator.geolocation.getCurrentPosition(locationSearch, geoLocationError, { timeout: 30000, maximumAge: 10000 });
    });

    $('.close-info').click(function onCloseInfoClick(){
      progressContainer.addClass('hidden');
    });

    finna.map.initMap($(".map"), false, _options);
  }

  var my = {
    initMapFacet: initMapFacet,
    initMapModal: initMapModal
  };

  return my;
})();
