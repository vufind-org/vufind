/*global VuFind */

VuFind.register('search', function search() {
  let jsRecordListSelector = '.js-result-list';
  let paginationLinksSelector = '.js-pagination a,.js-pagination-simple a';
  let scrollElementSelector = '.search-stats';
  let searchStatsSelector = '.js-search-stats';
  let searchFormSelector = 'form.searchForm';
  let resultsControlFormSelector = '.search-controls form';
  let sortFormSelector = resultsControlFormSelector + '.search-sort';
  let limitFormSelector = resultsControlFormSelector + '.search-result-limit';
  let viewTypeSelector = '.view-buttons a';
  let selectAllSelector = '.checkbox-select-all';

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
    document.querySelectorAll(resultsControlFormSelector).forEach((form) => {
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

            function _addToQuery(el) {
              if ('radio' === el.type && !el.checked) {
                return;
              }
              if (el.name.endsWith('[]')) {
                query.append(el.name, el.value);
              } else {
                query.set(el.name, el.value);
              }
            }

            Object.entries(form.elements).forEach(([, element]) => {
              // Chrome represents multiple hidden 'filter[]' fields as a RadioNodeList:
              if (element instanceof RadioNodeList) {
                Object.entries(element).forEach(([, setElement]) => {
                  _addToQuery(setElement);
                });
              } else {
                _addToQuery(element);
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
   * @param {?string} value
   */
  function handleHiddenField(formSelector, fieldName, value) {
    let form = document.querySelector(formSelector);
    if (!form) {
      return;
    }
    let field = form.querySelector("input[name=" + fieldName + "]");
    if (field) {
      if (value) {
        field.value = value;
      } else {
        field.remove();
      }
    } else if (value) {
      prependHiddenField(form, fieldName, value);
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

    // Update hidden fields of the search form:
    handleHiddenField(searchFormSelector, 'limit', limit);
    handleHiddenField(searchFormSelector, 'sort', sort);

    // Update hidden fields of search control forms:
    handleHiddenField(sortFormSelector, 'limit', limit);
    handleHiddenField(limitFormSelector, 'sort', sort);

    // Update currently selected values (required for history traversal to show correct values):
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

    // Reset "select all" checkbox:
    document.querySelectorAll(selectAllSelector).forEach((el) => el.checked = false);
  }

  /**
   * Update URLs of links pointing to the same results page
   *
   * Updates links pointing to this page to ensure that they contain current URL
   * parameters (e.g. sort and limit). Any link that only contains query parameters
   * is considered.
   *
   * @param {string} pageUrl
   */
  function updateResultLinks(pageUrl) {
    let urlParts = pageUrl.split('?', 2);
    const params = new URLSearchParams(urlParts.length > 1 ? urlParts[1] : '');
    const sort = params.get('sort');
    const limit = params.get('limit');

    document.querySelectorAll('a').forEach((a) => {
      // Use original href attribute since a.href returns an absolute URL:
      const href = a.getAttribute('href');
      if (!href || !href.startsWith('?')) {
        return true;
      }
      const hrefParams = new URLSearchParams(href.substr(1));
      if (null === sort) {
        hrefParams.delete('sort');
      } else {
        hrefParams.set('sort', sort);
      }
      if (null === limit) {
        hrefParams.delete('limit');
      } else {
        hrefParams.set('limit', limit);
      }
      a.href = '?' + hrefParams.toString();
    });
  }

  /**
   * Scroll view port to results
   *
   * @param {string} _style Scroll behavior ('smooth' (default), 'instant' or 'auto')
   */
  function scrollToResults(_style) {
    const scrollEl = document.querySelector(scrollElementSelector);
    if (scrollEl && window.scrollY > scrollEl.offsetTop) {
      const style = typeof _style !== 'undefined' ? _style : 'smooth';
      scrollEl.scrollIntoView({behavior: style});
    }
  }

  /**
   * Show an error message
   *
   * @param {string} error
   */
  function showError(error) {
    let errorMsg = document.createElement('div');
    errorMsg.classList = 'alert alert-danger';
    errorMsg.textContent = error;
    const recordList = document.querySelector(jsRecordListSelector);
    recordList.replaceChildren(errorMsg);
  }

  /**
   * Load results and update associated elements.
   *
   * @param {string} pageUrl
   * @param {string} addToHistory
   */
  loadResults = function loadResultsReal(pageUrl, addToHistory) {
    const recordList = document.querySelector(jsRecordListSelector);
    const backend = recordList.dataset.backend;
    if (typeof backend === 'undefined') {
      showError('ERROR: data-backend not set for record list');
      return;
    }
    if (recordList.classList.contains('loading')) {
      return;
    }
    recordList.classList.add('loading');
    const history = recordList.dataset.history;

    const loadingOverlay = document.createElement('div');
    loadingOverlay.classList = 'loading-overlay';
    loadingOverlay.setAttribute('aria-live', 'polite');
    loadingOverlay.setAttribute('role', 'status');
    loadingOverlay.append(VuFind.loadingElement());
    recordList.prepend(loadingOverlay);
    scrollToResults();
    const searchStats = document.querySelector(searchStatsSelector);
    const statsKey = searchStats.dataset.key;

    const queryParams = new URLSearchParams('method=getSearchResults');
    queryParams.set('source', backend);
    if (typeof history !== 'undefined') {
      queryParams.set('history', history);
    }
    queryParams.set('statsKey', statsKey);
    let pageUrlParts = pageUrl.split('?');
    if (typeof pageUrlParts[1] !== 'undefined') {
      queryParams.set('querystring', pageUrlParts[1]);
      if (addToHistory) {
        window.history.pushState(
          {
            url: getBaseUrl(window.location.href) + '?' + pageUrlParts[1]
          },
          '',
          '?' + pageUrlParts[1]
        );
      }
    }
    updateResultControls(pageUrl);
    updateResultLinks(pageUrl);

    VuFind.emit('results-load', {
      url: pageUrl,
      addToHistory: addToHistory
    });

    fetch(VuFind.path + '/AJAX/JSON?' + queryParams.toString())
      .then((response) => response.json())
      .then((result) => {
        if (result.error) {
          throw result.error;
        }
        // We expect to get the results list in elements, but reset it to hide spinner just in case:
        recordList.textContent = '';
        Object.entries(result.data.elements).forEach(([elementSelector, contents]) => {
          document.querySelectorAll(elementSelector).forEach((element) => {
            VuFind.setElementContents(
              element,
              contents.content,
              contents.attrs,
              contents.target ? (contents.target + 'HTML') : ''
            );
          });
        });
        VuFind.initResultScripts(jsRecordListSelector);
        initPagination();

        VuFind.emit('results-loaded', {
          url: pageUrl,
          addToHistory: addToHistory,
          data: result
        });

        recordList.classList.remove('loading');
      })
      .catch((error) => {
        showError(VuFind.translate('error_occurred') + ' - ' + error);
        recordList.classList.remove('loading');
      });
  };

  /**
   * Handle history state change event and load results accordingly.
   *
   * @param {Event} event
   */
  function historyStateListener(event) {
    if (event.state && event.state.url && getBaseUrl(window.location.href) === getBaseUrl(event.state.url)) {
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
    init: init,
    scrollToResults
  };
});
