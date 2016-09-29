finna.StreetSearch = (function() {
    var startButton, terminateButton, progressContainer, getPositionSuccess, xhr;

    var reverseGeocodeService = 'https://api.digitransit.fi/geocoding/v1/reverse';
    var geolocationAccuracyTreshold = 20; // If accuracy >= treshold then give a warning for the user

    var doStreetSearch = function() {
        progressContainer.removeClass('hidden');
        progressContainer.find('.fa-spinner').removeClass('hidden');
        terminate = false;
        startButton.addClass('hidden'); 

        info(VuFind.translate('street_search_checking_for_geolocation'));

        if ('geolocation' in navigator) {
            info(VuFind.translate('street_search_geolocation_available'));
            navigator.geolocation.getCurrentPosition(reverseGeocode, geoLocationError, { timeout: 10000, maximumAge: 10000 });
        } else {
            geoLocationError();
        }
    };

    var terminateStreetSearch = function() {
        terminate = true;
        progressContainer.addClass('hidden');
        startButton.removeClass('hidden');
        if (typeof xhr !== 'undefined') {
            xhr.abort();
        }
    };
   
    var geoLocationError = function(error) {
        if (!getPositionSuccess) {
            var errorString = 'street_search_geolocation_other_error';
            var additionalInfo = '';
            if (error) {
                additionalInfo = error.message;
                switch(error.code) {
                    case error.POSITION_UNAVAILABLE:
                        errorString = 'street_search_geolocation_position_unavailable';
                        break;
                    case error.PERMISSION_DENIED:
                        errorString = 'street_search_geolocation_inactive';
                        break;
                    case error.TIMEOUT:
                        errorString = 'street_search_timeout';
                        break;
                    default:
                        // do nothing
                        break;
                }
            }
            errorString = VuFind.translate(errorString);
            if (additionalInfo) {
                errorString += ' -- ' + additionalInfo;
            }
            info(errorString, 1);
        }
    };

    var reverseGeocode = function(position) {
        if (terminate) {
            return;
        }
        getPositionSuccess = true;
    
        if (position.coords.accuracy >= geolocationAccuracyTreshold) {
            info(VuFind.translate('street_search_coordinates_found_accuracy_bad'));
        } else {
            info(VuFind.translate('street_search_coordinates_found'));
        }

        queryParameters = {
            'point.lat': position.coords.latitude,
            'point.lon': position.coords.longitude,
            'size': '1'
        };
    
        url = reverseGeocodeService + '?' + $.param(queryParameters);
    
        xhr = $.ajax({
            method: "GET",
            dataType: "json",
            url: url
        })
        .done(function(data) {
            if (data.features[0] && (street = data.features[0].properties.street) 
                && (city = data.features[0].properties.locality)
            ) {
                buildSearch(street, city);
            } else {
                info(VuFind.translate('street_search_no_streetname_found'), 1, 1);
            }
        })
        .fail(function() {
            info(VuFind.translate('street_search_reversegeocode_unavailable'), 1, 1);          
        });
    };
 
    var buildSearch = function(street, city) {
        if (!terminate) {
            info(VuFind.translate('street_search_searching_for') + ' ' + street + ' ' + city, 1, 1);

            queryParameters = {
                'lookfor': street + ' ' + city,
                'type': 'AllFields',
                'limit': '100',
                'view': 'grid',
                'filter': [
                    '~format:"0/Image/"',
                    '~format:"0/Place/"',
                    'online_boolean:"1"'
                ]
            };
            url = VuFind.path + '/Search/Results?' + $.param(queryParameters);
            window.location.href = url;
        }
    };

    var info = function(message, stopped, keepPrevious) {
        if (typeof stopped !== 'undefined' && stopped) {
            terminateButton.addClass('hidden');
            progressContainer.find('.fa-spinner').addClass('hidden');
            startButton.removeClass('hidden'); 
        }
        if (typeof keepPrevious === 'undefined' || !keepPrevious) {
            progressContainer.find('.info').empty();
        }
        var div = $('<div></div>').text(message);
        progressContainer.find('.info').append(div);        
    };

    var initPageElements = function () {
        startButton = $('.street-search-button');
        terminateButton = $('.street-search-terminate');
        progressContainer = $('.street-search-progress');
        terminate = false;
        startButton.click(doStreetSearch);
        terminateButton.click(terminateStreetSearch);
    };

    var init = function () {
        getPositionSuccess = false;
        initPageElements();
    };
    
    var my = {
        init: init
    };
    return my;

})(finna);
