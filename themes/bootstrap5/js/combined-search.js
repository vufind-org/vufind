/*global VuFind */
VuFind.combinedSearch = (function CombinedSearch() {

  function init(container, url) {
    VuFind.loadHtml(container, url, '', function containerLoad(responseText) {
      if (responseText.length === 0) {
        container.hide();
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
