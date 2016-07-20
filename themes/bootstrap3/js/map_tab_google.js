/*global google */
/*exported initialize */
var markers;
var markersData;
var latlng;
var myOptions;
var map;
var infowindow = new google.maps.InfoWindow({maxWidth: 480, minWidth: 480});
function initialize(mapData) {
  markersData = mapData;
  latlng = new google.maps.LatLng(0, 0);
  myOptions = {
    zoom: 1,
    center: latlng,
    mapTypeControl: true,
    mapTypeControlOptions: {
      style: google.maps.MapTypeControlStyle.DROPDOWN_MENU
    },
    mapTypeId: google.maps.MapTypeId.ROADMAP
  };
  map = new google.maps.Map(document.getElementById("map_canvas"),
    myOptions);
  showMarkers();
  }
function showMarkers(){
  deleteOverlays();
  markers = [];

  for (var i = 0; i < markersData.length; i++){
    var disTitle = markersData[i].title;
    var iconTitle = disTitle;
    if (disTitle.length > 25){
      iconTitle = disTitle.substring(0, 25) + "...";
    }
    var markerImg = "https://chart.googleapis.com/chart?chst=d_bubble_text_small&chld=edge_bc|" + iconTitle + "|EEEAE3|";
    var labelXoffset = 1 + disTitle.length * 4;
    var latLng = new google.maps.LatLng(markersData[i].lat, markersData[i].lon)
    var marker = new google.maps.Marker({
      position: latLng,
      map: map,
      title: disTitle,
      icon: markerImg
    });
    markers.push(marker);
  }
}
function deleteOverlays() {
  if (markers) {
    var i;
    for (i in markers) {
      markers[i].setMap(null);
    }
    markers.length = 0;
  }
}
function refreshMap() {
  showMarkers();
}
google.maps.event.addDomListener(window, 'load', initialize);
