/*global ol */
/*exported loadMapSelection */
//Coordinate order:  Storage and Query: WENS ; Display: WSEN

function loadMapSelection(geoField, boundingBox, baseURL, searchParams, showSelection, resultsCoords) {
  var init = true;
  var srcProj = 'EPSG:4326';
  var dstProj = 'EPSG:900913';
  var osm = new ol.layer.Tile({source: new ol.source.OSM()});
  var searchboxSource = new ol.source.Vector();
  var searchboxStyle = new ol.style.Style({
    fill: new ol.style.Fill({
      color: [255, 0, 0, .1]
    }),
    stroke: new ol.style.Stroke({
      color: [255, 0, 0, 1],
      width: 2
    })
  });
  var searchboxLayer = new ol.layer.Vector({ source: searchboxSource, style: searchboxStyle });

  var draw, map;
  var count = resultsCoords.length;
  var searchResults = new Array(count);
  var searchIds = new Array(count);
  for (var i = 0; i < count; ++i) {
    var markerCoordinates = ol.proj.transform([resultsCoords[i][1], resultsCoords[i][2]], srcProj, dstProj);
    searchResults[i] = new ol.Feature(new ol.geom.Point(markerCoordinates));
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
    source: clusterSource,
    style: function addClusterStyle(feature) {
      var size = feature.get('features').length;
      var pointRadius = 8 + (size.toString().length * 2);
      var style = styleCache[size];
      if (!style) {
        style = [new ol.style.Style({
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
        })];
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
  } 
  function addInteraction() {
    draw = new ol.interaction.Draw ({
      source: searchboxSource,
      type: 'LineString',
      maxPoints: 2,
      geometryFunction: function rectangleFunction(coordinates, geometryParam) {
        var geometry = geometryParam ? geometryParam : new ol.geom.Polygon(null);
        var start = coordinates[0];
        var end = coordinates[1];
        geometry.setCoordinates([
         [start, [start[0], end[1]], end, [end[0], start[1]], start]
        ]);
        return geometry;
      }
    });
    draw.on('drawend', function drawSearchBox(evt) {
      var geometry = evt.feature.getGeometry();
      var coordinates = geometry.getCoordinates();
      var westnorth = ol.proj.transform(coordinates[0][0], dstProj, srcProj);
      var eastsouth = ol.proj.transform(coordinates[0][2], dstProj, srcProj);
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
  $('button').on('click', function clearAndDrawMap() {
    map.removeInteraction(draw);
    addInteraction();
  });

  init = false;
}
