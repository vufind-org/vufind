var OpenStreetMapWrapper = {
    map: null,
    layers: {
        mapnik: null,
        markers: null
    },
    Lon2Merc: function(lon) {
        return 20037508.34 * lon / 180;
    },
    Lat2Merc: function(lat) {
        let PI = 3.14159265358979323846;
        lat = Math.log(Math.tan( (90 + lat) * PI / 360)) / (PI / 180);
        return 20037508.34 * lat / 180;
    },
    AddMarker: function(lon, lat, popupContentHTML, showOnLoad=false) {
        let ll = new OpenLayers.LonLat(this.Lon2Merc(lon), this.Lat2Merc(lat));
        let feature = new OpenLayers.Feature(this.layers.markers, ll);
        feature.closeBox = true;
        feature.popupClass = OpenLayers.Class(OpenLayers.Popup.FramedCloud, { minSize: new OpenLayers.Size(300, 250) } );
        feature.data.popupContentHTML = popupContentHTML;
        feature.data.overflow = "hidden";

        let marker = new OpenLayers.Marker(ll);
        marker.feature = feature;

        let markerClick = function(evt) {
            if (this.popup == null) {
                this.popup = this.createPopup(this.closeBox);
                OpenStreetMapWrapper.map.addPopup(this.popup);
                this.popup.show();
            } else {
                this.popup.toggle();
            }
            OpenLayers.Event.stop(evt);
        };
        marker.events.register("mousedown", feature, markerClick);

        this.layers.markers.addMarker(marker);

        if (showOnLoad) {
            this.map.addPopup(feature.createPopup(feature.closeBox));
        }
    },
    DrawMap: function() {
        OpenLayers.Lang.setCode('de');
        this.map = new OpenLayers.Map('map', {
            projection: new OpenLayers.Projection("EPSG:900913"),
            displayProjection: new OpenLayers.Projection("EPSG:4326"),
            controls: [
                new OpenLayers.Control.Navigation(),
                new OpenLayers.Control.LayerSwitcher(),
                new OpenLayers.Control.PanZoomBar()],
            maxExtent:
                new OpenLayers.Bounds(-20037508.34,-20037508.34,
                                        20037508.34, 20037508.34),
            numZoomLevels: 18,
            maxResolution: 156543,
            units: 'meters'
        });

        this.layers.mapnik = new OpenLayers.Layer.OSM.Mapnik("Mapnik");
        this.layers.markers = new OpenLayers.Layer.Markers("Address", { projection: new OpenLayers.Projection("EPSG:4326"),
                                                           visibility: true, displayInLayerSwitcher: false });

        this.map.addLayers([this.layers.mapnik, this.layers.markers]);
    },
    JumpTo: function(lon, lat, zoom) {
        let x = this.Lon2Merc(lon);
        let y = this.Lat2Merc(lat);
        this.map.setCenter(new OpenLayers.LonLat(x, y), zoom);
    }
};
