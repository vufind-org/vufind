/*global userIsLoggedIn, AjaxRequestQueue, VuFind */

VuFind.register("saveStatuses", function ItemStatuses() {
  /**
   * Display the save status for a single record element.
   * @param {Array<object>} itemLists Array of saved lists for the record.
   * @param {jQuery}        el        The record element.
   */
  function displaySaveStatus(itemLists, el) {
    const $item = $(el);

    if (itemLists.length > 0) {
      // If we got lists back, display them!
      var listEl = document.createElement("ul");
      listEl.append(...itemLists.map(function convertToLi(l) {
        const aEl = document.createElement("a");
        aEl.setAttribute("href", l.list_url);
        aEl.textContent = l.list_title;

        const liEl = document.createElement("li");
        liEl.append(aEl);
        return liEl;
      }));

      $item.find('.savedLists').addClass('loaded');
      $item.find('.js-load').replaceWith(listEl);
    } else {
      // If we got nothing back, remove the pending status:
      $item.find('.js-load').remove();
    }
    // No matter what, clear the flag that we have a pending save:
    $item.removeClass('js-save-pending');
  }

  /**
   * Success callback for the AJAX queue to iterate through items to display their save status.
   * @param {Array}  items    The items passed to the AjaxRequestQueue.
   * @param {object} response The AJAX response object. 
   */
  function checkSaveStatusSuccess(items, response) {
    items.forEach(function displayEachSaveStatus(item) {
      const key = item.source + "|" + item.id;

      if (typeof response.data.statuses[key] !== "undefined") {
        displaySaveStatus(response.data.statuses[key], item.el);
      }
    });

    VuFind.emit("save-status-done");
  }

  /**
   * Failure callback for the AJAX queue, displaying an error message or hiding the status element.
   * @param {Array}  items      The items passed to the AjaxRequestQueue
   * @param {object} response   The AJAX response object
   * @param {string} textStatus The status of the AJAX request
   */
  function checkSaveStatusFailure(items, response, textStatus) {
    if (
      textStatus === "abort" ||
      typeof response.responseJSON === "undefined"
    ) {
      items.forEach(function hideSaveStatus(item) {
        $(item.el).find(".savedLists").addClass("hidden");
      });

      VuFind.emit("save-status-done");

      return;
    }

    // display the error message on each of the ajax status place holder
    items.forEach(function displaySaveFailure(item) {
      $(item.el)
        .find(".savedLists")
        .addClass("alert-danger")
        .append(response.responseJSON.data);
    });

    VuFind.emit("save-status-done");
  }

  /**
   * Run the AJAX request to get save statuses for a batch of items.
   * @param {Array} items The array of items to request statuses for.
   * @returns {Promise} A promise for the AJAX request.
   */
  function runSaveAjaxQueue(items) {
    return new Promise(function runSaveAjaxPromise(done, error) {
      $.ajax({
        url: VuFind.path + "/AJAX/JSON?method=getSaveStatuses",
        data: {
          id: items.map((item) => item.id),
          source: items.map((item) => item.source),
        },
        dataType: "json",
        method: "POST",
      })
        .done(done)
        .catch(error);
    });
  }

  const saveStatusQueue = new AjaxRequestQueue({
    run: runSaveAjaxQueue,
    success: checkSaveStatusSuccess,
    failure: checkSaveStatusFailure,
  });

  /**
   * Check the save status of a single element record.
   * @param {HTMLElement} el The element to check save status of. 
   */
  function checkSaveStatus(el) {
    const savedListsEl = el.querySelector(".savedLists");
    if (!userIsLoggedIn || !savedListsEl) {
      VuFind.emit("save-status-done");

      return;
    }

    const hiddenIdEl = el.querySelector(".hiddenId");
    const hiddenSourceEl = el.querySelector(".hiddenSource");

    if (
      hiddenIdEl === null ||
      hiddenSourceEl === null ||
      el.classList.contains("js-save-pending")
    ) {
      return;
    }

    el.classList.add("js-save-pending");

    savedListsEl.classList.remove("loaded", "hidden");
    savedListsEl.innerHTML +=
      '<span class="js-load">' +
      VuFind.translate("loading_ellipsis") +
      "</span>";

    const ulEl = savedListsEl.querySelector("ul");
    if (ulEl !== null) {
      savedListsEl.removeChild(ulEl);
    }

    saveStatusQueue.add({
      id: hiddenIdEl.value,
      source: hiddenSourceEl.value,
      el,
    });
  }

  /**
   * Check the save status of all the records within a container
   * @param {HTMLElement} [container] The container element (default = document).
   */
  function checkAllSaveStatuses(container = document) {
    if (!userIsLoggedIn) {
      VuFind.emit("save-status-done");
      return;
    }

    const records = container.querySelectorAll(".result,.record");
    records.forEach(checkSaveStatus);

    VuFind.emit("save-status-done");
  }

  /**
   * Refresh the status of the all the records on the page.
   */
  function refresh() {
    // Make sure no event parameter etc. is passed to checkAllSaveStatuses()
    checkAllSaveStatuses();
  }

  /**
   * Update a container by checking save statuses.
   * @param {object} params An object containing the container element.
   */
  function updateContainer(params) {
    let container = params.container;
    if (VuFind.isPrinting()) {
      checkAllSaveStatuses(container);
    } else {
      VuFind.observerManager.createIntersectionObserver(
        'saveStatuses',
        checkSaveStatus,
        container.querySelectorAll(".result,.record")
      );
    }
  }

  /**
   * Initialize the module setting up initial checks and event listeners.
   */
  function init() {
    updateContainer({container: document});
    VuFind.listen('results-init', updateContainer);
  }

  return { init, refresh, check: checkAllSaveStatuses, checkRecord: checkSaveStatus };
});
