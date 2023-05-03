/*global AjaxRequestQueue, VuFind, unwrapJQuery */

VuFind.register('itemStatuses', function ItemStatuses() {
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
    const $item = $(el); // todo: remove jQuery

    $item.addClass('js-item-done').removeClass('js-item-pending');
    $item.find('.status').empty().append(result.availability_message);
    $item.find('.ajax-availability').removeClass('ajax-availability hidden');
    if (typeof(result.error) != 'undefined'
          && result.error.length > 0
    ) {
      $item.find('.callnumAndLocation').empty().addClass('text-danger').append(result.error);
      $item.find('.callnumber,.hideIfDetailed,.location').addClass('hidden');
    } else if (typeof(result.full_status) != 'undefined'
          && result.full_status.length > 0
          && $item.find('.callnumAndLocation').length > 0
    ) {
      // Full status mode is on -- display the HTML and hide extraneous junk:
      $item.find('.callnumAndLocation').empty().append(VuFind.updateCspNonce(result.full_status));
      $item.find('.callnumber,.hideIfDetailed,.location,.status').addClass('hidden');
    } else if (typeof(result.missing_data) !== 'undefined'
          && result.missing_data
    ) {
      // No data is available -- hide the entire status area:
      $item.find('.callnumAndLocation,.status').addClass('hidden');
    } else if (result.locationList) {
      // We have multiple locations -- build appropriate HTML and hide unwanted labels:
      $item.find('.callnumber,.hideIfDetailed,.location').addClass('hidden');
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
      $item.find('.locationDetails').removeClass('hidden');
      $item.find('.locationDetails').html(locationListHTML);
    } else {
      // Default case -- load call number and location into appropriate containers:
      $item.find('.callnumber').empty().append(formatCallnumbers(result.callnumber, result.callnumber_handler) + '<br/>');
      $item.find('.location').empty().append(
        result.reserve === 'true'
          ? result.reserve_message
          : result.location
      );
    }
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
    response.data.statuses.forEach(function displayItemStatusResponse(status) {
      if (typeof idMap[status.id] === "undefined") {
        return;
      }

      idMap[status.id].forEach((el) => displayItemStatus(status, el));
    });

    VuFind.emit("item-status-done");
  }

  function itemStatusAjaxFailure(items, response, textStatus) {
    if (
      textStatus === "error" ||
      textStatus === "abort" ||
      typeof response.responseJSON === "undefined"
    ) {
      VuFind.emit("item-status-done");

      return;
    }

    // display the error message on each of the ajax status place holder
    items.forEach(function displayItemStatusFailure(item) {
      $(item.el)
        .find(".callnumAndLocation")
        .addClass("text-danger")
        .empty()
        .removeClass("hidden")
        .append(
          typeof response.responseJSON.data === "string"
            ? response.responseJSON.data
            : VuFind.translate("error_occurred")
        );
    });

    VuFind.emit("item-status-done");
  }

  function makeItemStatusQueue({
    url = "/AJAX/JSON?method=getItemStatuses",
    dataType = "json",
    method = "POST",
    delay = 200,
  } = {}) {
    return new AjaxRequestQueue({
      run: function runItemAjaxQueue(items) {
        return new Promise(function runItemAjaxPromise(done, error) {
          const sid = VuFind.getCurrentSearchId();

          $.ajax({
            // todo: replace with fetch
            url: VuFind.path + url,
            data: { id: items.map((item) => item.id), sid },
            dataType,
            method,
          })
            .done(done)
            .catch(error);
        });
      },
      success: itemStatusAjaxSuccess,
      failure: itemStatusAjaxFailure,
      delay,
    });
  }

  //store the handlers in a "hash" obj
  var checkItemHandlers = {
    ils: makeItemStatusQueue(),
    overdrive: makeItemStatusQueue({ url: "/Overdrive/getStatus" }),
  };

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
    checkItemHandlers[handlerName].add({ el, id: hiddenIdEl.value });
  }

  function checkAllItemStatuses(container = document) {
    container.querySelectorAll(".ajaxItem").forEach(checkItemStatus);
  }

  function init($container = document) {
    const container = unwrapJQuery($container);

    if (VuFind.isPrinting()) {
      checkAllItemStatuses(container);
    } else {
      VuFind.observerManager.createIntersectionObserver(
        'itemStatuses',
        checkItemStatus,
        container.querySelectorAll('.ajaxItem')
      );
    }
  }

  return { init: init, check: checkAllItemStatuses, checkRecord: checkItemStatus };
});
