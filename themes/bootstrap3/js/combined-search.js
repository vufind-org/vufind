/*global VuFind, checkItemStatuses, checkSaveStatuses */
VuFind.combinedSearch = (function CombinedSearch() {
  var setup = function setup(container, url) {
    container.load(url, '', function containerLoad(responseText) {
      if (responseText.length === 0) {
        container.hide();
      } else {
        VuFind.openurl.init(container);
        checkItemStatuses(container);
        checkSaveStatuses(container);
        VuFind.emit('vf-combined-ajax', container);
      }
    });
  };

  return {
    setup: setup
  };
})();
