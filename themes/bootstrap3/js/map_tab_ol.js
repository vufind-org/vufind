/*global ol */
/*exported loadMapTab */
//Coordinate order:  Storage and Query: WENS ; Display: WSEN
function loadMapTab(mapData, popupTitle) {
  var init = true;
  var pTitle = popupTitle + '<button class="close">&times;</button>';
  var srcProj = 'EPSG:4326';
  var dstProj = 'EPSG:900913';
  var osm = new ol.layer.Tile({source: new ol.source.OSM()});
  var vectorSource = new ol.source.Vector();
  var map;
  var iconStyle = new ol.style.Style({
    image: new ol.style.Circle({
      radius: 5,
      fill: new ol.style.Fill({
        color: 'red'
      })
    })
  });
  var polyStyle = new ol.style.Style({
    fill: new ol.style.Fill({
      color: [200, 0, 0, 0.1]
    }),
    stroke: new ol.style.Stroke({
      color: 'red',
      width: 2
    })
  });

  // close map popups if not on the Map tab
  $('.record-tabs .map').on('hide.bs.tab', function closePopups() {
    $('#popup').popover('destroy');
  });

  $('#map-canvas').show();
  init = function drawMap() {
    var featureCount = mapData.length;
    var label, label_on;
    var label_name;
    var label_coords, label_coord, label_coord1, label_coord2;
    var i = 0;
    for (i; i < featureCount; i++) {
      // Construct the label names
      label_name = mapData[i][5];
      //Construct the coordinate labels
      label_coords = mapData[i][6];
      if (label_coords) {
        label_coord1 = mapData[i][6].substring(0, 16);
        label_coord2 = mapData[i][6].substring(16);
        if (label_coord2) {
          label_coord = label_coord1 + '<br/>' + label_coord2;
        } else {
          label_coord = label_coord1;
        }
      }
      // Construct the entire label string
      if (label_coord && label_name) {
        label = label_coord + '<br/>' + label_name;
        label_on = true;
      } else if (label_name){
        label = label_name;
        label_on = true;
      } else if (label_coord) {
        label = label_coord;
        label_on = true;
      } else {
        label = 'No information available';
        label_on = false;
      }
      // Determine if entry is point or polygon - Does W=E & N=S? //
      if (mapData[i][4] === 2) {
      //It's a point feature //
        var lonlat = ol.proj.transform([mapData[i][0], mapData[i][1]], srcProj, dstProj);
        var iconFeature = new ol.Feature({
          geometry: new ol.geom.Point(lonlat),
          name: label
        });
        iconFeature.setStyle(iconStyle);
        vectorSource.addFeature(iconFeature);
      } else if (mapData[i][4] === 4) { // It's a polygon feature //
        var point1 = ol.proj.transform([mapData[i][0], mapData[i][3]], srcProj, dstProj);
        var point2 = ol.proj.transform([mapData[i][0], mapData[i][1]], srcProj, dstProj);
        var point3 = ol.proj.transform([mapData[i][2], mapData[i][1]], srcProj, dstProj);
        var point4 = ol.proj.transform([mapData[i][2], mapData[i][3]], srcProj, dstProj);
        var polyFeature = new ol.Feature({
          geometry: new ol.geom.Polygon([
            [point1, point2, point3, point4, point1]
          ]),
          name: label
        });
        polyFeature.setStyle(polyStyle);
        vectorSource.addFeature(polyFeature);
      }
    }
    var vectorLayer = new ol.layer.Vector({
      source: vectorSource,
      renderBuffer: 500
    });
    map = new ol.Map({
      renderer: 'canvas',
      projection: dstProj,
      layers: [osm, vectorLayer],
      target: 'map-canvas',
      view: new ol.View({
        center: [0, 0],
        zoom: 1
      })
    });

  // Adjust zoom extent and center of map
    if (featureCount === 1 && mapData[0][4] === 2) {
      map.getView().setZoom(4);
      var centerCoord = ol.proj.transform([mapData[0][0], mapData[0][1]], srcProj, dstProj);
      map.getView().setCenter(centerCoord);
    } else {
      var extent = vectorLayer.getSource().getExtent();
      map.getView().fit(extent, map.getSize());
    }

  // Turn on popup tool tips if labels or coordinates are enabled.
    if (label_on === true) {
      var element = document.getElementById('popup');
      var popup = new ol.Overlay({
        element: element,
        stopEvent: true
      });
      map.addOverlay(popup);

      // Display popup on click
      map.on('click', function displayPopup(evt) {
        var popupfeature = map.forEachFeatureAtPixel(evt.pixel,
          function showFeature(feature) {
            return feature;
          });
        if (popupfeature) {
          var coordinate = evt.coordinate;
          popup.setPosition(coordinate);
          $(element).popover({
            'placement': 'auto',
            'container': 'body',
            'animation': false,
            'html': true,
            'title': pTitle
          }).on('shown.bs.popover', function closePopup(e) {
            // 'aria-describedby' is the id of the current popover
            var current_popover = '#' + $(e.target).attr('aria-describedby');
            var $cur_pop = $(current_popover);
            $cur_pop.find('.close').click(function closeCurPop(){
              $(element).popover('destroy');
            });
          });
          $(element).data('bs.popover').options.content = popupfeature.get('name');
          $(element).popover('show');
        } else {
          $(element).popover('destroy');
        }
      });

      // change mouse cursor when over marker
      map.on('pointermove', function changeMouseCursor(e) {
        if (e.dragging) {
          $(element).popover('destroy');
          return;
        }
        var pixel = map.getEventPixel(e.originalEvent);
        var hit = map.hasFeatureAtPixel(pixel);
        var target = map.getTarget();
        if (hit === true) {
          document.getElementById(target).style.cursor = "pointer";
        } else {
          document.getElementById(target).style.cursor = "default";
        }
      });
    }
  };
  init();
  init = false;
}
