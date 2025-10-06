/*global finna, VuFind*/
/**
 * Module for finna-js-record-container component
 * Exposes functions:
 * - loadContents
 */
finna.jsRecordContainer = (() => {
  /**
   * Load contents to a record specific container i.e similar or record driver related
   * @param {string} selector String selector for element to load contents for
   */
  function loadContents(selector)
  {
    const element = document.querySelector(selector);
    if (!element) {
      return;
    }
    const method = element.dataset.method;
    if (method) {
      const urlParams = {
        id: element.dataset.recordId,
        method: method,
      };
      const dataSource = element.dataset.source;
      if (dataSource) {
        urlParams.source = dataSource;
      }
      fetch(VuFind.path + '/AJAX/JSON?' + new URLSearchParams(urlParams))
        .then(response => response.json())
        .then(result => {
          if (result.data && result.data.html) {
            VuFind.setInnerHtml(element, VuFind.updateCspNonce(result.data.html));
            element.classList.add('initialized');
          }
        });
    }
  }
  return {
    loadContents
  };
})();
