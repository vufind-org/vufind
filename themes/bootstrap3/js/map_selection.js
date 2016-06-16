//Coordinate order:  Storage and Query: WENS ; Display: WSEN

function loadMapSelection(geoField, boundingBox, baseURL, searchParams, showSelection) {
    var init = true;
    var srcProj = 'EPSG:4326';
    var dstProj = 'EPSG:900913';
    var osm = new ol.layer.Tile({source: new ol.source.OSM()});
    var vectorSource = new ol.source.Vector();
    var vectorLayer = new ol.layer.Vector({ source: vectorSource });
    var draw, map;
    var geometryFunction = function(coordinates, geometry) {
       if (!geometry) {
          geometry = new ol.geom.Polygon(null);
       }
       var start = coordinates[0];
       var end = coordinates[1];
       geometry.setCoordinates([
         [start, [start[0], end[1]], end, [end[0], start[1]], start]
       ]);
       return geometry;
     };

    $('#geo_search').show();
    var init = function(){
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

       if (showSelection == true) {
          vectorSource.clear();
          var newBbox = new ol.geom.Polygon([[
             ol.proj.transform([boundingBox[0],boundingBox[3]], srcProj, dstProj),
             ol.proj.transform([boundingBox[0],boundingBox[1]], srcProj, dstProj),
             ol.proj.transform([boundingBox[2],boundingBox[1]], srcProj, dstProj),
             ol.proj.transform([boundingBox[2],boundingBox[3]], srcProj, dstProj)
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
        geometryFunction: geometryFunction
      });
      draw.on('drawend', function(evt) {
        var geometry = evt.feature.getGeometry();
        var coordinates = geometry.getCoordinates();
        var westnorth = ol.proj.transform(coordinates[0][0], dstProj, srcProj);
        var eastsouth = ol.proj.transform(coordinates[0][2], dstProj, srcProj);
        var rawFilter = geoField + ':Intersects(ENVELOPE(' + westnorth[0] + ', ' + eastsouth[0] + ', ' + westnorth[1] + ', ' + eastsouth[1] + '))';
        location.href = baseURL + searchParams + "&filter[]=" + rawFilter;
      }, this);
      map.addInteraction(draw);
    }   
   init();
   $('button').on('click', function () {
    //Show Draw Search Box help if it's their first visit of the day
    if (document.cookie.indexOf("visited")<0) {
        // set a new cookie
        expiry = (24*60*60*1000); // one day = 24hr * 60 min * 60 sec * 1000 milliseconds
        document.cookie = "visited=yes; max-age=" + expiry;
        window.alert("To draw the search box:\n1) Click and release on starting point\n2) Drag box\n3) Click on ending point");
    }
     vectorSource.clear();
     map.removeInteraction(draw);
     addInteraction();
   });

  init = false;
}
