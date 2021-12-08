/* global VuFind */

$(document).ready(function triggerPrint() {
  var url = window.location.href;
  if (url.indexOf('?print=') !== -1 || url.indexOf('&print=') !== -1) {
    $(document).one('ajaxStop', function doTriggerPrint() {
      // Print dialogs cause problems during testing, so disable them
      // when the "test mode" cookie is set. This should never happen
      // under normal usage outside of the Phing startup process.
      if (document.cookie.indexOf('VuFindTestSuiteRunning=') === -1) {
        window.addEventListener(
          "afterprint",
          function doAfterPrint() {
            // Return to previous page after a minimal timeout. This is
            // done to avoid problems with some browsers, which fire the
            // afterprint event while the print dialog is still open.
            setTimeout(function doGoBack() { history.back(); }, 10);
          },
          { once: true }
        );
        // Trigger print after a minimal timeout. This is done to avoid
        // problems with some browsers, which might not fully update
        // ajax loaded page content before showing the print dialog.
        setTimeout(function doPrint() { window.print(); }, 10);
      } else {
        console.log("Printing disabled due to test mode."); // eslint-disable-line no-console
      }
    });
    // Make an ajax call to ensure that ajaxStop is triggered
    $.getJSON(VuFind.path + '/AJAX/JSON', {method: 'keepAlive'});
  }
});
