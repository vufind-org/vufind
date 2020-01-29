var OpenStreetMapWrapper = {
    map: null,
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
        var locations = this.locations;
        this.map.on('singleclick', function (event) {
            console.log(event);
            var container = document.getElementById('popup');
            var content = document.getElementById('popup-content');
            var closer = document.getElementById('popup-closer');
            var overlay = new ol.Overlay({
                element: container,
                autoPan: true,
                autoPanAnimation: {
                    duration: 250
                }
            });
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
        let feature = new ol.Feature({
                        type: 'geoMarker',
                        geometry: new ol.geom.Point(ol.proj.fromLonLat([lon, lat])),
                        tuefindId: tuefindId,
                    });
        let icon = new ol.style.Icon({
            anchor: [0.5, 1],
            anchorXUnits: 'fraction',
            anchorYUnits: 'fraction',
            scale: 0.5,
            src: iconUrl,
        });
        let style = new ol.style.Style({image: icon});
        feature.setStyle(style);
        let layer = new ol.layer.Vector({
            source: new ol.source.Vector({
                features: [
                    feature
                ]
            })
        });
        this.map.addLayer(layer);
        this.locations.push({tuefindId: tuefindId, lon: lon, lat: lat, html: popupContentHTML, layer: layer});
    }
}