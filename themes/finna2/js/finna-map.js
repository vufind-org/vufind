/*global VuFind, finna, L */
finna.map = (function finnaMap() {

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

  function initMapZooming(map) {
    // Add zoom control with translated tooltips
    L.control.zoom({
      position: 'topleft',
      zoomInTitle: VuFind.translate('map_zoom_in'),
      zoomOutTitle: VuFind.translate('map_zoom_out')
    }).addTo(map);

    $('.leaflet-control-zoom').children('a').each(function removeFocus() {
      $(this).attr('tabindex', -1);
    });

    // Enable mouseWheel zoom on click
    map.once('focus', function onFocusMap() {
      map.scrollWheelZoom.enable();
    });
    map.scrollWheelZoom.disable();
  }

  function initMap($mapContainer, editable, _options) {
    var mapCanvas = $mapContainer;
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
        if (editable) {
          addRemoveButton(circle, drawnItems);
        }
        drawnItems.addLayer(circle);
      }
    });
    if (options.items.length <= 0 ) {
      options.zoom = 5;
    }

    var map = new L.Map(mapCanvas.get(0), {
      attributionControl: false,
      layers: [options.tileLayer, drawnItems],
      center: options.center,
      zoom: options.zoom,
      zoomControl: false
    });

    mapCanvas.attr('tabindex', -1);
    initMapZooming(map);

    if (options.items.length > 0) {
      var onLoad = function tileLayerOnLoad() {
        var bounds = drawnItems.getBounds();
        var fitZoom = map.getBoundsZoom(bounds);
        map.fitBounds(bounds, fitZoom);
        options.tileLayer.off('load', onLoad);
        if (editable) {
          drawnItems.eachLayer(function disableEditing(layer) {
            layer.editing.enable();
          });
        }
      };
      options.tileLayer.on('load', onLoad);
    }

    if (editable) {
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
    }
    return {
      map: map,
      drawnItems: drawnItems
    };
  }

  var my = {
    initMap: initMap,
    initMapZooming: initMapZooming
  };

  return my;
})();
