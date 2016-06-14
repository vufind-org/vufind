/*global VuFind, checkItemStatuses, checkSaveStatuses */
VuFind.combinedSearch = (function CombinedSearch() {
  var init = function init(container, url) {
    container.load(url, '', function containerLoad(responseText) {
      if (responseText.length === 0) {
        container.hide();
      } else {
        VuFind.openurl.init(container);
        checkItemStatuses(container);
        checkSaveStatuses(container);
      }
    });
  };

  var my = {
    init: init
  };

  return my;

})();
