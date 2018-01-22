/*global VuFind, finna, L */
finna.MapFacet = (function finnaStreetMap() {
  var geolocationAccuracyThreshold = 20; // If accuracy >= threshold then give a warning for the user
  var searchRadius = 0.1; // Radius of the search area in KM
  var progressContainer;

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

    var mapCanvas = $(".map");
    if (mapCanvas.length === 0) {
      return;
    }

    L.drawLocal.draw.handlers.circle.tooltip.start = '';
    L.drawLocal.draw.handlers.simpleshape.tooltip.end = '';
    L.drawLocal.draw.handlers.circle.radius = VuFind.translate('radiusPrefix');

    var defaults = {
      tileLayer: L.tileLayer('//map-api.finna.fi/v1/rendered/{z}/{x}/{y}.png', {
        zoom: 10,
        tileSize: 256,
        attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>'
      }),
      center: new L.LatLng(64.8, 26),
      zoom: 8,
      items: []
    };
    var options = $.extend(defaults, _options);

    var drawnItems = new L.FeatureGroup();
    $.each(options.items, function drawItem(idx, item) {
      var matches = item.match(/pt=([\d.]+),([\d.]+) d=([\d.]+)/);
      if (matches) {
        var circle = new L.Circle([matches[1], matches[2]], matches[3] * 1000);
        drawnItems.addLayer(circle);
      }
    });

    var map = new L.Map(mapCanvas.get(0), {
      attributionControl: false,
      layers: [options.tileLayer, drawnItems],
      center: options.center,
      zoom: options.zoom,
      zoomControl: false
    });

    finna.layout.initMap(map);

    if (options.items.length > 0) {
      var onLoad = function tileLayerOnLoad() {
        var bounds = drawnItems.getBounds();
        var fitZoom = map.getBoundsZoom(bounds);
        map.fitBounds(bounds, fitZoom);
        options.tileLayer.off('load', onLoad);
      };
      options.tileLayer.on('load', onLoad);
    }
    return map;
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

  function info(message, stopped){
    if (typeof stopped !== 'undefined' && stopped) {
      progressContainer.find('.fa-spinner').addClass('hidden');
    }
    var div = $('<div class="info-message"></div>').text(message);
    progressContainer.find('.info').empty().append(div);
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

    $('#modal').on('shown.bs.modal', function onShownModal() {
      map.invalidateSize();
      var bounds = drawnItems.getBounds();
      var fitZoom = map.getBoundsZoom(bounds);
      map.fitBounds(bounds, fitZoom);
    });

    var mapCanvas = $('.modal-map');
    if (mapCanvas.length === 0) {
      return;
    }
    L.drawLocal.draw.handlers.circle.tooltip.start = '';
    L.drawLocal.draw.handlers.simpleshape.tooltip.end = '';
    L.drawLocal.draw.handlers.circle.radius = VuFind.translate('radiusPrefix');

    var defaults = {
      tileLayer: L.tileLayer('//map-api.finna.fi/v1/rendered/{z}/{x}/{y}.png', {
        tileSize: 256,
        attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>'
      }),
      center: new L.LatLng(64.8, 26),
      zoom: 8,
      items: []
    };
    var options = $.extend(defaults, _options);

    var drawnItems = new L.FeatureGroup();
    $.each(options.items, function drawItem(idx, item) {
      var matches = item.match(/pt=([\d.]+),([\d.]+) d=([\d.]+)/);
      if (matches) {
        var circle = new L.Circle([matches[1], matches[2]], matches[3] * 1000);
        addRemoveButton(circle, drawnItems);
        drawnItems.addLayer(circle);
      }
    });
    if (options.items.length <= 0 ) {
      options.zoom = 5;
    }

    var map = new L.Map(mapCanvas.get(0), {
      layers: [options.tileLayer, drawnItems],
      center: options.center,
      zoom: options.zoom,
      zoomControl: false
    });

    finna.layout.initMap(map);

    if (options.items.length > 0) {
      var onLoad = function tileLayerOnLoad() {
        var bounds = drawnItems.getBounds();
        var fitZoom = map.getBoundsZoom(bounds);
        map.fitBounds(bounds, fitZoom);
        options.tileLayer.off('load', onLoad);
        drawnItems.eachLayer(function disableEditing(layer) {
          layer.editing.enable();
        });
      };
      options.tileLayer.on('load', onLoad);
    }

    var FinnaMapButton = L.Control.extend({
      options: {
        position: 'bottomleft'
      },
      createButton: function createButton(cssClass, html, clickHandler/*, style*/) {
        var container = L.DomUtil.create('div', 'map-button btn ' + cssClass + ' leaflet-bar leaflet-control leaflet-control-custom');
        $(container).html(html).click(clickHandler);
        return container;
      }
    });

    var DeleteButton = FinnaMapButton.extend({
      onAdd: function mapOnDelete(/*mapTarget*/) {
        var htmlElem = $('<div><i class="fa fa-times"></i>');
        $('<span/>').text(' ' + VuFind.translate('clearCaption')).appendTo(htmlElem);
        return this.createButton('map-button-clear', htmlElem.html(), function mapClearLayersClick() {
          drawnItems.eachLayer(function disableEditing(layer) {
            layer.editing.disable();
          });
          drawnItems.clearLayers();
        });
      }
    });
    map.addControl(new DeleteButton());

    var CircleButton = FinnaMapButton.extend({
      onAdd: function mapOnAddCircle(mapTarget) {
        var htmlElem = $('<div><i class="fa fa-crosshairs"></i>');
        $('<span/>').text(' ' + VuFind.translate('circleCaption')).appendTo(htmlElem);
        var button = this.createButton('map-button-circle btn-primary', htmlElem.html(), function mapCircleButtonClick() {
          $('.map-button-circle').addClass('active');
          new L.Draw.Circle(mapTarget, {}).on('disabled', function mapOnCircleDisabled() {
            $('.map-button-circle').removeClass('active');
          }).enable();
        });
        return button;
      }
    });
    map.addControl(new CircleButton());

    map.on('draw:created', function mapOnCreated(e) {
      var layer = e.layer;
      layer.editing.enable();
      addRemoveButton(layer, drawnItems);
      drawnItems.addLayer(layer);
    });

    map.on('popupopen', function mapOnPopupOpen(e) {
      e.popup._source.setStyle({opacity: 0.8, fillOpacity: 0.5});
    });
    map.on('popupclose', function mapOnPopupClose(e) {
      e.popup._source.setStyle({opacity: 0.5, fillOpacity: 0.2});
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
            'online_boolean:"1"',
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

  function addRemoveButton(layer, featureGroup) {
    var button = $('<a/>')
        .html('<i class="fa fa-times" aria-hidden="true"></i>')
        .click(function mapOnRemoveButtonClick(/*e*/) {
          layer.editing.disable();
          featureGroup.removeLayer(layer);
        });
    $('<span/>').text(VuFind.translate('removeCaption')).appendTo(button);
    layer.bindPopup(button.get(0), {closeButton: false});
  }

  var my = {
    initMapFacet: initMapFacet,
    initMapModal: initMapModal
  };

  return my;
})();
