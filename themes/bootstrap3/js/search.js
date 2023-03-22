/*global VuFind, checkSaveStatuses, setupQRCodeLinks */

VuFind.register('search', function search() {
  let paginationLinksSelector = '.js-ajax-pagination a';
  let recordListSelector = '.record-list';
  let scrollElementSelector = '.search-stats';
  let searchStatsSelector = '.js-search-stats';
  let searchControlFormSelector = '.search-controls form';
  let viewTypeSelector = '.view-buttons a';

  /**
   * Initialize result page scripts
   *
   * @param {string|JQuery} container
   */
  function initResultScripts(container) {
    VuFind.openurl.init($(container));
    VuFind.itemStatuses.init(container);
    checkSaveStatuses($(container));
    setupQRCodeLinks($(container));
    VuFind.recordVersions.init(container);
  }

  /**
   * Load results and update other associated elements
   *
   * @param {string} pageUrl
   * @param {string} addToHistory
   */
  function loadResults(pageUrl, addToHistory) {
    document.querySelector(scrollElementSelector).scrollIntoView();
    const recordList = document.querySelector(recordListSelector);
    recordList.innerHTML = VuFind.loading();
    const searchStats = document.querySelector(searchStatsSelector);
    const statsKey = searchStats.dataset.key;

    const backend = recordList.dataset.backend;
    let url = VuFind.path + '/AJAX/JSON?method=getSearchResults&source='
      + encodeURIComponent(backend) + '&statsKey=' + encodeURIComponent(statsKey);
    let pageUrlParts = pageUrl.split('?');
    if (typeof pageUrlParts[1] !== 'undefined') {
      url += '&querystring=' + encodeURIComponent(pageUrlParts[1]);
      if (addToHistory) {
        window.history.pushState({url: pageUrl}, '', '?' + pageUrlParts[1]);
      }
    }
    fetch(url)
      .then((response) => response.json())
      .then((result) => {
        // We expect to get the results list in elements, but reset it to hide spinner just in case:
        recordList.innerHTML = '';
        Object.entries(result.data.elements).forEach(([elementSelector, contents]) => {
          document.querySelectorAll(elementSelector).forEach((element) => {
            if (contents.target === 'inner') {
              element.innerHTML = contents.html;
            } else {
              element.outerHTML = contents.html;
            }
            element.setAttribute('aria-live', 'polite');
          });
        });
        initPagination();
      })
      .catch((error) => {
        let errorMsg = document.createElement('div');
        errorMsg.classList = 'alert alert-danger';
        errorMsg.textContent = VuFind.translate('error_occurred') + ' - ' + error;
        recordList.innerHTML = '';
        recordList.append(errorMsg);
      });
  }

  /**
   * Listener for changes in history state for loading appropriate results
   *
   * @param {Event} event
   */
  function historyStateListener(event) {
    if (event.state.url) {
      // TODO check that base address matches?
      event.preventDefault();
      loadResults(event.state.url, false);
    }
  }

  /**
   * Initialize AJAX pagination
   *
   * @returns {boolean}
   */
  function initPagination() {
    let active = false;
    document.querySelectorAll(paginationLinksSelector).forEach((element) => {
      if (!element.dataset.ajaxPagination) {
        element.dataset.ajaxPagination = true;
        active = true;
        element.addEventListener('click', function handleClick(event) {
          const href = this.getAttribute('href');
          loadResults(href, true);
          event.preventDefault();
        });
      }
    });
    if (active) {
      window.history.replaceState({url: window.location.href}, '', window.location.href);
      window.addEventListener('popstate', historyStateListener);
    }
    return active;
  }

  /**
   * Initialize result controls that are not refreshed via AJAX
   */
  function initResultControls() {
    document.querySelectorAll(searchControlFormSelector).forEach((form) => {
      if (!form.dataset.ajaxPagination) {
        form.dataset.ajaxPagination = true;
        form.querySelectorAll('.jumpMenu').forEach(jumpMenu => {
          // Disable original jump menu function:
          jumpMenu.classList.remove('jumpMenu');
          jumpMenu.addEventListener('change', function handleSubmit(event) {
            event.preventDefault();
            let query = [];
            Object.entries(form.elements).forEach(([, element]) => {
              query.push(element.name + '=' + encodeURIComponent(element.value));
            });
            let url = form.getAttribute('action');
            url += url.indexOf('?') !== -1 ? '&' : '?';
            url += query.join('&');
            console.debug('Form url: ' + url);
            loadResults(url, true);
          });
        });
      }
    });
  }

  /**
   * Initialize AJAX pagination if enabled
   */
  function init() {
    if (initPagination()) {
      initResultControls();
    }
  }

  return {
    init: init,
    initResultScripts: initResultScripts
  };
});
