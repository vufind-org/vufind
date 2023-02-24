/*global AjaxRequestQueue, VuFind */

VuFind.register("recordVersions", function recordVersions() {
  function checkVersionStatusSuccess(items, response) {
    items.forEach(function displayVersionStatus(item) {
      const key = item.source + "|" + item.id;

      if (typeof response.data[key] !== "undefined") {
        $(item.el).html(VuFind.updateCspNonce(response.data[key]));
      }
<<<<<<< HEAD
=======
      $elem.addClass('loaded');
      $elem.removeClass('hidden');
      $elem.append('<span class="js-load">' + VuFind.translate('loading_ellipsis') + '</span>');
      var $item = $(this).parents('.result');
      var id = $item.find('.hiddenId')[0].value;
      var source = $item.find('.hiddenSource')[0].value;
      $.getJSON(
        VuFind.path + '/AJAX/JSON',
        {
          method: 'getRecordVersions',
          id: id,
          source: source,
          sid: VuFind.getCurrentSearchId()
        }
      )
        .done(function onGetVersionsDone(response) {
          if (response.data.length > 0) {
            $elem.html(VuFind.updateCspNonce(response.data));
          } else {
            $elem.text('');
          }
        })
        .fail(function onGetVersionsFail() {
          $elem.text(VuFind.translate('error_occurred'));
        });
>>>>>>> origin/dev
    });
  }

  function checkVersionStatusFailure(items) {
    items.forEach(function displayVersionFailure(item) {
      item.el.innerHTML = VuFind.translate("error_occurred");
    });
  }

  function runVersionAjaxQueue(items) {
    return new Promise(function runVersionAjaxPromise(done, error) {
      $.getJSON(VuFind.path + "/AJAX/JSON", {
        method: "getRecordVersions",
        id: items.records.map((item) => item.id),
        source: items.records.map((item) => item.source),
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

  function init($container = document) {
    const container = $container instanceof Node ? $container : $container[0];

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

  return { init };
});
