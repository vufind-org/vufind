/*global AjaxRequestQueue, VuFind */

VuFind.register("recordVersions", function recordVersions() {
  /**
   * Update elements with version status on a successful AJAX call.
   * @param {Array}  items    The items passed to the AjaxRequestQueue.
   * @param {object} response The AJAX response.
   */
  function checkVersionStatusSuccess(items, response) {
    items.forEach(function displayVersionStatus(item) {
      const key = item.source + "|" + item.id;

      if (typeof response.data.records[key] !== "undefined") {
        $(item.el).html(VuFind.updateCspNonce(response.data.records[key]));
      }
    });
  }

  /**
   * Update elements with an error message on a failed AJAX call.
   * @param {Array} items The items passed to the AjaxRequestQueue.
   */
  function checkVersionStatusFailure(items) {
    items.forEach(function displayVersionFailure(item) {
      item.el.textContent = VuFind.translate("error_occurred");
    });
  }

  /**
   * Run the AJAX request to get record versions for a batch of items.
   * @param {Array} items The array of items to request versions for.
   * @returns {Promise} A promise for the AJAX request.
   */
  function runVersionAjaxQueue(items) {
    return new Promise(function runVersionAjaxPromise(done, error) {
      $.getJSON(VuFind.path + "/AJAX/JSON", {
        method: "getRecordVersions",
        id: items.map((item) => item.id),
        source: items.map((item) => item.source),
        sid: VuFind.getCurrentSearchId(),
      })
        .done(done)
        .fail(error);
    });
  }

  const versionQueue = new AjaxRequestQueue({
    run: runVersionAjaxQueue,
    success: checkVersionStatusSuccess,
    failure: checkVersionStatusFailure,
  });

  /**
   * Check for and queue record version requests for elements within a container.
   * @param {Document|jQuery} _container The container to check for elements.
   */
  function checkRecordVersions(_container = document) {
    const container = $(_container);

    const elements =
      container.hasClass("record-versions") && container.hasClass("ajax")
        ? container
        : container.find(".record-versions.ajax");

    elements.each(function checkVersions() {
      const $elem = $(this);

      if ($elem.hasClass("loaded")) {
        return;
      }

      $elem.addClass("loaded");
      $elem.removeClass("hidden");
      $elem.append(
        '<span class="js-load">' +
          VuFind.translate("loading_ellipsis") +
          "</span>"
      );

      const $item = $(this).parents(".result");
      const id = $item.find(".hiddenId")[0].value;
      const source = $item.find(".hiddenSource")[0].value;

      versionQueue.add({ id, source, el: this });
    });
  }


  /**
   * Handle updating a container with version information.
   * @param {object} params An object containing the container element.
   */
  function updateContainer(params) {
    let container = params.container;
    if (VuFind.isPrinting()) {
      checkRecordVersions(container);
    } else {
      VuFind.observerManager.createIntersectionObserver(
        'recordVersions',
        checkRecordVersions,
        Array.from(container.querySelectorAll(".record-versions.ajax"))
      );
    }
  }

  /**
   * Initialize the record versions module.
   */
  function init() {
    updateContainer({container: document});
    VuFind.listen('results-init', updateContainer);
  }

  return { init };
});
