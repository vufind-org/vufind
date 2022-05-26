/*global Hunt, StatusAjaxQueue, VuFind */

VuFind.register("recordVersions", function recordVersions() {
  function checkVersionStatusSuccess(items, response) {
    items.forEach(function displayVersionStatus(item) {
      const key = item.source + "|" + item.id;

      if (typeof response.data[key] !== "undefined") {
        $(item.el).html(VuFind.updateCspNonce(response.data[key]));
      }
    });
  }

  function checkVersionStatusFailure(items, response, textStatus) {
    items.forEach(function displayVersionFailure(item) {
      item.el.innerHTML = VuFind.translate("error_occurred");
    });
  }

  function runVersionAjaxQueue(items) {
    return new Promise(function runVersionAjaxQueue(done, error) {
      $.getJSON(VuFind.path + "/AJAX/JSON", {
        method: "getRecordVersions",
        id: items.map((item) => item.id),
        source: items.map((item) => item.source),
      })
        .done(done)
        .fail(error);
    });
  }

  const versionQueue = new StatusAjaxQueue({
    run: runVersionAjaxQueue,
    success: checkVersionStatusSuccess,
    failure: checkVersionStatusFailure,
  });

  function checkRecordVersions(container = document) {
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

  function init(container = document) {
    if (typeof Hunt === "undefined" || VuFind.isPrinting()) {
      checkRecordVersions(container);
    } else {
      new Hunt(container.querySelectorAll(".record-versions.ajax"), {
        enter: checkRecordVersions,
      });
    }
  }

  return { init };
});
