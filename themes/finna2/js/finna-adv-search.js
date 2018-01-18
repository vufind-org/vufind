/* global VuFind, finna, L */
finna.advSearch = (function advSearch() {

  function initForm() {
    var form = $('.template-dir-search #advSearchForm');
    var container = form.find('.ranges-container .slider-container').closest('fieldset');
    var field = container.find('input[name="daterange[]"]').eq(0).val();
    var fromField = container.find('#' + field + 'from');
    var toField = container.find('#' + field + 'to');
    form.submit(function formSubmit(event) {
      if (typeof form[0].checkValidity == 'function') {
        // This is for Safari, which doesn't validate forms on submit
        if (!form[0].checkValidity()) {
          event.preventDefault();
          return;
        }
      } else {
        // JS validation for browsers that don't support form validation
        fromField.removeClass('invalid');
        toField.removeClass('invalid');
        if (fromField.val() && toField.val() && parseInt(fromField.val(), 10) > parseInt(toField.val(), 10)) {
          fromField.addClass('invalid');
          toField.addClass('invalid');
          event.preventDefault();
          return;
        }
      }
      // Convert date range from/to fields into a "[from TO to]" query
      container.find('input[type="hidden"]').attr('disabled', 'disabled');
      var from = fromField.val() || '*';
      var to = toField.val() || '*';
      if (from !== '*' || to !== '*') {
        var filter = field + ':"[' + from + " TO " + to + ']"';

        $('<input>')
          .attr('type', 'hidden')
          .attr('name', 'filter[]')
          .attr('value', filter)
          .appendTo($(this));
      }
    });

    fromField.change(function fromFieldChange() {
      toField.attr('min', fromField.val());
    });
    toField.change(function toFieldChange() {
      fromField.attr('max', toField.val());
    });
  }

  /**
   * Initialize advanced search map
   *
   * @param options Array of options:
   *   tileLayer     L.tileLayer Tile layer
   *   center        L.LatLng    Map center point
   *   zoom          int         Initial zoom level
   *   items         array       Items to draw on the map
   */
  function initMap(_options) {
    var mapCanvas = $('.selection-map-canvas');
    if (mapCanvas.length === 0) {
      return;
    }
    L.drawLocal.draw.handlers.circle.tooltip.start = '';
    L.drawLocal.draw.handlers.simpleshape.tooltip.end = '';
    L.drawLocal.draw.handlers.circle.radius = VuFind.translate('radiusPrefix');

    var defaults = {
      tileLayer: L.tileLayer('//map-api.finna.fi/v1/rendered/{z}/{x}/{y}.png', {
        maxZoom: 18,
        tileSize: 256,
        attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>'
      }),
      center: new L.LatLng(64.8, 26),
      zoom: 5,
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
    drawnItems.eachLayer(function disableEditing(layer) {
      layer.editing.enable();
    });

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
        map.fitBounds(bounds, {maxZoom: 11});
        options.tileLayer.off('load', onLoad);
      };
      options.tileLayer.on('load', onLoad);
    }

    var FinnaMapButton = L.Control.extend({
      options: {
        position: 'bottomleft'
      },
      createButton: function createButton(cssClass, html, clickHandler/*, style*/) {
        var container = L.DomUtil.create('div', 'map-button ' + cssClass + ' btn btn-primary leaflet-bar leaflet-control leaflet-control-custom');
        $(container).html(html).click(clickHandler);
        return container;
      }
    });

    var DeleteButton = FinnaMapButton.extend({
      onAdd: function mapOnDelete(/*mapTarget*/) {
        var htmlElem = $('<div><i class="fa fa-times"></i>');
        $('<span/>').text(' ' + VuFind.translate('clearCaption')).appendTo(htmlElem);
        return this.createButton('map-button-clear', htmlElem.html(), function mapClearLayersClick() {
          drawnItems.clearLayers();
        });
      }
    });
    map.addControl(new DeleteButton());

    var CircleButton = FinnaMapButton.extend({
      onAdd: function mapOnAddCircle(mapTarget) {
        var htmlElem = $('<div><i class="fa fa-crosshairs"></i>');
        $('<span/>').text(' ' + VuFind.translate('circleCaption')).appendTo(htmlElem);
        var button = this.createButton('map-button-circle', htmlElem.html(), function mapCircleButtonClick() {
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

    mapCanvas.closest('form').submit(function mapFormSubmit() {
      var filters = '';
      drawnItems.eachLayer(function mapLayerToSearchFilter(layer) {
        var latlng = layer.getLatLng();
        var value = '{!geofilt sfield=location_geo pt=' + latlng.lat + ',' + latlng.lng + ' d=' + (layer.getRadius() / 1000) + '}';
        if (filters) {
          filters += ' OR ';
        }
        filters += value;
      });
      if (filters) {
        var field = $('<input type="hidden" name="filter[]"/>').val(filters);
        mapCanvas.closest('form').append(field);
      }
    });
  }

  function addRemoveButton(layer, featureGroup) {
    var button = $('<a/>')
      .html('<i class="fa fa-times" aria-hidden="true"></i>')
      .click(function mapOnRemoveButtonClick(/*e*/) {
        featureGroup.removeLayer(layer);
      });
    $('<span/>').text(VuFind.translate('removeCaption')).appendTo(button);
    layer.bindPopup(button.get(0), {closeButton: false});
  }

  var my = {
    init: function init() {
      initForm();
    },
    initMap: initMap
  };

  return my;

})();
