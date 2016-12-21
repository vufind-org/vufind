finna.StreetSearch = (function() {
    var startButton, terminateButton, progressContainer;

    var geolocationAccuracyThreshold = 20; // If accuracy >= threshold then give a warning for the user
    var searchRadius = 0.1; // Radius of the search area in KM

    var doStreetSearch = function() {
        progressContainer.removeClass('hidden');
        progressContainer.find('.fa-spinner').removeClass('hidden');
        terminate = false;
        startButton.prop('disabled', true);

        info(VuFind.translate('street_search_checking_for_geolocation'));

        if ('geolocation' in navigator) {
            info(VuFind.translate('street_search_geolocation_available'));
            navigator.geolocation.getCurrentPosition(locationSearch, geoLocationError, { timeout: 30000, maximumAge: 10000 });
        } else {
            geoLocationError();
        }
    };

    var terminateStreetSearch = function() {
        terminate = true;
        progressContainer.addClass('hidden');
        startButton.prop('disabled', false);
    };

    var geoLocationError = function(error) {
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
        info(errorString, true);
    };

    var locationSearch = function(position) {
        if (terminate) {
            return;
        }

        if (position.coords.accuracy >= geolocationAccuracyThreshold) {
            info(VuFind.translate('street_search_coordinates_found_accuracy_bad'), false, false);
        } else {
            info(VuFind.translate('street_search_coordinates_found'), false, false);
        }

        queryParameters = {
            'type': 'AllFields',
            'limit': '100',
            'view': 'grid',
            'filter': [
                '~format:"0/Image/"',
                '~format:"0/Place/"',
                'online_boolean:"1"',
                '{!geofilt sfield=location_geo pt=' + position.coords.latitude + ',' + position.coords.longitude + ' d=' + searchRadius + '}'
            ],
            'streetsearch': '1'
        };
        url = VuFind.path + '/Search/Results?' + $.param(queryParameters);
        window.location.href = url;
    };

    var info = function(message, stopped, allowStopping) {
        if (typeof stopped !== 'undefined' && stopped) {
            terminateButton.addClass('hidden');
            progressContainer.find('.fa-spinner').addClass('hidden');
            startButton.prop('disabled', false);
        } else if (typeof allowStopping !== 'undefined' && !allowStopping) {
            terminateButton.addClass('hidden');
        }
        var div = $('<div></div>').text(message);
        progressContainer.find('.info').empty().append(div);
    };

    var initPageElements = function () {
        startButton = $('.street-search-button');
        terminateButton = $('.street-search-terminate');
        progressContainer = $('.street-search-progress');
        terminate = false;
        startButton.click(doStreetSearch);
        terminateButton.click(terminateStreetSearch);
        var query = '&' + window.location.href.split('?')[1];
        if (query.indexOf('&go=1') >= 0) {
           startButton.click();
        }
    };

    var init = function () {
        initPageElements();
    };

    var my = {
        init: init
    };
    return my;

})(finna);
