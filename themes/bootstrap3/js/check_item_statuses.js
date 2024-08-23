/*global AjaxRequestQueue, VuFind */

VuFind.register('itemStatuses', function ItemStatuses() {
  var _checkItemHandlers = {};
  var _handlerUrls = {};

  function formatCallnumbers(callnumber, callnumber_handler) {
    var cns = callnumber.split(',\t');
    for (var i = 0; i < cns.length; i++) {
      // If the call number has a special delimiter, it indicates a prefix that
      // should be used for display but not for sorting/searching.
      var actualCallNumber = cns[i];
      var displayCallNumber = cns[i];
      var parts = cns[i].split('::::');
      if (parts.length > 1) {
        displayCallNumber = parts[0] + " " + parts[1];
        actualCallNumber = parts[1];
      }

      cns[i] = callnumber_handler
        ? '<a href="' + VuFind.path + '/Alphabrowse/Home?source=' + encodeURI(callnumber_handler) + '&amp;from=' + encodeURI(actualCallNumber) + '">' + displayCallNumber + '</a>'
        : displayCallNumber;
    }
    return cns.join(',\t');
  }

  function displayItemStatus(result, el) {
    el.querySelectorAll('.status').forEach((status) => {
      status.innerHTML = result.availability_message;
    });
    el.querySelectorAll('.ajax-availability').forEach((ajaxAvailability) => {
      ajaxAvailability.classList.remove('ajax-availability');
      ajaxAvailability.classList.remove('hidden');
    });

    let callnumAndLocations = el.querySelectorAll('.callnumAndLocation');
    if (typeof(result.error) != 'undefined'
      && result.error.length > 0
    ) {
      callnumAndLocations.forEach((callnumAndLocation) => {
        callnumAndLocation.innerHTML = result.error;
        callnumAndLocation.classList.add('text-danger');
      });
      el.querySelectorAll('.callnumber,.hideIfDetailed,.location').forEach((e) => { e.classList.add('hidden'); });
    } else if (typeof(result.full_status) != 'undefined'
      && result.full_status.length > 0
      && callnumAndLocations.length > 0
    ) {
      // Full status mode is on -- display the HTML and hide extraneous junk:
      callnumAndLocations.forEach((callnumAndLocation) => {
        VuFind.setElementContents(callnumAndLocation, VuFind.updateCspNonce(result.full_status));
      });
      el.querySelectorAll('.callnumber,.hideIfDetailed,.location,.status').forEach((e) => { e.classList.add('hidden'); });
    } else if (typeof(result.missing_data) !== 'undefined'
      && result.missing_data
    ) {
      // No data is available -- hide the entire status area:
      el.querySelectorAll('.callnumAndLocation,.status').forEach((e) => e.classList.add('hidden'));
    } else if (result.locationList) {
      // We have multiple locations -- build appropriate HTML and hide unwanted labels:
      el.querySelectorAll('.callnumber,.hideIfDetailed,.location').forEach((e) => e.classList.add('hidden'));
      var locationListHTML = "";
      for (var x = 0; x < result.locationList.length; x++) {
        locationListHTML += '<div class="groupLocation">';
        if (result.locationList[x].availability) {
          locationListHTML += '<span class="text-success">'
            + VuFind.icon("status-available")
            + result.locationList[x].location
            + '</span> ';
        } else if (typeof(result.locationList[x].status_unknown) !== 'undefined'
          && result.locationList[x].status_unknown
        ) {
          if (result.locationList[x].location) {
            locationListHTML += '<span class="text-warning">'
              + VuFind.icon("status-unknown")
              + result.locationList[x].location
              + '</span> ';
          }
        } else {
          locationListHTML += '<span class="text-danger">'
            + VuFind.icon("status-unavailable")
            + result.locationList[x].location
            + '</span> ';
        }
        locationListHTML += '</div>';
        locationListHTML += '<div class="groupCallnumber">';
        locationListHTML += (result.locationList[x].callnumbers)
          ? formatCallnumbers(result.locationList[x].callnumbers, result.locationList[x].callnumber_handler) : '';
        locationListHTML += '</div>';
      }
      el.querySelectorAll('.locationDetails').forEach((locationDetails) => {
        locationDetails.classList.remove('hidden');
        locationDetails.innerHTML = locationListHTML;
      });
    } else {
      // Default case -- load call number and location into appropriate containers:
      el.querySelectorAll('.callnumber').forEach((callnumber) => {
        callnumber.innerHTML = formatCallnumbers(result.callnumber, result.callnumber_handler) + '<br>';
      });
      el.querySelectorAll('.location').forEach((location) => {
        location.innerHTML = result.reserve === 'true'
          ? result.reserve_message
          : result.location;
      });
    }
    el.classList.add('js-item-done');
    el.classList.remove('js-item-pending');
  }

  function itemStatusAjaxSuccess(items, response) {
    let idMap = {};

    // make map of ids to element arrays
    items.forEach(function mapItemId(item) {
      if (typeof idMap[item.id] === "undefined") {
        idMap[item.id] = [];
      }

      idMap[item.id].push(item.el);
    });

    // display data
    response.json().then((body) => {
      body.data.statuses.forEach(function displayItemStatusResponse(status) {
        if (typeof idMap[status.id] === "undefined") {
          return;
        }
        idMap[status.id].forEach((el) => displayItemStatus(status, el));
      });
      VuFind.emit("item-status-done");
    });
  }

  function itemStatusAjaxFailure(items, response, textStatus) {
    if (
      textStatus === "error" ||
      textStatus === "abort"
    ) {
      VuFind.emit("item-status-done");
      return;
    }

    response.json().then((body) => {
      // display the error message on each of the ajax status place holder
      items.forEach(function displayItemStatusFailure(item) {
        item.el.querySelectorAll(".callnumAndLocation").forEach((callNumAndLocation) => {
          callNumAndLocation.classList.add("text-danger");
          callNumAndLocation.innerHTML = "";
          callNumAndLocation.classList.remove("hidden");
          callNumAndLocation.innerHTML = typeof body.data === "string"
            ? body.data
            : VuFind.translate("error_occurred");
        });
      });
    }).finally(() => {
      VuFind.emit("item-status-done");
    });
  }

  function getStatusUrl(handlerName) {
    if (_handlerUrls[handlerName] !== undefined) {
      return _handlerUrls[handlerName];
    }
    return "/AJAX/JSON?method=getItemStatuses";
  }

  function getItemStatusPromise({
    handlerName = "ils",
    acceptType = "application/json",
    method = "POST",
  } = {}) {
    return function runFetchItem(items) {
      let body = new URLSearchParams();
      items.forEach((item) => {
        body.append("id[]", item.id);
      });
      body.append("sid", VuFind.getCurrentSearchId());
      return fetch(
        VuFind.path + getStatusUrl(handlerName),
        {
          method: method,
          headers: {
            'Accept': acceptType,
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: body
        }
      );
    };
  }

  function makeItemStatusQueue({
    handlerName = "ils",
    delay = 200,
  } = {}) {
    return new AjaxRequestQueue({
      run: getItemStatusPromise({handlerName: handlerName}),
      success: itemStatusAjaxSuccess,
      failure: itemStatusAjaxFailure,
      delay,
    });
  }

  function checkItemStatus(el) {
    const hiddenIdEl = el.querySelector(".hiddenId");

    if (
      hiddenIdEl === null ||
      el.classList.contains("js-item-pending") ||
      el.classList.contains("js-item-done")
    ) {
      return;
    }

    // update element to reflect lookup
    el.classList.add("js-item-pending");
    el.classList.remove("hidden");
    const callnumAndLocationEl = el.querySelector(".callnumAndLocation");
    if (callnumAndLocationEl) {
      callnumAndLocationEl.classList.remove("hidden");
    }
    el.querySelectorAll(".callnumAndLocation .ajax-availability").forEach(
      (ajaxEl) => ajaxEl.classList.remove("hidden")
    );

    const statusEl = el.querySelector(".status");
    if (statusEl) {
      statusEl.classList.remove("hidden");
    }

    // get proper handler
    let handlerName = "ils";
    if (el.dataset.handlerName) {
      handlerName = el.dataset.handlerName;
    } else {
      const handlerNameEl = el.querySelector(".handler-name");

      if (handlerNameEl !== null) {
        handlerName = handlerNameEl.value;
      }
    }

    // queue the element into the queue
    let payload = { el, id: hiddenIdEl.value };
    if (VuFind.config.get('item-status:load-batch-wise', true)) {
      _checkItemHandlers[handlerName].add(payload);
    } else {
      let runFunc = getItemStatusPromise({handlerName: handlerName});
      runFunc([payload])
        .then((...res) => itemStatusAjaxSuccess([payload], ...res))
        .catch((...error) => {
          console.error(...error);
          itemStatusAjaxFailure([payload], ...error);
        });
    }
  }

  function checkAllItemStatuses(container = document) {
    const records = container.querySelectorAll(".ajaxItem");

    if (records.length === 0) {
      VuFind.emit("item-status-done");
      return;
    }

    records.forEach(checkItemStatus);
  }

  function updateContainer(params) {
    let container = params.container;
    if (VuFind.isPrinting() || !(VuFind.config.get('item-status:load-observable-only', true))) {
      checkAllItemStatuses(container);
    } else {
      VuFind.observerManager.createIntersectionObserver(
        'itemStatuses',
        checkItemStatus,
        container.querySelectorAll('.ajaxItem')
      );
    }
  }

  function addHandler(handlerName, handlerUrl) {
    _checkItemHandlers[handlerName] = makeItemStatusQueue({handlerName: handlerName});
    _handlerUrls[handlerName] = handlerUrl;
  }

  function init() {
    _checkItemHandlers = {
      ils: makeItemStatusQueue()
    };
    addHandler("overdrive", "/Overdrive/getStatus");
    updateContainer({container: document});
    VuFind.listen('results-init', updateContainer);
  }

  return { init: init, addHandler: addHandler, check: checkAllItemStatuses, checkRecord: checkItemStatus };
});
