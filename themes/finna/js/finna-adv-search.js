/* global VuFind */
finna.advSearch = (function() {

    var initForm = function() {
        var form = $('.main.template-dir-search #advSearchForm');
        var container = form.find('.ranges-container .slider-container').closest('.row');
        var field = container.find('input[name="daterange[]"]').eq(0).val();
        var fromField = container.find('#' + field + 'from');
        var toField = container.find('#' + field + 'to');
        form.submit(function(event) {
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
            if (from != '*' || to != '*') {
                var filter = field + ':"[' + from + " TO " + to + ']"';

                $("<input>")
                    .attr("type", "hidden")
                    .attr("name", "filter[]")
                    .attr("value", filter)
                    .appendTo($(this));
            }
        });

        fromField.change(function() {
            toField.attr('min', fromField.val());
        });
        toField.change(function() {
            fromField.attr('max', toField.val());
        });
    };
    
    /**
     * Initialize advanced search map
     * 
     * @param options Array of options:
     *   tileLayer     L.tileLayer Tile layer
     *   center        L.LatLng    Map center point
     *   zoom          int         Initial zoom level
     */
    var initMap = function(options) {
      var mapCanvas = $('.selection-map-canvas');
      if (mapCanvas.length == 0) {
        return;
      }
      L.drawLocal.draw.handlers.circle.tooltip.start = '';
      L.drawLocal.draw.handlers.simpleshape.tooltip.end = '';
      L.drawLocal.draw.handlers.circle.radius = VuFind.translate('radiusPrefix');

      var defaults = {
        tileLayer: L.tileLayer('//api.digitransit.fi/map/v1/{id}/{z}/{x}/{y}.png', {
          id: 'hsl-map',
          maxZoom: 18,
          tileSize: 512,
          zoomOffset: -1,
          // bounds: L.latLngBounds(L.latLng(60, 19.5), L.latLng(70, 30)),
          attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>'
        }),
        center: new L.LatLng(64.8, 26),
        zoom: 5
      };
      options = $.extend(defaults, options);
      

      var drawnItems = new L.FeatureGroup();
      map = new L.Map(mapCanvas.get(0), {
        layers: [options.tileLayer, drawnItems],
        center: options.center,
        zoom: options.zoom
      });
      
      FinnaMapButton = L.Control.extend({
        options: {
          position: 'bottomright'
        },
        createButton: function(cssClass, html, clickHandler, style) {
          var container = L.DomUtil.create('div', 'map-button ' + cssClass + ' btn btn-primary leaflet-bar leaflet-control leaflet-control-custom');
          $(container).html(html).click(clickHandler);
          return container;
       }
      });

      DeleteButton = FinnaMapButton.extend({
       onAdd: function (map) {
         var htmlElem = $('<div><i class="fa fa-times"></i>');
         $('<span/>').text(' ' + VuFind.translate('clearCaption')).appendTo(htmlElem);
         return this.createButton('map-button-clear', htmlElem.html(), function() {
           drawnItems.clearLayers();
         });
        }
      });
      map.addControl(new DeleteButton());

      CircleButton = FinnaMapButton.extend({
        onAdd: function (map) {
         var htmlElem = $('<div><i class="fa fa-crosshairs"></i>');
         $('<span/>').text(' ' + VuFind.translate('circleCaption')).appendTo(htmlElem);
          var button = this.createButton('map-button-circle', htmlElem.html(), function() {
           $('.map-button-circle').addClass('active');  
            new L.Draw.Circle(map, {}).on('disabled', function() {
              $('.map-button-circle').removeClass('active');  
            }).enable();
          });
          $(button).css('top', '-10px');
          return button;
        }
      });
      map.addControl(new CircleButton());

      map.on('draw:created', function(e) {
        var type = e.layerType,
        layer = e.layer;
        var button = $('<a/>')
          .html('<i class="fa fa-times" aria-hidden="true"></i>')
          .click(function(e) {
            drawnItems.removeLayer(layer);
          });
        $('<span/>').text(VuFind.translate('removeCaption')).appendTo(button);
        layer.bindPopup(button.get(0), {closeButton: false});
        drawnItems.addLayer(layer);
      });
      
      map.on('popupopen', function(e) {
        e.popup._source.setStyle({opacity: 0.8, fillOpacity: 0.5});
      });
      map.on('popupclose', function(e) {
        e.popup._source.setStyle({opacity: 0.5, fillOpacity: 0.2});
      });
      
      mapCanvas.closest('form').submit(function() {
        var filters = '';
        drawnItems.eachLayer(function(layer) {
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
    };

    var my = {
        init: function() {
            initForm();
            initMap();
        }
    };

    return my;

})(finna);
