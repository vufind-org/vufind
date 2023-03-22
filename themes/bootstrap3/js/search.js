/*global VuFind */

VuFind.register('search', function search() {
  let jsRecordListSelector = '.js-record-list';
  let paginationLinksSelector = '.js-pagination a';
  let scrollElementSelector = '.search-stats';
  let searchStatsSelector = '.js-search-stats';
  let searchControlFormSelector = '.search-controls form';
  let sortFormSelector = searchControlFormSelector + '.search-sort';
  let sortFormLimitSelector = sortFormSelector + ' input[name=limit]';
  let limitFormSelector = searchControlFormSelector + '.search-result-limit';
  let limitFormSortSelector = searchControlFormSelector + ' input[name=sort]';
  let viewTypeSelector = '.view-buttons a';

  // Forward declaration
  let loadResults = function loadResultsForward() {};

  /**
   * Get the URL without any parameters
   *
   * @param {string} url
   *
   * @returns string
   */
  function getBaseUrl(url) {
    const parts = url.split('?');
    return parts[0];
  }

  /**
   * Initialize pagination.
   */
  function initPagination() {
    document.querySelectorAll(paginationLinksSelector).forEach((element) => {
      if (!element.dataset.ajaxPagination) {
        element.dataset.ajaxPagination = true;
        element.addEventListener('click', function handleClick(event) {
          event.preventDefault();
          const href = this.getAttribute('href');
          loadResults(href, true);
        });
      }
    });
  }

  /**
   * Initialize result controls that are not refreshed via AJAX.
   *
   * Note that view type links are updated in updateResultControls, but using them
   * will cause a reload since page contents may change.
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
            // Build a URL from form action and fields and load results:
            let urlParts = form.getAttribute('action').split('?', 2);
            const query = new URLSearchParams(urlParts.length > 1 ? urlParts[1] : '');
            Object.entries(form.elements).forEach(([, element]) => {
              if (element.name.endsWith('[]')) {
                query.append(element.name, element.value);
              } else {
                query.set(element.name, element.value);
              }
            });
            // Remove page so that any change resets it:
            query.delete('page');
            const url = urlParts[0] + '?' + query.toString();
            loadResults(url, true);
          });
        });
      }
    });
  }

  /**
   * Prepend a hidden field to a form.
   *
   * @param {?Element} form
   * @param {string} name
   * @param {string} value
   */
  function prependHiddenField(form, name, value) {
    if (!form) {
      return;
    }
    const input = document.createElement('input');
    input.type = "hidden";
    input.name = name;
    input.value = value;
    form.prepend(input);
  }

  /**
   * Handle a hidden field.
   *
   * Adds, updates or removes the field as necessary.
   *
   * @param {string} formSelector
   * @param {string} fieldName
   * @param {?Element} field
   * @param {?string} value
   */
  function handleHiddenField(formSelector, fieldName, field, value) {
    if (field) {
      if (value) {
        field.value = value;
      } else {
        field.remove();
      }
    } else if (value) {
      prependHiddenField(document.querySelector(formSelector), fieldName, value);
    }
  }

  /**
   * Update value of a select field
   *
   * @param {?Element} select
   * @param {?string} value
   */
  function updateSelectValue(select, value) {
    if (!select) {
      return;
    }
    if (select.value !== value) {
      if (value) {
        select.value = value;
      } else {
        const defaultValue = select.querySelector('option[data-default]');
        if (defaultValue) {
          select.value = defaultValue.value;
        }
      }
    }
  }

  /**
   * Update URLs of result controls (sort, limit, view type)
   *
   * We will deliberately avoid replacing the controls for accessibility, so we need
   * to ensure that they contain current URL parameters.
   *
   * @param {string} pageUrl
   */
  function updateResultControls(pageUrl) {
    const parts = pageUrl.split('?', 2);
    const params = new URLSearchParams(parts.length > 1 ? parts[1] : '');
    const sort = params.get('sort');
    const limit = params.get('limit');

    // Update hidden fields of forms:
    const limitField = document.querySelector(sortFormLimitSelector);
    handleHiddenField(sortFormSelector, 'limit', limitField, limit);
    const sortField = document.querySelector(limitFormSortSelector);
    handleHiddenField(limitFormSelector, 'sort', sortField, sort);

    // Update currently selected values:
    updateSelectValue(document.querySelector(sortFormSelector + ' select'), sort);
    updateSelectValue(document.querySelector(limitFormSelector + ' select'), limit);

    // Update view type links:
    document.querySelectorAll(viewTypeSelector).forEach((element) => {
      const url = element.getAttribute('href');
      const urlParts = url.split('?', 2);
      const urlParams = new URLSearchParams(urlParts.length > 1 ? urlParts[1] : '');
      if (sort) {
        urlParams.set('sort', sort);
      } else {
        urlParams.delete('sort');
      }
      if (limit) {
        urlParams.set('limit', limit);
      } else {
        urlParams.delete('limit');
      }
      element.setAttribute('href', urlParts[0] + '?' + urlParams.toString());
    });
  }

  /**
   * Load results and update associated elements.
   *
   * @param {string} pageUrl
   * @param {string} addToHistory
   */
  loadResults = function loadResultsReal(pageUrl, addToHistory) {
    const recordList = document.querySelector(jsRecordListSelector);
    const loadingOverlay = document.createElement('div');
    loadingOverlay.classList = 'loading-overlay';
    loadingOverlay.setAttribute('aria-live', 'polite');
    loadingOverlay.innerHTML = VuFind.loading();
    recordList.prepend(loadingOverlay);
    document.querySelector(scrollElementSelector).scrollIntoView({behavior: 'smooth'});
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
    updateResultControls(pageUrl);
    VuFind.emit('vf-results-load', {url: pageUrl, addToHistory: addToHistory});
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
        VuFind.initResultScripts(jsRecordListSelector);
        initPagination();
        VuFind.emit('vf-results-loaded', {url: pageUrl, addToHistory: addToHistory, data: result});
      })
      .catch((error) => {
        let errorMsg = document.createElement('div');
        errorMsg.classList = 'alert alert-danger';
        errorMsg.textContent = VuFind.translate('error_occurred') + ' - ' + error;
        recordList.innerHTML = '';
        recordList.append(errorMsg);
      });
  };

  /**
   * Handle history state change event and load results accordingly.
   *
   * @param {Event} event
   */
  function historyStateListener(event) {
    if (event.state.url && getBaseUrl(window.location.href) === getBaseUrl(event.state.url)) {
      event.preventDefault();
      loadResults(event.state.url, false);
    }
  }

  /**
   * Initialize JS pagination if enabled
   */
  function init() {
    if (document.querySelector(jsRecordListSelector)) {
      initPagination();
      initResultControls();
      window.history.replaceState({url: window.location.href}, '', window.location.href);
      window.addEventListener('popstate', historyStateListener);
    }
  }

  return {
    init: init
  };
});
