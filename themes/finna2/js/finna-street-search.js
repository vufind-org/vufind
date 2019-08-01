/*global VuFind, finna */
finna.StreetSearch = (function finnaStreetSearch() {
  var terminateButton, progressContainer;
  var terminate = false;

  var geolocationAccuracyThreshold = 20; // If accuracy >= threshold then give a warning for the user
  var searchRadius = 0.1; // Radius of the search area in KM

  function info(message, stopped, allowStopping) {
    if (typeof stopped !== 'undefined' && stopped) {
      terminateButton.addClass('hidden');
      progressContainer.find('.fa-spinner').addClass('hidden');
      $('.street-search-action-links').removeClass('hidden');
    } else if (typeof allowStopping !== 'undefined' && !allowStopping) {
      terminateButton.addClass('hidden');
    }
    var div = $('<div></div>').text(message);
    progressContainer.find('.info').empty().append(div);
  }

  function geoLocationError(error) {
    var errorString = 'street_search_geolocation_other_error';
    var additionalInfo = '';
    if (error) {
      switch (error.code) {
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
        additionalInfo = error.message;
        break;
      }
    }
    errorString = VuFind.translate(errorString);
    if (additionalInfo) {
      errorString += ' -- ' + additionalInfo;
    }
    info(errorString, true);
    $('.street-search-action-links').removeClass('hidden');
  }

  function locationSearch(position) {
    if (terminate) {
      return;
    }

    if (position.coords.accuracy >= geolocationAccuracyThreshold) {
      info(VuFind.translate('street_search_coordinates_found_accuracy_bad'), false, false);
    } else {
      info(VuFind.translate('street_search_coordinates_found'), false, false);
    }

    var queryParameters = {
      'type': 'AllFields',
      'limit': '100',
      'view': 'grid',
      'filter': [
        '~format:"0/Image/"',
        '~format:"0/Place/"',
        'free_online_boolean:"1"',
        '{!geofilt sfield=location_geo pt=' + position.coords.latitude + ',' + position.coords.longitude + ' d=' + searchRadius + '}'
      ],
      'streetsearch': '1'
    };
    var url = VuFind.path + '/Search/Results?' + $.param(queryParameters);
    window.location.href = url;
  }

  function doStreetSearch() {
    progressContainer.removeClass('hidden');
    progressContainer.find('.fa-spinner').removeClass('hidden');
    terminate = false;

    info(VuFind.translate('street_search_checking_for_geolocation'));

    if ('geolocation' in navigator) {
      info(VuFind.translate('street_search_geolocation_available'));
      navigator.geolocation.getCurrentPosition(locationSearch, geoLocationError, { timeout: 30000, maximumAge: 10000 });
    } else {
      geoLocationError();
    }
  }

  function terminateStreetSearch() {
    terminate = true;
    progressContainer.addClass('hidden');
    $('.street-search-action-links').removeClass('hidden');
  }

  function initPageElements() {
    terminateButton = $('.street-search-terminate');
    progressContainer = $('.street-search-progress');
    terminate = false;
    terminateButton.click(terminateStreetSearch);
    var query = '&' + window.location.href.split('?')[1];
    if (query.indexOf('&go=1') >= 0) {
      $('.street-search-action-links').addClass('hidden');
      doStreetSearch();
    }
  }

  function initStreetHeader(){
    progressContainer = $('.street-search-progress');
    $('.update-location').click(function updateStreetSearchLocation(e){
      e.preventDefault();
      doStreetSearch();
    });
  }

  function init() {
    initPageElements();
  }

  var my = {
    init: init,
    initStreetHeader: initStreetHeader
  };

  return my;
})();
