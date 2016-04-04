/*global VuFind, setupOpenUrlLinks, checkItemStatuses, checkSaveStatuses */
VuFind.combinedSearch = (function() {
  var init = function(container, url) {
    container.load(url, '', function(responseText) {
      if (responseText.length == 0) {
        container.hide();
      } else {
        setupOpenUrlLinks(container);
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
