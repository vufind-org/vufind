/*global VuFind */
VuFind.combinedSearch = (function CombinedSearch() {
  /**
   * Initialize the combined search functionality
   * @param {string|jQuery} container The container element or a selector for it.
   * @param {string}        url       The URL to fetch the combined search results from.
   */
  function init(container, url) {
    VuFind.loadHtml(container, url, '', function containerLoad(responseText) {
      if (!responseText || responseText.length === 0) {
        var element = typeof container === 'string' ? document.querySelector(container) : container;
        if (element) {
          element.style.display = "none";
          let parent = element.parentNode;
          while (parent && parent.classList.contains('js-hide-if-empty')) {
            parent.style.display = "none";
            parent = parent.parentNode;
          }
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
