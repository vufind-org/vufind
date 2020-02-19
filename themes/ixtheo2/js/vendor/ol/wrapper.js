/* this file is TueFind-specific! */
var OpenStreetMapWrapper = {
    map: null,
    layer: null,
    locations: [],
    DrawMap: function(lon, lat, zoom) {
        // Initialize map
        this.map = new ol.Map({
            target: 'map',
            layers: [
                new ol.layer.Tile({
                    source: new ol.source.OSM()
                })
            ],
            view: new ol.View({
                center: ol.proj.fromLonLat([lon, lat]),
                zoom: zoom
            })
        });

        // Prepare popups for markers
        var container = document.getElementById('popup');
        var content = document.getElementById('popup-content');
        var closer = document.getElementById('popup-closer');
        closer.blur();
        var overlay = new ol.Overlay({
            element: container,
            autoPan: true,
            autoPanAnimation: {
                duration: 250
            }
        });

        var locations = this.locations;
        this.map.on('singleclick', function (event) {
            closer.onclick = function() {
                overlay.setPosition(undefined);
                closer.blur();
                return false;
            };
            OpenStreetMapWrapper.map.addOverlay(overlay);
            var coordinate = event.coordinate;
            OpenStreetMapWrapper.map.getFeaturesAtPixel(event.pixel).forEach(function(pixelFeature) {
                var pixelFeatureId = pixelFeature.values_.tuefindId;
                locations.forEach(function (location) {
                    if (location.tuefindId == pixelFeatureId) {
                        content.innerHTML = location.html;
                        overlay.setPosition(coordinate);
                    }
                });
            });
        });
    },
    AddLocation: function(lon, lat, iconUrl, popupContentHTML) {
        let tuefindId = lon + '#' + lat;
        this.locations.push({tuefindId: tuefindId, lon: lon, lat: lat, html: popupContentHTML, iconUrl: iconUrl});
    },
    BuildLocationLayer: function() {
        var features = [];
        this.locations.forEach(function(location) {;
            let feature = new ol.Feature({
                        type: 'geoMarker',
                        geometry: new ol.geom.Point(ol.proj.fromLonLat([location.lon, location.lat])),
                        tuefindId: location.tuefindId,
                    });
            let icon = new ol.style.Icon({
                anchor: [0.5, 1],
                anchorXUnits: 'fraction',
                anchorYUnits: 'fraction',
                scale: 0.5,
                src: location.iconUrl,
            });
            let style = new ol.style.Style({image: icon});
            feature.setStyle(style);
            features.push(feature);
        });

        this.layer = new ol.layer.Vector({
            source: new ol.source.Vector({
                features: features
            })
        });
        this.map.addLayer(this.layer);
    },
    ResetLocationLayer: function() {
        this.locations = [];
        if (this.layer != null) {
            this.map.removeLayer(this.layer);
            this.layer = null;
        }
    }
}