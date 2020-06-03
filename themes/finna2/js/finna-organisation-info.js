/*global VuFind, finna */
finna.organisationInfo = (function finnaOrganisationInfo() {
  var organisationList = {};

  function query(parentId, queryParams, callback) {
    var url = VuFind.path + '/AJAX/JSON';
    var org = {'id': parentId, 'sector': ''};
    var params = {method: 'getOrganisationInfo', parent: org, params: queryParams};
    $.getJSON(url, params)
      .done(function onGetOrganisationInfoDone(response) {
        if (response.data) {
          callback(true, response.data);
          return;
        }
        callback(false, 'Error reading organisation info');
      })
      .fail(function onGetOrganisationInfoFall(response/*, textStatus, err*/) {
        var error = false;
        if (typeof response.responseJSON !== 'undefined') {
          error = response.responseJSON.data;
        }
        callback(false, error);
      });
  }

  function getCachedDetails(id) {
    if (typeof organisationList[id].details !== 'undefined') {
      return organisationList[id].details;
    }
    return null;
  }

  function getField(obj, field, organisationId) {
    var res = finna.common.getField(obj, field);
    if (res !== null) {
      return res;
    }
    if (organisationId) {
      var cache = getCachedDetails(organisationId);
      if (cache) {
        if (typeof cache[field] != 'undefined') {
          return cache[field];
        }
      }
    }
    return null;
  }

  function cacheSchedules(id, data) {
    var schedules = finna.common.getField(data, 'openTimes');
    if (schedules) {
      organisationList[id].openTimes = schedules;
      organisationList[id].details.openTimes = schedules;
    }
    var scheduleDesc = finna.common.getField(data, 'scheduleDescriptions');
    if (scheduleDesc) {
      organisationList[id].details.scheduleDescriptions = scheduleDesc;
      organisationList[id].scheduleDescriptions = scheduleDesc;
    }
  }

  function getOrganisations(target, parent, buildings, callbackParams, callback) {
    if (typeof parent === 'undefined') {
      return;
    }

    if (parent in organisationList) {
      callback(organisationList[parent]);
    }

    query(parent, {action: 'consortium', target: target, buildings: buildings}, function onQueryDone(success, response) {
      if (!success) {
        callback(false, callbackParams);
        return;
      }
      var list = getField(response, 'list');
      $.each(list, function handleList(ind, obj) {
        organisationList[obj.id] = obj;
        organisationList[obj.id].details = {};
        cacheSchedules(obj.id, obj);
      });
      callback(response, callbackParams);
    });
  }

  function getInfo(id) {
    if (!(id in organisationList)) {
      return false;
    }
    return organisationList[id];
  }

  function getDetails(id) {
    if (!(id in organisationList)) {
      return false;
    }

    var data = organisationList[id];
    var details = {};
    var openNow = getField(data, 'openNow');
    if (openNow !== null) {
      details.openNow = openNow;
    }

    $(['name', 'email', 'homepage', 'routeUrl', 'mapUrl', 'openToday', 'buildingYear', 'openTimes', 'schedule-descriptions'])
      .each(function handleField(ind, field) {
        var val = getField(data, field);
        if (val) {
          details[field] = val;
        }
      });

    var address = '';
    var street = getField(data.address, 'street');
    if (street) {
      address += street;
    }
    var zipcode = getField(data.address, 'zipcode');
    if (zipcode) {
      address += ', ' + zipcode;
    }
    var city = getField(data.address, 'city');
    if (city) {
      address += ' ' + city;
    }

    details.address = address;

    var cached = getCachedDetails(id);
    if (cached) {
      details = $.extend(details, {details: cached});
    }
    return details;
  }

  function cacheDetails(id, details) {
    if (!('openTimes' in details) && 'openTimes' in organisationList[id]) {
      details.openTimes = organisationList[id].openTimes;
    }
    organisationList[id].details = details;
  }

  function getSchedules(target, parent, id, periodStart, dir, fullDetails, allServices, callback) {
    var params = {
      target: target, action: 'details', id: id,
      fullDetails: fullDetails ? 1 : 0,
      allServices: allServices ? 1 : 0
    };

    if (periodStart) {
      params = $.extend(params, {periodStart: periodStart});
    }
    if (dir) {
      params = $.extend(params, {dir: dir});
    }

    query(parent, params, function onQueryDone(success, obj) {
      if (!success) {
        callback(false);
        return;
      }

      if (fullDetails) {
        cacheDetails(id, obj);
      }
      cacheSchedules(id, obj);

      var result = {};
      $(['openTimes', 'scheduleDescriptions', 'periodStart', 'weekNum', 'currentWeek', 'phone', 'links', 'facility-image', 'services', 'pictures', 'rss'])
        .each(function handleField(ind, field) {
          var val = getField(obj, field, id);
          if (val) {
            result[field] = val;
          }
        });

      callback(result);
    });
  }

  var my = {
    getOrganisations: getOrganisations,
    getInfo: getInfo,
    getDetails: getDetails,
    getSchedules: getSchedules
  };

  return my;
})();
