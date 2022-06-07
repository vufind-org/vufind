/* global VuFind */

function waitForItemStatuses(fn) {
  var itemDone = false;
  var saveDone = false;

  function checkBoth() {
    if (itemDone && saveDone) {
      fn();
    }
  }

  VuFind.listen("item-status-done", function listenForItemStatusDone() {
    itemDone = true;
    checkBoth();
  });

  VuFind.listen("save-status-done", function listenForSaveStatusDone() {
    saveDone = true;
    checkBoth();
  });
}

$(document).ready(function triggerPrint() {
  if (!VuFind.isPrinting()) {
    return;
  }

  function defer(fn) {
    setTimeout(fn, 10);
  }

  waitForItemStatuses(function waitForAjax() {
    $(document).one('ajaxStop', function doTriggerPrint() {
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

    // Make an ajax call to ensure that ajaxStop is triggered
    $.getJSON(VuFind.path + '/AJAX/JSON', {method: 'keepAlive'});
  });
});
