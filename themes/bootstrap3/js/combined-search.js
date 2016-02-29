/*global VuFind, setupOpenUrlLinks, checkSaveStatuses, setupSaveRecordLinks */
VuFind.combinedSearch = (function() {
  var init = function(container, url) {
    container.load(url, '', function(responseText) {
      if (responseText.length == 0) {
        container.hide();
      } else {
        setupOpenUrlLinks(container);
        checkSaveStatuses(container);
        setupSaveRecordLinks(container);
      }
    });
  };
  
  var my = {
    init: init
  };

  return my;

})(VuFind);
    