/*global L, VuFind */
/*exported loadMapSelection */
//Coordinate order:  Storage and Query: WENS ; Display: WSEN

function loadMapSelection(geoField, boundingBox, baseURL, homeURL, searchParams, resultsCoords, basemap) {
  // Initialize variables
  var searchboxLayer = L.featureGroup();
  var drawnItemsLayer = L.featureGroup();
  var mcgIDs = [];
  var clickedIDs = [];
  var clickedBounds = [];
  var drawnItems;
  var basemapLayer = new L.TileLayer(basemap[0], {attribution: basemap[1]});
  var mapSearch;
  // Define styles for icons and clusters
  var searchIcon = L.Icon.extend({
    options: {
      iconSize: [25, 41],
      iconAnchor: [12, 41],
      popupAnchor: [1, -34],
      shadowSize: [41, 41]
    }
  });
  
  // Red will be used for search results display
  var redIcon = new searchIcon({
    iconUrl: VuFind.path + '/themes/bootstrap3/css/vendor/leaflet/images/marker-icon-2x-red.png',
    shadowUrl: VuFind.path + '/themes/bootstrap3/css/vendor/leaflet/images/marker-shadow.png'
  });
  
  var redRectIcon = new searchIcon({
    iconUrl: VuFind.path + '/themes/bootstrap3/css/vendor/leaflet/images/rectangle-icon-2x-red.png',
    shadowUrl: VuFind.path + '/themes/bootstrap3/css/vendor/leaflet/images/marker-shadow.png'
  });

  // Blue will be used when a user selects a geofeature
  var blueIcon = new searchIcon({
    iconUrl: VuFind.path + '/themes/bootstrap3/css/vendor/leaflet/images/marker-icon-2x-blue.png',
    shadowUrl: VuFind.path + '/themes/bootstrap3/css/vendor/leaflet/images/marker-shadow.png'
  });
  var blueRectIcon = new searchIcon({
    iconUrl: VuFind.path + '/themes/bootstrap3/css/vendor/leaflet/images/rectangle-icon-2x-blue.png',
    shadowUrl: VuFind.path + '/themes/bootstrap3/css/vendor/leaflet/images/marker-shadow.png'
  });

  // Initialize marker clusters with icon colors
  var markerClusters = new L.MarkerClusterGroup({
    iconCreateFunction: function icf(cluster) {
      var childCount = cluster.getChildCount();
      var markers = cluster.getAllChildMarkers();
      var cstatus = "";
      for (var i = 0; i < markers.length; i++) {
        cstatus = markers[i].options.recStatus;
        i = i + markers[i].options.total;
      }
      var c = ' marker-cluster-';
      if (cstatus === 'active') {   
        c += 'active';
      } else {
        c += 'inactive';
      }
      return new L.DivIcon({ html: '<div><span><b>' + childCount + '</b></span></div>', className: 'marker-cluster' + c, iconSize: new L.Point(40, 40) });
    }
  });

  // Handle user interaction with markers and rectangles
  //----------------------------------------------------//
  function onClick() {
    mapSearch.eachLayer(function msl(layer) {
      if (layer.options.id === "mRect") {
        mapSearch.removeLayer(layer);
      }
    });

    // Reset previously selected features to inactive color - RED
    if (clickedIDs.length > 0) {
      markerClusters.eachLayer(function mcl(layer){
        if (layer.options.recID === clickedIDs[0]) {
          layer.options.recStatus = 'inactive';
          layer._popup.setContent(layer.options.recPopup);
          if (layer.options.recType === "rectangle") {
            layer.setIcon(redRectIcon);
          } else {
            layer.setIcon(redIcon);
          } 
        } 
      });
      clickedIDs = []; 
      clickedBounds = [];
    }
    
    //Handle current feature selection
    //Change color of all features with thisID to BLUE
    var thisID = this.options.recID;
    clickedIDs.push(thisID);
    var j = 0;
    markerClusters.eachLayer(function mc(layer){
      if (layer.options.recID === thisID) {
        j = j++;
        layer.options.recStatus = 'active';
        if (layer.options.recType === "rectangle") {
          layer.setIcon(blueRectIcon);
        } else {
          layer.setIcon(blueIcon);
        }
        clickedBounds.push([layer.getLatLng().lat, layer.getLatLng().lng]);
        if (layer.options.recType === "rectangle") {
          // create rectangle from options and show
          var mRect_sw = L.latLng([layer.options.recS, layer.options.recW]);
          var mRect_ne = L.latLng([layer.options.recN, layer.options.recE]);
          var mRect = L.rectangle([[mRect_sw, mRect_ne]], {
            color: '#3388ff',
            fillOcpacity: 0.1,
            weight: 2,
            id: 'mRect'
          });
          mRect.bindPopup(L.popup().setContent(layer.options.rmPopup));
          var mrBounds = mRect.getBounds();
          clickedBounds.push([
            [mrBounds.getSouthWest().lat, mrBounds.getSouthWest().lng],
            [mrBounds.getNorthEast().lat, mrBounds.getNorthEast().lng]
          ]);
          mapSearch.addLayer(mRect);
        }
      }
    });
    markerClusters.refreshClusters();

    // Check if there are multiple markers at this location
    // If so, update popup to show title for all rectangles by
    // combining popup content.
    thisID = this.options.recID;
    var thisLat = this.getLatLng().lat;
    var thisLng = this.getLatLng().lng;
    var updatePopup = [this._popup.getContent()];
    markerClusters.eachLayer(function mc(layer){
      var mLat = layer.getLatLng().lat;
      var mLng = layer.getLatLng().lng;
      var mPopup = layer._popup.getContent();
      if ((mLat === thisLat && mLng === thisLng) && updatePopup.indexOf(mPopup) < 0) {
        updatePopup.push(mPopup);
      }
    });
    this._popup.setContent(updatePopup.join(" "));
  }

  // Searchbox
  //-------------------------------------//
  // Retrieve searchbox coordinates 
  var sb_west = boundingBox[0];
  var sb_south = boundingBox[1];
  var sb_east = boundingBox[2];
  var sb_north = boundingBox[3];

  // Adjust searchbox to a 0-360 grid if it crosses the dateline
  if (sb_west > sb_east) {
    // Move west left of east
    if (sb_east >= 0) {
      sb_west = sb_west - 360;
    } else {
      // Move east right of west
      sb_east = sb_east + 360;
    }
  }
  var sb_sw = L.latLng([sb_south, sb_west]);
  var sb_ne = L.latLng([sb_north, sb_east]);

  // Create searchBox feature
  var searchboxFeature = L.rectangle ([[sb_sw, sb_ne]], {
    color: 'red',
    fillColor: 'red',
    fillOcpacity: 0.4,
    weight: 2
  });

  var sb_bounds = searchboxFeature.getBounds();
  var sb_center = sb_bounds.getCenter();
  searchboxFeature.addTo(searchboxLayer);

  // Search results
  //-------------------------------------//
  // Create a new vector type for rectangle features
  // with getLatLng and setLatLng methods.
  L.RectangleClusterable = L.Rectangle.extend({
    _originalInitialize: L.Rectangle.prototype.initialize,
    initialize: function lrc(bounds, options) {
      this._originalInitialize(bounds, options);
      this._latlng = this.getBounds().getCenter();
    },
    getLatLng: function e() {
      return this._latlng;
    },
    setLatLng: function e() {}
  });

  // Process result coordinates 
  for (var i = 0; i < resultsCoords.length; ++i )
  {
    var rcType;
    var rcFeature;
    var rcStatus = "inactive";
    var recID = resultsCoords[i][0];
    var recTitle = resultsCoords[i][1];
    var popupContent = '<article class="geoItem"><a href="' + homeURL + 'Record/' + recID + '">' + recTitle + '</a></article>';
    var popup = L.popup().setContent(popupContent);
    var rc_west = resultsCoords[i][2];
    var rc_east = resultsCoords[i][3];
    var rc_north = resultsCoords[i][4];
    var rc_south = resultsCoords[i][5];
    sb_west = searchboxFeature.getBounds().getWest();
    sb_east = searchboxFeature.getBounds().getEast(); 

    if (sb_west >= -180 && sb_east <= 180) {
      // do nothing
    } else {
      //move coordinates if they are outside of searchbox bounds
      if (rc_west < sb_west ) {
        rc_west = rc_west + 360;
        rc_east = rc_east + 360;
      }
      if (rc_east > sb_east) {
        rc_west = rc_west - 360; 
        rc_east = rc_east - 360;
      } 
    }

    if (rc_west === rc_east && rc_north === rc_south) {
      rcType = "point";
      rcFeature = L.marker([rc_south, rc_west], {
        recID: recID, 
        recType: rcType,
        recStatus: rcStatus,
        recPopup: popupContent,
        icon: redIcon
      });
    } else {
      rcType = "rectangle";
      rcFeature = new L.RectangleClusterable([
        [rc_south, rc_west],
        [rc_north, rc_east]
      ], {recID: recID, recType: rcType, recStatus: rcStatus, recPopup: popupContent, color: '#910a0a' });
    }
    rcFeature.bindPopup(popup);
    rcFeature.on('click', onClick);
       
    // Only add feature to markerClusters if it is within or intersects searchbox
    if (rcType === "rectangle") {
      if (searchboxFeature.getBounds().intersects(rcFeature.getBounds())) {
        // add center point to layer
        var rectCtr = rcFeature.getBounds().getCenter();
        var rmPopupContent = '<article class="geoItem"><a href="' + homeURL
          + 'Record/' + recID + '">' + recTitle + '</a><br><em>'
          + VuFind.translate('rectangle_center_message') + '</em></article>';
        var rmPopup = L.popup().setContent(rmPopupContent);
        var rectMarker = L.marker(rectCtr, {
          recID: recID,
          recType: "rectangle",
          recStatus: rcStatus,
          recPopup: rmPopupContent,
          rmPopup: popupContent,
          recN: rc_north,
          recS: rc_south,
          recE: rc_east,
          recW: rc_west,
          icon: redRectIcon
        });
        rectMarker.bindPopup(rmPopup);
        rectMarker.on('click', onClick);
        markerClusters.addLayer(rectMarker);
        mcgIDs.push(recID);
      }
    } else if (searchboxFeature.getBounds().contains(rcFeature.getLatLng())){
      markerClusters.addLayer(rcFeature);
      mcgIDs.push(recID);
    }
  }

  // Turn on map selection pane
  $('#geo_search').show();
 
  // Create map
  mapSearch = new L.Map ("geo_search_map", {
    layers: [basemapLayer, searchboxLayer, markerClusters, drawnItemsLayer],
    center: sb_center
  });
  mapSearch.fitBounds(sb_bounds);  

  // Add search functionality
  drawnItems = new L.Draw.Rectangle(mapSearch);
  
  mapSearch.on('draw:created', function ms(e) {
    var layer = e.layer;
    drawnItemsLayer.addLayer(layer);
    
    // Get search box coordinates SW, NW, NE, SE
    // note the wrap() function creates 180 to -180 compliant longitude values.
    var di_ne = layer.getBounds().getNorthEast().wrap();
    var di_sw = layer.getBounds().getSouthWest().wrap();
    var di_north = di_ne.lat;
    var di_east = di_ne.lng;
    var di_south = di_sw.lat;
    var di_west = di_sw.lng;
    
    //Create search query
    var rawFilter = geoField + ':Intersects(ENVELOPE(' + di_west + ', ' + di_east + ', ' + di_north + ', ' + di_south + '))';
    location.href = baseURL + searchParams + "&filter[]=" + rawFilter;
  }, this);
  
  document.getElementById("draw_box").onclick = function drawSearchBox() {
    drawnItemsLayer.clearLayers();
    new L.Draw.Rectangle(mapSearch, drawnItems.options.rectangle).enable();
  };
  
  // If user clicks on map anywhere turn all features to inactive color - RED
  // and reset clicked arrays
  mapSearch.on('click', function ms() {
    mapSearch.eachLayer(function msl(layer) {
      if (layer.options.id === "mRect") {
        mapSearch.removeLayer(layer);
      }
    });

    if (clickedIDs.length > 0) {
      markerClusters.eachLayer(function mc(layer){
        layer.options.recStatus = 'inactive';
        if (layer.options.recType === "rectangle") {
          layer.setIcon(redRectIcon);
        } else {
          layer.setIcon(redIcon);
        }
      });
      clickedIDs = [];
      clickedBounds = [];
      markerClusters.refreshClusters();
    }
  });
}
