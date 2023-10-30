/*global VuFind, setupQRCodeLinks */
VuFind.combinedSearch = (function CombinedSearch() {
  function initResultScripts(container) {
    VuFind.openurl.init(container);
    VuFind.itemStatuses.init(container);
    VuFind.saveStatuses.init(container);
    setupQRCodeLinks(container);
    VuFind.recordVersions.init(container);
  }

  function init(container, url) {
    VuFind.loadHtml(container, url, '', function containerLoad(responseText) {
      if (responseText.length === 0) {
        container.hide();
      } else {
        initResultScripts(container);
      }
    });
  }

  var my = {
    init: init,
    initResultScripts: initResultScripts
  };

  return my;

})();
