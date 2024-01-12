/* global VuFind */

VuFind.register('resultcount', function resultCount() {
  function init() {
    document.querySelectorAll('ul.nav-tabs [data-show-counts] a').forEach((tab) => {
      function loadCount(url) {
        let source = tab.dataset.source;
        let xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function appendResultCount() {
          if (this.readyState === 4 && this.status === 200) {
            let response = JSON.parse(xhttp.responseText);
            tab.textContent += ' (' + response.data.total.toLocaleString() + ')';
          }
        };
        let params = new URLSearchParams({
          method: 'getResultCount',
          querystring: url,
          source: source
        });
        xhttp.open('GET', VuFind.path + '/AJAX/JSON?' + params.toString());
        xhttp.setRequestHeader('Content-type', 'application/json');
        xhttp.send();
      }
      if (tab.getAttribute('href')) {
        loadCount(tab.getAttribute('href'));
      } else if (tab.dataset.searchUrl) {
        loadCount(tab.dataset.searchUrl);
      }
    });
  }
  return {
    init: init
  };
});
