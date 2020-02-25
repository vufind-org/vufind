/*global VuFind, finna, L */
finna.organisationMap = (function finnaOrganisationMap() {
  var zoomLevel = {initial: 27, far: 5, close: 14};
  var holder = null;
  var mapTileUrl = null;
  var attribution = null;
  var map = null;
  var mapMarkers = {};
  var markers = [];
  var selectedMarker = null;

  function reset() {
    var group = new L.featureGroup(markers);
    var bounds = group.getBounds().pad(0.2);
    // Fit markers to screen
    map.fitBounds(bounds, {zoom: {animate: true}});
    map.closePopup();
    selectedMarker = null;
  }

  function draw(organisationList/*, id*/) {
    var me = $(this);
    var organisations = organisationList;

    var layer = L.tileLayer(mapTileUrl, {
      attribution: attribution,
      tileSize: 256
    });

    map = L.map($(holder).attr('id'), {
      zoomControl: false,
      layers: layer,
      minZoom: zoomLevel.far,
      maxZoom: 18,
      zoomDelta: 0.1,
      zoomSnap: 0.1,
      closePopupOnClick: false
    });

    finna.map.initMapZooming(map);

    // Center popup
    map.on('popupopen', function onPopupOpen(e) {
      map.setZoom(zoomLevel.close, {animate: false});

      var px = map.project(e.popup._latlng);
      px.y -= e.popup._container.clientHeight / 2;
      map.panTo(map.unproject(px), {animate: false});
    });

    map.on('popupclose', function onPopupClose(/*e*/) {
      selectedMarker = null;
    });

    L.control.locate({strings: {title: VuFind.translate('map_my_location')}}).addTo(map);
    $('.leaflet-control-locate a').attr('aria-label', VuFind.translate('map_my_location'));

    var icons = {};
    $(['open', 'closed', 'no-schedule']).each(function addIcon(ind, obj) {
      icons[obj] = L.divIcon({
        className: 'mapMarker',
        iconSize: null,
        html: '<div class="leaflet-marker-icon leaflet-zoom-animated leaflet-interactive"><i class="fa fa-map-marker ' + obj + '" style="position: relative; font-size: 35px;"></i></div>',
        iconAnchor: [10, 35],
        popupAnchor: [0, -36],
        labelAnchor: [-5, -86]
      });
    });

    // Map points
    $.each(organisations, function mapOrganisation(ind, obj) {
      if (obj.address != null && obj.address.coordinates !== null) {
        if (typeof obj.address.coordinates === 'undefined') {
          return true;
        }
        var infoWindowContent = obj.map.info;
        var point = obj.address.coordinates;

        var icon = icons['no-schedule'];
        var openTimes = finna.common.getField(obj, 'openTimes');
        if (openTimes) {
          var schedules = finna.common.getField(openTimes, 'schedules');
          var openNow = finna.common.getField(openTimes, 'openNow');
          icon = schedules && schedules.length > 0 ? (openNow ? icons.open : icons.closed) : icon;
        }

        var marker = L.marker(
          [point.lat, point.lon],
          {icon: icon}
        ).addTo(map);
        marker.on('mouseover', function onMouseOverMarker(ev) {
          if (marker === selectedMarker) {
            return;
          }
          var holderOffset = $(holder).offset();
          var offset = $(ev.originalEvent.target).offset();
          var x = offset.left - holderOffset.left;
          var y = offset.top - holderOffset.top;

          me.trigger('marker-mouseover', {id: obj.id, x: x, y: y});
        });

        marker.on('mouseout', function onMouseOutMarker(/*ev*/) {
          me.trigger('marker-mouseout');
        });

        marker.on('click', function onClickMarker(/*ev*/) {
          me.trigger('marker-click', obj.id);
        });

        marker
          .bindPopup(infoWindowContent, {zoomAnimation: true, autoPan: false})
          .addTo(map);

        mapMarkers[obj.id] = marker;
        markers.push(marker);
      }
    });

    reset();
  }

  function resize() {
    map.invalidateSize(true);
  }

  function hideMarker() {
    if (selectedMarker) {
      selectedMarker.closePopup();
    }
  }

  function selectMarker(id) {
    var marker = null;
    if (id in mapMarkers) {
      marker = mapMarkers[id];
    }
    if (selectedMarker) {
      if (selectedMarker === marker) {
        return;
      } else if (!marker) {
        hideMarker();
        return;
      }
    }

    marker.openPopup();
    selectedMarker = marker;
  }

  function init(_holder, _mapTileUrl, _attribution) {
    holder = _holder;
    mapTileUrl = _mapTileUrl;
    attribution = _attribution;
  }

  var my = {
    hideMarker: hideMarker,
    reset: reset,
    resize: resize,
    selectMarker: selectMarker,
    init: init,
    draw: draw
  };

  return my;
})();
