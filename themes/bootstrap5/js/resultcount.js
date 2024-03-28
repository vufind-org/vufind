/* global VuFind */

VuFind.register('resultcount', function resultCount() {
  function init() {
    document.querySelectorAll('ul.nav-tabs [data-show-counts] a').forEach((tab) => {
      function setCount(count) {
        if (count >= 0) {
          tab.textContent += ' (' + count.toLocaleString() + ')';
        }
      }
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
