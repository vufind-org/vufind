/*global ol */
/*exported loadMapSelection */
//Coordinate order:  Storage and Query: WENS ; Display: WSEN

function loadMapSelection(geoField, boundingBox, baseURL, searchParams, showSelection) {
  var init = true;
  var srcProj = 'EPSG:4326';
  var dstProj = 'EPSG:900913';
  var osm = new ol.layer.Tile({source: new ol.source.OSM()});
  var vectorSource = new ol.source.Vector();
  var vectorStyle = new ol.style.Style({
    fill: new ol.style.Fill({
      color: [255, 0, 0, .1]
    }),
    stroke: new ol.style.Stroke({
      color: [255, 0, 0, 1],
      width: 2
    })
  });
  var vectorLayer = new ol.layer.Vector({ source: vectorSource, style: vectorStyle });
  var draw, map;
  function rectangleFunction(coordinates, geometry) {
    if (!geometry) {
      geometry = new ol.geom.Polygon(null);
    }
    var start = coordinates[0];
    var end = coordinates[1];
    geometry.setCoordinates([
      [start, [start[0], end[1]], end, [end[0], start[1]], start]
    ]);
    return geometry;
  }

  $('#geo_search').show();
  init = function drawMap() {
    map = new ol.Map({
      interactions: ol.interaction.defaults({
        shiftDragZoom: false
      }),
      target: 'geo_search_map',
      projection: dstProj,
      layers: [osm, vectorLayer],
      view: new ol.View({
        center: [0, 0],
        zoom: 1
      })
    });

    if (showSelection === true) {
      vectorSource.clear();
      // Adjust bounding box (WSEN) display for queries crossing the dateline
      if (boundingBox[0] > boundingBox[2]) {
        boundingBox[2] = boundingBox[2] + 360;
      }
      var newBbox = new ol.geom.Polygon([[
        ol.proj.transform([boundingBox[0], boundingBox[3]], srcProj, dstProj),
        ol.proj.transform([boundingBox[0], boundingBox[1]], srcProj, dstProj),
        ol.proj.transform([boundingBox[2], boundingBox[1]], srcProj, dstProj),
        ol.proj.transform([boundingBox[2], boundingBox[3]], srcProj, dstProj)
      ]]); 
      var featureBbox = new ol.Feature({ 
        name: "bbox",
        geometry: newBbox
      });
      vectorSource.addFeature(featureBbox);
      map.getView().fit(vectorSource.getExtent(), map.getSize());
    }
  } 
  function addInteraction() {
    draw = new ol.interaction.Draw ({
      source: vectorSource,
      type: 'LineString',
      maxPoints: 2,
      geometryFunction: rectangleFunction
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
      if (west < -180) {
        west = west + 360;
      }
      if (east > 180) {
        east = east - 360;
      }
      var rawFilter = geoField + ':Intersects(ENVELOPE(' + west + ', ' + east + ', ' + north + ', ' + south + '))';
      location.href = baseURL + searchParams + "&filter[]=" + rawFilter;
    }, this);
    map.addInteraction(draw);
  }   
  init();
  $('button').on('click', function clearAndDrawMap() {
    vectorSource.clear();
    map.removeInteraction(draw);
    addInteraction();
  });

  init = false;
}
