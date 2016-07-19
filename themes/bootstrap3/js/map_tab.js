/*global ol */
/*exported loadMapTab */
//Coordinate order:  Storage and Query: WENS ; Display: WSEN
function loadMapTab(mapData) {
  var init = true;
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
      color: [200, 0, 0, .1]
    }),
    stroke: new ol.style.Stroke({
      color: 'red',
      width: 2 
    })
  });

  $('#map-canvas').show();
  var init = function drawMap() {
    var featureCount = mapData.length;
    var label, label_on;
    var label_name;
    var label_coord, label_coord1, label_coord2;
    var i = 0;
    for (i; i < featureCount; i++) {
      //Construct the coordinate labels
      if ((mapData[i][5] == 'cl') || (mapData[i][5] == 'c')) { 
        label_coord1 = mapData[i][6].substring(0, 16); 
        label_coord2 = mapData[i][6].substring(16); 
        if (label_coord2) {
          label_coord = label_coord1 + '<br/>' + label_coord2;
        } else {
          label_coord = label_coord1;
        }
      }
      // Construct the label names
      if (mapData[i][5] == 'l') {
        label_name = mapData[i][6];
      }
      if (mapData[i][5] == 'cl') {
        label_name = mapData[i][7];
      }
      // Construct the entire label string
      if (mapData[i][5] == 'cl') {
        label = label_coord + '<br/>' + label_name;
        label_on = true;
      }
      if (mapData[i][5] == 'c') {
        label = label_coord;
        label_on = true;
      }
      if (mapData[i][5] == 'l') {
        label = label_name;
        label_on = true;
      }
      if (mapData[i][5] == 'n') {
        label = '';
        label_on = false;
      }

      // Determine if entry is point or polygon - Does W=E & N=S? //
      if (mapData[i][4] == 2) {
      //It's a point feature //
        var lonlat = ol.proj.transform([mapData[i][0], mapData[i][1]], srcProj, dstProj);
        var iconFeature = new ol.Feature({
          geometry: new ol.geom.Point(lonlat),
          name: label
        });
        iconFeature.setStyle(iconStyle);
        vectorSource.addFeature(iconFeature);
      } else if (mapData[i][4] == 4) { // It's a polygon feature //
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
    var extent = vectorLayer.getSource().getExtent();
    map.getView().fit(extent, map.getSize());

  // Turn on popup tool tips if labels or coordinates are enabled.
    if (label_on == true) {
      var element = document.getElementById('popup');
      var popup = new ol.Overlay({
        element: element
      });
      map.addOverlay(popup);

      // display popup on click
      map.on('click', function displayPopup(evt) {
        feature = map.forEachFeatureAtPixel(evt.pixel,
          function showFeature(feature) {
            return feature;
          });
        if (feature) {
          element = popup.getElement();
          var coordinate = evt.coordinate;
          $(element).popover('destroy');
          popup.setPosition(coordinate);
          $(element).popover({
            'placement': 'top',
            'animation': false,
            'html': true,
            'content': feature.get('name')
          });
          $(element).popover('show');
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
        if (hit == true) {
          document.getElementById(target).style.cursor = "pointer";
        } else {
          document.getElementById(target).style.cursor = "default";
        }
      });
    }
  }
  init();
  init = false;
}
