/* global VuFind */

VuFind.register('resultcount', function resultCount() {
  /**
   * Initialize the functionality to display item counts on tabs with the
   * `data-show-counts` attribute.
   */
  function init() {
    document.querySelectorAll('ul.nav-tabs [data-show-counts] a').forEach((tab) => {
      /**
       * Append the provided count to the tab's text.
       * @param {number} count The number of results to display.
       * @private
       */
      function setCount(count) {
        if (count >= 0) {
          tab.textContent += ' (' + count.toLocaleString() + ')';
        }
      }
      /**
       * Fetch the result count and set it on the tab.
       * @param {string} url The URL to use for the AJAX request.
       * @private
       */
      function loadCount(url) {
        if (url == null) {
          return;
        }
        let source = tab.dataset.source;
        let params = new URLSearchParams({
          method: 'getResultCount',
          querystring: url,
          source: source
        });
        fetch(VuFind.path + '/AJAX/JSON?' + params.toString())
          .then(response => response.json())
          .then(response => setCount(response.data.total));
      }

      if (tab.dataset.resultTotal) {
        setCount(parseInt(tab.dataset.resultTotal));
      } else {
        loadCount(tab.getAttribute('href') || tab.dataset.searchUrl);
      }
    });
  }
  return {
    init: init
  };
});
