/*global VuFind */
VuFind.combinedSearch = (function CombinedSearch() {

  function init(container, url) {
    VuFind.loadHtml(container, url, '', function containerLoad(responseText) {
      if (responseText.length === 0) {
        container.hide();
        container = container.parent();
        while (container.hasClass('hide-if-empty')) {
          container.hide();
          container = container.parent();
        }
      } else {
        VuFind.initResultScripts(container);
      }
    });
  }

  var my = {
    init: init
  };

  return my;

})();
