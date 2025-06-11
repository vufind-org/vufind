/* global VuFind */

function waitForAjaxContentToLoad(fn) {
  var itemDone = typeof VuFind.itemStatuses === "undefined";
  var saveDone = typeof VuFind.saveStatuses === "undefined";
  var initiallyActiveTab = document.querySelector(".record-tab.initiallyActive");
  var tabDone = false;
  if (!initiallyActiveTab) {
    // No initially active tab? Then not on record page, so treat tab loading as done:
    tabDone = true;
  } else {
    // Check for a tab hash, which could impact which element we check for activity:
    var newTab = typeof window.location.hash !== 'undefined'
      ? window.location.hash.toLowerCase() : '';
    if (newTab.length <= 1 || newTab === '#tabnav') {
      // No tab hash, check for loading of the initial tab:
      tabDone = initiallyActiveTab.classList.contains("active");
    } else {
      // Tab hash, check that the specified tab is active (if valid):
      var hashTab = document.querySelector('.record-tabs .' + newTab.substring(1) + ' a');
      tabDone = !hashTab || hashTab.classList.contains("active");
    }
  }

  var fnCalled = false;
  function checkAllConditions() {
    if (!fnCalled && itemDone && saveDone && tabDone) {
      fn();
      fnCalled = true;
      return true;
    }
    return false;
  }

  if (checkAllConditions()) {
    return;
  }

  VuFind.listen("item-status-done", function listenForItemStatusDone() {
    itemDone = true;
    checkAllConditions();
  });

  VuFind.listen("save-status-done", function listenForSaveStatusDone() {
    saveDone = true;
    checkAllConditions();
  });

  VuFind.listen("record-tab-loaded", function listenForTabLoaded(eventData) {
    if (eventData.container && eventData.container.classList && eventData.container.classList.contains("active")) {
      tabDone = true;
    }
    checkAllConditions();
  });
}

VuFind.listen("ready", function triggerPrint() {
  if (!VuFind.isPrinting()) {
    return;
  }

  function defer(fn) {
    setTimeout(fn, 10);
  }

  waitForAjaxContentToLoad(function waitForAjax() {
    // Print dialogs cause problems during testing, so disable them
    // when the "test mode" cookie is set. This should never happen
    // under normal usage outside of the Phing startup process.
    if (document.cookie.indexOf('VuFindTestSuiteRunning=') > -1) {
      console.log("Printing disabled due to test mode."); // eslint-disable-line no-console
      return;
    }

    window.addEventListener(
      "afterprint",
      function doAfterPrint() {
        // Return to previous page after a minimal timeout. This is
        // done to avoid problems with some browsers, which fire the
        // afterprint event while the print dialog is still open.
        defer(function doGoBack() { history.back(); });
      },
      { once: true }
    );

    // Trigger print after a minimal timeout. This is done to avoid
    // problems with some browsers, which might not fully update
    // ajax loaded page content before showing the print dialog.
    defer(function doPrint() { window.print(); });
  });
});
