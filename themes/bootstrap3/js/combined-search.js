/*global VuFind */
VuFind.combinedSearch = (function CombinedSearch() {

  function init(container, url) {
    VuFind.loadHtml(container, url, '', function containerLoad(responseText) {
      if (!responseText || responseText.length === 0) {
        if (container.style) {
          container.style.display = "none";
        }
        let parent = container.parentNode;
        while (parent && parent.classList.contains('js-hide-if-empty')) {
          if (parent.style) {
            parent.style.display = "none";
          }
          parent = parent.parentNode;
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
