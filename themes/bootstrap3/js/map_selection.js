/*global ol */
/*exported loadMapSelection */
//Coordinate order:  Storage and Query: WENS ; Display: WSEN

function loadMapSelection(geoField, boundingBox, baseURL, homeURL, searchParams, showSelection, resultsCoords, popupTitle) {
  var init = true;
  var pTitle = popupTitle + '<button class="close">&times;</button>';
  var srcProj = 'EPSG:4326';
  var dstProj = 'EPSG:900913';
  var osm = new ol.layer.Tile({source: new ol.source.OSM()});
  var searchboxSource = new ol.source.Vector();
  var searchboxStyle = new ol.style.Style({
    fill: new ol.style.Fill({
      color: [255, 0, 0, 0.1]
    }),
    stroke: new ol.style.Stroke({
      color: [255, 0, 0, 1],
      width: 2
    })
  });
  var searchboxLayer = new ol.layer.Vector({
    source: searchboxSource,
    style: searchboxStyle
  });
  var draw, map;
  var count = resultsCoords.length;
  var searchResults = new Array(count);
  var searchIds = new Array(count);
  for (var i = 0; i < count; ++i) {
    var coordinates = ol.proj.transform(
        [resultsCoords[i][1], resultsCoords[i][2]], srcProj, dstProj
    );
    searchResults[i] = new ol.Feature({
      geometry: new ol.geom.Point(coordinates),
      id: resultsCoords[i][0],
      name: resultsCoords[i][3]
    });
    searchIds[i] = resultsCoords[i][0];
  }
  var resultSource = new ol.source.Vector({
    features: searchResults
  });
  var clusterSource = new ol.source.Cluster({
    distance: 60,
    source: resultSource
  });
  var styleCache = {};
  var clusterLayer = new ol.layer.Vector({
    id: 'clusterLayer',
    source: clusterSource,
    style: function addClusterStyle(feature) {
      var size = feature.get('features').length;
      var pointRadius = 8 + (size.toString().length * 2);
      var style = styleCache[size];
      if (!style) {
        style = [
          new ol.style.Style({
            image: new ol.style.Circle({
              radius: pointRadius,
              stroke: new ol.style.Stroke({
                color: '#ff0000'
              }),
              fill: new ol.style.Fill({
                color: '#ffb3b3'
              })
            }),
            text: new ol.style.Text({
              text: size.toString(),
              font: 'bold 12px arial,sans-serif',
              fill: new ol.style.Fill({
                color: 'black'
              })
            })
          })
        ];
        styleCache[size] = style;
      }
      map.removeInteraction(draw);
      return style;
    }
  });

  $('#geo_search').show();
  init = function drawMap() {
    map = new ol.Map({
      interactions: ol.interaction.defaults({
        shiftDragZoom: false
      }),
      target: 'geo_search_map',
      projection: dstProj,
      layers: [osm, searchboxLayer, clusterLayer],
      view: new ol.View({
        center: [0, 0],
        zoom: 1
      })
    });

    if (showSelection === true) {
      searchboxSource.clear();
      // Adjust bounding box (WSEN) display for queries crossing the dateline
      if (boundingBox[0] > boundingBox[2]) {
        boundingBox[2] = boundingBox[2] + 360;
      }
      var newBbox = new ol.geom.Polygon([[
        ol.proj.transform([boundingBox[0], boundingBox[3]], srcProj, dstProj),
        ol.proj.transform([boundingBox[0], boundingBox[1]], srcProj, dstProj),
        ol.proj.transform([boundingBox[2], boundingBox[1]], srcProj, dstProj),
        ol.proj.transform([boundingBox[2], boundingBox[3]], srcProj, dstProj),
        ol.proj.transform([boundingBox[0], boundingBox[3]], srcProj, dstProj)
      ]]);
      var featureBbox = new ol.Feature({
        name: "bbox",
        geometry: newBbox
      });
      searchboxSource.addFeature(featureBbox);
      map.getView().fit(searchboxSource.getExtent(), map.getSize());
    }

    //Get popup elements from webpage
    var element = document.getElementById('popup');

    // Add popup element to map
    var popup = new ol.Overlay({
      element: element,
      stopEvent: true
    });
    map.addOverlay(popup);

    // Display popup on click
    map.on('click', function displayPopup(evt) {
      var popupfeature = map.forEachFeatureAtPixel(evt.pixel,
        function showFeature(feature) {
          return {'feature': feature, 'layer': clusterLayer};
        });
      if (popupfeature) {
        var cFeatures = popupfeature.feature.get('features');
        var fType = typeof cFeatures;
        var fLayerId = popupfeature.layer.get('id');
        if ((fLayerId === 'clusterLayer') && (fType === 'object') && (cFeatures.length < 5)) {
          var coordinate = map.getCoordinateFromPixel(evt.pixel);
          var pcontent = '';
          for (var j = 0; j < cFeatures.length; j++) {
            var cFeatureName = cFeatures[j].get('name');
            var cFeatureId = cFeatures[j].get('id');
            var cFeatureContent = '<article class="geoItem">' +
              cFeatureName.link(homeURL + 'Record/' + cFeatureId) + '</article>';
            pcontent += cFeatureContent;
          }
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
              $(element).popover('hide');
            });
          });
          $(element).data('bs.popover').options.content = pcontent;
          $(element).popover('show');
        }
      } else {
        $(element).popover('destroy');
      }
    });

    // change mouse cursor when over marker
    map.on('pointermove', function changeMouseCursor(evt) {
      if (evt.dragging) {
        $(element).popover('destroy');
        return;
      }
      var pixel = map.getEventPixel(evt.originalEvent);
      var hit = map.hasFeatureAtPixel(pixel);
      if (hit) {
        var fl = map.forEachFeatureAtPixel(pixel, function getFeature(feature) {
          return {'feature': feature, 'layer': clusterLayer};
        });
        var cFeatures = fl.feature.get('features');
        var fType = typeof cFeatures;
        var fLayerId = fl.layer.get('id');
        if ((fLayerId === 'clusterLayer') && (fType === 'object') && (cFeatures.length < 5)) {
          map.getTargetElement().style.cursor = 'pointer';
        } else {
          map.getTargetElement().style.cursor = 'default';
        }
      }
    });
    // close popup if zoom in / out occurs
    map.getView().on('change:resolution', function closePopupsOnZoom() {
      $(element).popover('destroy');
    });
  };
  function addInteraction() {
    draw = new ol.interaction.Draw ({
      source: searchboxSource,
      type: 'Box',
      geometryFunction: function rectangleFunction(coords, geometryParam) {
        var geometry = geometryParam ? geometryParam : new ol.geom.Polygon(null);
        var start = coords[0];
        var end = coords[1];
        geometry.setCoordinates([
          [start, [start[0], end[1]], end, [end[0], start[1]], start]
        ]);
        return geometry;
      },
      freehand: true
    });

    draw.on('drawend', function drawSearchBox(evt) {
      var geometry = evt.feature.getGeometry();
      var geoCoordinates = geometry.getCoordinates();
      var westnorth = ol.proj.transform(geoCoordinates[0][0], dstProj, srcProj);
      var eastsouth = ol.proj.transform(geoCoordinates[0][2], dstProj, srcProj);
      // Check to make sure the coordinates are in the correct order
      var west = westnorth[0];
      var east = eastsouth[0];
      var north = westnorth[1];
      var south = eastsouth[1];
      if (west > east){
        west = eastsouth[0];
        east = westnorth[0];
      }
      if (south > north) {
        north = eastsouth[1];
        south = westnorth[1];
      }
      // Make corrections for queries that cross the dateline
      if (west > 180) {
        if (west > 360) {
          west = west - (360 * Math.floor(west / -360));
          if (west > 180) {
            west = west - 360;
          }
        } else {
          west = west - 360;
        }
      }
      if (west < -180) {
        if (west < -360) {
          west = west + (360 * Math.floor(west / -360));
          if (west < -180) {
            west = west + 360;
          }
        } else {
          west = west + 360;
        }
      }
      if (east > 180) {
        // Fix overlapping longitudinal query parameters
        if (east > 360) {
          east = east - (360 * Math.floor(east / 360));
          if (east > 180) {
            east = east - 360;
          }
        } else {
          east = east - 360;
        }
      }
      var rawFilter = geoField + ':Intersects(ENVELOPE(' + west + ', ' + east + ', ' + north + ', ' + south + '))';
      location.href = baseURL + searchParams + "&filter[]=" + rawFilter;
    }, this);
    map.addInteraction(draw);
  }
  init();
  document.getElementById("draw_box").onclick = function clearAndDrawMap() {
    map.removeInteraction(draw);
    addInteraction();
  };
  init = false;
}
