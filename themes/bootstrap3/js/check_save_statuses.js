/*global htmlEncode, userIsLoggedIn, StatusAjaxQueue, VuFind */

VuFind.register("saveStatuses", function ItemStatuses() {
  function displaySaveStatus(itemLists, el) {
    const $item = $(el);

    if (itemLists.length > 0) {
      // If we got lists back, display them!
      var html = '<ul>' + itemLists.map(function convertToLi(l) {
        return '<li><a href="' + l.list_url + '">' + htmlEncode(l.list_title) + '</a></li>';
      }).join('') + '</ul>';
      $item.find('.savedLists').addClass('loaded');
      $item.find('.js-load').replaceWith(html);
    } else {
      // If we got nothing back, remove the pending status:
      $item.find('.js-load').remove();
    }
    // No matter what, clear the flag that we have a pending save:
    $item.removeClass('js-save-pending');
  }

  function checkSaveStatusSuccess(items, response) {
    items.forEach(function displayEachSaveStatus(item) {
      const key = item.source + "|" + item.id;

      if (typeof response.data.statuses[key] !== "undefined") {
        displaySaveStatus(response.data.statuses[key], item.el);
      }
    });

    VuFind.emit("save-status-done");
  }

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

  const saveStatusQueue = new StatusAjaxQueue({
    run: runSaveAjaxQueue,
    success: checkSaveStatusSuccess,
    failure: checkSaveStatusFailure,
  });

  function checkSaveStatus(el) {
    if (!userIsLoggedIn) {
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

    const savedListsEl = el.querySelector(".savedLists");
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

  function checkAllSaveStatuses(container = document) {
    if (!userIsLoggedIn) {
      return;
    }

    container.querySelectorAll(".result,.record").forEach(checkSaveStatus);
  }

  function refresh() {
    // Make sure no event parameter etc. is passed to checkAllSaveStatuses()
    checkAllSaveStatuses();
  }

  function init($container = document) {
    const container = $container instanceof Node ? $container : $container[0];

    if (VuFind.isPrinting()) {
      checkAllSaveStatuses(container);
    } else {
      VuFind.observerManager.createIntersectionObserver(
        'saveStatuses',
        checkSaveStatus,
        Array.from(container.querySelectorAll(".result,.record"))
      );
    }
  }

  return { init, refresh };
});
