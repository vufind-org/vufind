/*global VuFind, multiFacetsSelection, unwrapJQuery */

/**
 * Get the globally-configured multi-facets selection setting (or default to 'false').
 * @returns {string} The multi-facets selection setting
 */
const getMultiFacetsSelectionSetting = () => {
  return typeof multiFacetsSelection === 'undefined' ? 'false' : multiFacetsSelection;
};

/**
 * Returns whether multi-facets selection is enabled.
 * @returns {boolean} Return true if multi-facets selection is enabled
 */
const isMultiFacetsSelectionEnabled = () => {
  return getMultiFacetsSelectionSetting() !== 'false';
};

/**
 * Get the default checkbox selection state to apply if overriding user state is not found.
 * @returns {boolean} Return true if the checkbox should be checked by default.
 */
const getMultiFacetsSelectionPageLoadValue = () => {
  const setting = getMultiFacetsSelectionSetting();
  return setting === 'always' || setting === 'checked';
};

/* --- Facet List --- */
VuFind.register('facetList', function FacetList() {
  /**
   * Get the current value from the facet filter input.
   * @returns {string|null} The current filter value, or null if the element doesn't exist.
   */
  function getCurrentContainsValue() {
    const containsEl = document.querySelector('.ajax_param[data-name="contains"]');
    return containsEl ? containsEl.value : null;
  }

  /**
   * Set the value of the facet filter input.
   * @param {string} val The value to set.
   */
  function setCurrentContainsValue(val) {
    const containsEl = document.querySelector('.ajax_param[data-name="contains"]');
    if (containsEl) {
      containsEl.value = val;
    }
  }

  /**
   * Override the "href" attribute of a set of elements with new URL parameters.
   * @param {string} selector         The CSS selector for the elements.
   * @param {object} [overrideParams] An object of key-value pairs to add to the URL (default = {}).
   */
  function overrideHref(selector, overrideParams = {}) {
    $(selector).each(function overrideHrefEach() {
      const dummyDomain = 'https://www.example.org'; // we need this since the URL class cannot parse relative URLs
      const url = new URL(dummyDomain + $(this).attr('href'));
      Object.entries(overrideParams).forEach(([key, value]) => {
        url.searchParams.set(key, value);
      });
      $(this).attr('href', url.href.replaceAll(dummyDomain, ''));
    });
  }

  /**
   * Update the href attribute to include the current facet filter value.
   */
  function updateHrefContains() {
    const overrideParams = { contains: getCurrentContainsValue() };
    overrideHref('.js-facet-sort', overrideParams);
    overrideHref('.js-facet-next-page', overrideParams);
    overrideHref('.js-facet-prev-page', overrideParams);
  }

  /**
   * Fetch facet list HTML content via an AJAX call.
   * @param {object} [overrideParams] An object of key-value pairs to add as URL parameters for the request (default = {}).
   * @returns {Promise} A promise that resolves with the AJAX request object.
   */
  function getContent(overrideParams = {}) {
    const ajaxParams = $('.ajax_params').data('params');
    let url = ajaxParams.urlBase;

    for (let [key, val] of Object.entries(ajaxParams)) {
      if (key in overrideParams) {
        val = overrideParams[key];
      }
      url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(val);
    }

    const contains = getCurrentContainsValue();
    if (contains) {
      url += '&contains=' + encodeURIComponent(contains);
    }

    if (!("facetsort" in overrideParams)) {
      const sort = $('.js-facet-sort.active').data('sort');
      if (sort !== undefined) {
        url += '&facetsort=' + encodeURIComponent(sort);
      }
    }

    url += '&ajax=1';

    return Promise.resolve($.ajax({
      url: url
    }));
  }

  /**
   * Update the content of the facet list by making an AJAX call and rendering the result.
   * @param {object} [overrideParams] An object of key-value pairs to add as URL parameters for the request (default = {}).
   */
  function updateContent(overrideParams = {}) {
    $('#facet-info-result').html(VuFind.loading());
    getContent(overrideParams).then(html => {
      let htmlList = '';
      $(VuFind.updateCspNonce(html)).find('.full-facet-list').each(function itemEach() {
        htmlList += $(this).prop('outerHTML');
      });
      $('#facet-info-result').html(htmlList);
      updateHrefContains();
      VuFind.lightbox_facets.setup();
    });
  }

  // Useful function to delay callbacks, e.g. when using a keyup event
  // to detect when the user stops typing.
  // See also: https://stackoverflow.com/questions/1909441/how-to-delay-the-keyup-handler-until-the-user-stops-typing
  var inputCallbackTimeout = null;
  /**
   * Register event listeners for the facet list, including filtering and sorting.
   */
  function registerCallbacks() {
    $('.facet-lightbox-filter').removeClass('hidden');

    $('.ajax_param[data-name="contains"]').on('input', function onInputChangeFacetList(event) {
      clearTimeout(inputCallbackTimeout);
      if (event.target.value.length < 1) {
        $('#btn-reset-contains').addClass('hidden');
      } else {
        $('#btn-reset-contains').removeClass('hidden');
      }
      inputCallbackTimeout = setTimeout(function onInputTimeout() {
        updateContent({ facetpage: 1 });
      }, 500);
    });

    $('#btn-reset-contains').on('click', function onResetClick() {
      setCurrentContainsValue('');
      $('#btn-reset-contains').addClass('hidden');
      updateContent({ facetpage: 1 });
    });
  }

  /**
   * Set up the facet list functionality.
   */
  function setup() {
    if ($.isReady) {
      registerCallbacks();
    } else {
      $(function ready() {
        registerCallbacks();
      });
    }
  }

  return { setup: setup, getContent: getContent, updateContent: updateContent };
});

/* --- Multi Facets Handling --- */
VuFind.register('multiFacetsSelection', function multiFacetsSelection() {
  const globalAddedParams = new URLSearchParams();
  const globalRemovedParams = new URLSearchParams();
  const initialParams = new URLSearchParams();
  const rangeSelectorForms = [];
  let isMultiFacetsSelectionActivated = false;
  let callbackOnApply;
  let callbackWhenDeactivated;
  let defaultContext;
  let defaultCountText;
  // Events to emit
  const activation_event = 'facet-selection-begin';
  const deactivation_event = 'facet-selection-cancel';
  const apply_event = 'facet-selection-done';
  const local_storage_variable_name = 'multi-facets-selection';

  /**
   * Normalize a filter value
   * @param {string} key   Parameter name
   * @param {string} value Value
   * @returns {string} The normalized value
   */
  function normalizeValue(key, value) {
    if (key !== 'filter[]') {
      return value;
    }
    const p = value.indexOf(':');
    if (p < 0) {
      return value;
    }
    // Ensure that filter value is surrounded by quotes
    let filterValue = value.substring(p + 1);
    filterValue = (!filterValue.startsWith('"') ? '"' : '') + filterValue + (!filterValue.endsWith('"') ? '"' : '');
    return value.substring(0, p) + ':' + filterValue;
  }

  /**
   * Normalize a single query key from a search
   * @param {string} key Key name
   * @returns {string} The normalized query
   */
  function normalizeSearchQueryKey(key) {
    // We normally use open-ended brackets to signify array-based query parameters.
    // However, some server-side processing can occasionally inject explicit index
    // values. We want to normalize out the index values for consistency.
    return key.replace(/(.+)\[\d+\]/, '$1[]');
  }

  /**
   * Append a normalized value to a normalized key in a set of parameters
   * @param {URLSearchParams} params Parameters to update
   * @param {string}          key    Key name
   * @param {string}          value  Value to set
   */
  function appendNormalizedValue(params, key, value) {
    const normalizedKey = normalizeSearchQueryKey(key);
    params.append(normalizedKey, normalizeValue(normalizedKey, value));
  }

  /**
   * Normalize keys and values in a set of search parameters
   * @param {URLSearchParams} params Parameters to normalize
   * @returns {URLSearchParams} A new object with normalized keys and values.
   */
  function normalizeSearchQueryKeysAndValues(params) {
    const normalized = new URLSearchParams();
    for (const [key, value] of params) {
      appendNormalizedValue(normalized, key, value);
    }
    return normalized;
  }

  for (const [key, value] of (new URLSearchParams(window.location.search))) {
    appendNormalizedValue(initialParams, key, value);
  }

  /**
   * Update query params for every date range selector
   * @param {URLSearchParams} queryParams The current query parameters.
   * @returns {URLSearchParams} A new object with the range selector values applied.
   */
  function processRangeSelector(queryParams) {
    let newParams = new URLSearchParams(queryParams.toString());
    for (const form of rangeSelectorForms) {
      const rangeName = form.dataset.name;
      const rangeFilterField = form.dataset.filterField;
      let valuesExist = false;
      const dateInputs = form.querySelectorAll('.date-fields input');
      // Check if we have any non-empty inputs:
      for (const input of dateInputs) {
        if (input.value !== '') {
          valuesExist = true;
          break;
        }
      }
      if (valuesExist) {
        // Update query params:
        for (const input of dateInputs) {
          newParams.set(input.name, input.value);
        }
        newParams.set(rangeFilterField, rangeName);
      } else {
        // Delete from query params:
        for (const input of dateInputs) {
          newParams.delete(input.name);
        }
        newParams = VuFind.deleteKeyValueFromURLSearchParams(newParams, rangeFilterField, rangeName);
      }
      // Remove any filter[]=rangeName:... from query params:
      const paramStart = rangeName + ':';
      for (const value of newParams.getAll('filter[]')) {
        if (value.startsWith(paramStart)) {
          newParams = VuFind.deleteKeyValueFromURLSearchParams(newParams, 'filter[]', value);
        }
      }
    }
    return newParams;
  }
  
  /**
   * Compile modified facets into lists of added and removed URL parameters.
   */
  function processModifiedFacets() {
    const elems = document.querySelectorAll('[data-multi-filters-modified="true"]');

    for (const elem of elems) {
      const href = elem.getAttribute('href');
      const p = href.indexOf('?');
      const elemParams = normalizeSearchQueryKeysAndValues(new URLSearchParams(p >= 0 ? href.substring(p + 1) : ''));

      // Add parameters that did not initially exist:
      for (const [key, value] of elemParams) {
        // URLSearchParams.has(key, value) seems to be broken on iOS 16, so check with our own method:
        if (!VuFind.inURLSearchParams(initialParams, key, value)) {
          appendNormalizedValue(globalAddedParams, key, value);
        }
      }
      // Remove parameters that this URL no longer has:
      for (const [key, value] of initialParams) {
        if (!VuFind.inURLSearchParams(elemParams, key, value)) {
          appendNormalizedValue(globalRemovedParams, key, value);
        }
      }
    }
  }
  
  /**
   * Compile current parameters and newly added / removed to return the URL to redirect to
   * @returns {string} The new URL to redirect to.
   */
  function getHrefWithNewParams() {
    processModifiedFacets();

    // Create params without the removed parameters:
    const newParams = VuFind.deleteParamsFromURLSearchParams(initialParams, globalRemovedParams);
    // Add newly added parameters:
    for (const [key, value] of globalAddedParams) {
      newParams.append(key, value);
    }

    // Take base url from data attribute if present (standalone full facet list):
    const baseUrl = defaultContext.dataset.searchUrl || window.location.pathname;
    return baseUrl + '?' + processRangeSelector(newParams).toString();
  }

  /**
   * Apply the selected multi-facets and redirects the page.
   */
  function applyMultiFacetsSelection() {
    defaultContext.getElementsByClassName('js-apply-multi-facets-selection')[0]
      .removeEventListener('click', applyMultiFacetsSelection);
    if (callbackOnApply instanceof Function) {
      callbackOnApply();
    }
    const params = {
      url: getHrefWithNewParams()
    };
    VuFind.emit(apply_event, params);
    window.location.assign(params.url);
  }

  /**
   * Toggle the visual selected style of a facet element.
   * @param {HTMLElement} elem The facet element to toggle.
   */
  function toggleSelectedFacetStyle(elem) {
    let excluded = elem.classList.contains('exclude');
    let facet;
    if (elem.classList.contains('facet')) {
      facet = elem;
    } else {
      facet = elem.closest('.facet');
    }
    excluded = excluded || facet.classList.contains('excluded');
    facet.classList.toggle(excluded ? 'excluded' : 'active');

    if (excluded) {
      return;
    }
    const icon = elem.closest('a').querySelector('.icon');
    if (icon !== null) {
      const newCheckedState = icon.dataset.checked === 'false';
      let attrs = {};
      attrs.class = 'icon-link__icon';
      attrs['data-checked'] = (newCheckedState ? 'true' : 'false');
      icon.outerHTML = VuFind.icon(newCheckedState ? 'facet-checked' : 'facet-unchecked', attrs);
    }
  }

  /**
   * Get the count of modified facet filters.
   * @returns {number} The number of modified facets.
   */
  function getModifiedFiltersCount() {
    return document.querySelectorAll('[data-multi-filters-modified="true"]').length;
  }

  /**
   * Update the count text displayed for the multi-facets feature.
   */
  function updateCountText() {
    const textElems = document.getElementsByClassName('multi-filters-text');
    const count = getModifiedFiltersCount();
    const text = count === 0 ? defaultCountText : VuFind.translate('modified_filter_count', { '%%count%%': count });
    for (const textElem of textElems) {
      textElem.textContent = text;
    }
  }

  /**
   * Hide the facet count text.
   */
  function hideCountText() {
    document.querySelectorAll('.multi-filters-text').forEach(el => el.style.display = 'none');
  }

  /**
   * Show the facet count text.
   */
  function showCountText() {
    document.querySelectorAll('.multi-filters-text').forEach(el => el.style.display = 'block');
  }

  /**
   * Toggle the visibility of the facet count text.
   * @param {boolean} [show] Whether to show or hide the text (default = true).
   */
  function toggleCountText(show = true) {
    if (show) {
      showCountText();
    } else {
      hideCountText();
    }
  }

  /**
   * Initialize the original count text to be used as a default state.
   * @param {HTMLElement} context The container element.
   */
  function initOriginalCountText(context) {
    if (typeof defaultCountText === 'undefined') {
      const multiFiltersTextEl = context.querySelector('.multi-filters-text');
      defaultCountText = multiFiltersTextEl ? multiFiltersTextEl.textContent : '-';
    }
  }

  /**
   * Handle a click event on a facet when multi-selection is enabled.
   * @param {Event} e The click event.
   */
  function handleMultiSelectionClick(e) {
    e.preventDefault();
    const elem = e.currentTarget;

    // Switch data-multi-filters-modified to keep track of changed facets
    const currentAttrVal = elem.getAttribute('data-multi-filters-modified');
    const isOriginalState = currentAttrVal === null || currentAttrVal === 'false';
    if (isOriginalState && elem.closest('.facet').querySelectorAll('[data-multi-filters-modified="true"]').length > 0) {
      elem.closest('.facet').querySelector('[data-multi-filters-modified="true"]').click();
    }
    elem.setAttribute('data-multi-filters-modified', isOriginalState);
    updateCountText();
    toggleSelectedFacetStyle(elem);
  }

  /**
   * Save the user's last selection state in local storage.
   * @param {boolean} state The state ('true' or 'false') to save.
   */
  function saveUserSelectionLastState(state) {
    localStorage.setItem(local_storage_variable_name, state ? 'true' : 'false');
  }

  /**
   * Retrieve the user's last selection state from local storage.
   * @returns {boolean|undefined} Return true or false if a state is found, or undefined otherwise.
   */
  function getUserSelectionLastState() {
    const state = localStorage.getItem(local_storage_variable_name);
    if (state === null) {
      return undefined;
    }
    return localStorage.getItem(local_storage_variable_name) === 'true';
  }

  /**
   * Toggle the multi-facets selection feature on or off.
   * @param {boolean} enable Whether to enable the feature.
   */
  function toggleMultiFacetsSelection(enable) {
    if (typeof enable !== 'undefined') {
      isMultiFacetsSelectionActivated = enable;
      saveUserSelectionLastState(isMultiFacetsSelectionActivated);
    }
    document.querySelectorAll('.multi-facet-selection').forEach( el => el.classList.toggle('multi-facet-selection-active', isMultiFacetsSelectionActivated) );
    const checkboxes = document.getElementsByClassName('js-user-selection-multi-filters');
    for (let i = 0; i < checkboxes.length; i++) {
      checkboxes[i].checked = isMultiFacetsSelectionActivated;
    }
    if (!isMultiFacetsSelectionActivated) {
      const elems = document.querySelectorAll('[data-multi-filters-modified="true"]');
      for (const elem of elems) {
        elem.setAttribute('data-multi-filters-modified', "false");
        toggleSelectedFacetStyle(elem);
      }
    }
    toggleCountText(isMultiFacetsSelectionActivated);
    const event = isMultiFacetsSelectionActivated ? activation_event : deactivation_event;
    VuFind.emit(event);
  }

  /**
   * Register a callback function to be executed when the "Apply" button is clicked.
   * @param {Function} callback The function to call on apply.
   */
  function registerCallbackOnApply(callback) {
    callbackOnApply = callback;
  }

  /**
   * Register a callback function to be executed when the multi-selection mode is deactivated.
   * @param {Function} callback The function to call when deactivated.
   */
  function registerCallbackWhenDeactivated(callback) {
    callbackWhenDeactivated = callback;
  }

  /**
   * Handle a click on a facet.
   * @param {Event} e The click event.
   */
  function handleClickedFacet(e) {
    if (isMultiFacetsSelectionActivated === true) {
      handleMultiSelectionClick(e);
    } else if (callbackWhenDeactivated instanceof Function) {
      callbackWhenDeactivated();
    }
  }

  /**
   * Initialize the multi-facet control elements.
   * @param {HTMLElement} context The container element.
   */
  function initMultiFacetControls(context) {
    // Listener on checkbox for multiFacetsSelection feature
    const activationElem = context.querySelector('.js-user-selection-multi-filters');
    if (activationElem) {
      activationElem.addEventListener('change', function multiFacetSelectionChange() { toggleMultiFacetsSelection(this.checked); } );
      toggleMultiFacetsSelection(getUserSelectionLastState());
    }
    // Listener on apply filters button
    const applyElem = context.querySelector('.js-apply-multi-facets-selection');
    if (applyElem) {
      applyElem.addEventListener('click', applyMultiFacetsSelection);
    }
  }

  /**
   * Initialize click handlers for individual facet links.
   * @param {HTMLElement} context The container element.
   */
  function initFacetClickHandler(context) {
    context.classList.add('multi-facet-selection');
    context.querySelectorAll('a.facet:not(.narrow-toggle):not(.js-facet-next-page), .facet a').forEach(function addListeners(link) {
      link.addEventListener('click', handleClickedFacet);
    });
  }

  /**
   * Initialize event listeners for date range selector forms.
   * @param {HTMLElement} context The container element.
   */
  function initRangeSelection(context) {
    context.querySelectorAll('div.facet form .date-fields').forEach((elem) => {
      const formElement = elem.closest('form');
      if (formElement && !rangeSelectorForms.includes(formElement)) {
        rangeSelectorForms.push(formElement);
        formElement.addEventListener('submit', function rangeFormSubmit(e) {
          if (isMultiFacetsSelectionActivated) {
            e.preventDefault();
          }
        });
      }
    });
  }

  /**
   * Initialize the multi-facets selection feature.
   * @param {HTMLElement} [_context] The container element to initialize. Defaults to #search-sidebar, then .js-full-facet-list if not found.
   */
  function init(_context) {
    if (!isMultiFacetsSelectionEnabled()) {
      return;
    }
    if (defaultContext === undefined) {
      defaultContext = document.getElementById('search-sidebar');
      if (null === defaultContext) {
        // No sidebar, we may be on the standalone full facet list page:
        defaultContext = document.querySelector('.js-full-facet-list');
        if (null === defaultContext) {
          // No context:
          return;
        }
      }
    }
    const context = (typeof _context === "undefined") ? defaultContext : _context;
    initOriginalCountText(context);
    initMultiFacetControls(context);
    initFacetClickHandler(context);
    initRangeSelection(context);
    // Synchronize the state of multi-facet checkboxes in case there's e.g. a lightbox with its own controls:
    let state;
    if (getMultiFacetsSelectionSetting() === 'always') {
      state = true;
    } else {
      state = getUserSelectionLastState();
      if (state === undefined) {
        state = getMultiFacetsSelectionPageLoadValue() ? true : undefined;
      }
    }
    VuFind.multiFacetsSelection.toggleMultiFacetsSelection(state);
  }

  return {
    init: init,
    registerCallbackOnApply: registerCallbackOnApply,
    registerCallbackWhenDeactivated: registerCallbackWhenDeactivated,
    toggleMultiFacetsSelection: toggleMultiFacetsSelection,
    initFacetClickHandler: initFacetClickHandler,
    initRangeSelection: initRangeSelection
  };
});

/* --- Side Facets --- */
VuFind.register('sideFacets', function SideFacets() {
  /**
   * Show a loading overlay on a facet container.
   *
   * This is used to indicate that new facet data is being loaded.
   */
  function showLoadingOverlay() {
    let elem;
    if (this === undefined || this.nodeName === undefined) {
      elem = $('#search-sidebar .collapse, .checkbox-filters');
    } else {
      elem = $(this).closest(".collapse");
    }
    elem.append(
      '<div class="facet-loading-overlay">'
      + '<span class="facet-loading-overlay-label">'
      + VuFind.loading()
      + '</span></div>'
    );
  }

  /**
   * Activate facet-blocking on facet links to show a loading overlay on click.
   * @param {jQuery} [context] The container element to activate blocking on.
   */
  function activateFacetBlocking(context) {
    const finalContext = (typeof context === "undefined") ? $(document.body) : context;
    finalContext.find('a.facet:not(.narrow-toggle):not(.js-facet-next-page),.facet a').click(showLoadingOverlay);
  }

  /**
   * Set form action on submit if necessary to get rid of any hash in current page URL
   * @param {Event} ev The form submission event.
   */
  function formSubmitHandler(ev) {
    const form = ev.target;
    if (form.getAttribute('action') === null) {
      const url = new URL(window.location);
      url.hash = '';
      form.setAttribute('action', url.toString());
    }
  }

  /**
   * Manage form submission to avoid including a hash (e.g #search-sidebar) in the URL
   */
  function setupFacetFormListeners() {
    document.querySelectorAll('.facet-group form').forEach((formEl) => formEl.addEventListener('submit', formSubmitHandler));
  }

  /**
   * Activate a single AJAX facet container, requesting and rendering its content.
   */
  function activateSingleAjaxFacetContainer() {
    var $container = $(this);
    var facetList = [];
    var $facets = $container.find('div.collapse.in[data-facet], div.collapse.show[data-facet], .checkbox-filters [data-facet]');
    $facets.each(function addFacet() {
      if (!$(this).data('initialized')) {
        facetList.push($(this).data('facet'));
        $(this).data('initialized', 'true');
      }
    });
    if (facetList.length === 0) {
      return;
    }
    const querySuppressed = $container.data('querySuppressed');
    let query = window.location.search.substring(1);
    if (querySuppressed) {
      // When the query is suppressed we can't use the page URL directly since it
      // doesn't contain the actual query, so take the full query and update any
      // parameters that may have been dynamically modified (we deliberately avoid)
      // touching anything else to avoid encoding issues e.g. with brackets):
      const storedQuery = new URLSearchParams($container.data('query'));
      const windowQuery = new URLSearchParams(query);
      ['sort', 'limit', 'page'].forEach(key => {
        const val = windowQuery.get(key);
        if (null !== val) {
          storedQuery.set(key, val);
        } else {
          storedQuery.delete(key);
        }
      });
      query = storedQuery.toString();
    }
    var request = {
      method: 'getSideFacets',
      searchClassId: $container.data('searchClassId'),
      location: $container.data('location'),
      configIndex: $container.data('configIndex'),
      querySuppressed: querySuppressed,
      extraFields: $container.data('extraFields'),
      enabledFacets: facetList
    };
    $container.find('.facet-load-indicator').removeClass('hidden');
    $.getJSON(VuFind.path + '/AJAX/JSON?' + query, request)
      .done(function onGetSideFacetsDone(response) {
        $.each(response.data.facets, function initFacet(facet, facetData) {
          var containerSelector = typeof facetData.checkboxCount !== 'undefined'
            ? '.checkbox-filters ' : '.facet-group ';
          var $facetContainer = $container.find(containerSelector + '[data-facet="' + facet + '"]');
          $facetContainer.data('loaded', 'true');
          if (typeof facetData.checkboxCount !== 'undefined') {
            if (facetData.checkboxCount !== null) {
              $facetContainer.find('.avail-count').text(
                facetData.checkboxCount.toString().replace(/\B(?=(\d{3})+\b)/g, VuFind.translate('number_thousands_separator'))
              );
            }
          } else if (typeof facetData.html !== 'undefined') {
            $facetContainer.html(VuFind.updateCspNonce(facetData.html));
            if (!isMultiFacetsSelectionEnabled()) {
              activateFacetBlocking($facetContainer);
            }
          }
          if (isMultiFacetsSelectionEnabled() && $facetContainer.length > 0) {
            VuFind.multiFacetsSelection.initFacetClickHandler($facetContainer.get()[0]);
          }
          $facetContainer.find('.facet-load-indicator').remove();
        });
        VuFind.lightbox.bind($('.sidebar'));
        if (isMultiFacetsSelectionEnabled()) {
          const sidebar = document.querySelector('.sidebar');
          if (sidebar) {
            VuFind.multiFacetsSelection.initRangeSelection(sidebar);
          }
        }
        setupFacetFormListeners();
        VuFind.emit('VuFind.sidefacets.loaded', {container: unwrapJQuery($container)});
      })
      .fail(function onGetSideFacetsFail() {
        $container.find('.facet-load-indicator').remove();
        $container.find('.facet-load-failed').removeClass('hidden');
      });
  }

  /**
   * Load all side facet containers that are configured for AJAX loading.
   */
  function loadAjaxSideFacets() {
    $('.side-facets-container-ajax').each(activateSingleAjaxFacetContainer);
  }

  /**
   * Load AJAX side facets with a tiny delay so that all non-collapsed items are available after initialization
   */
  function delayLoadAjaxSideFacets() {
    setTimeout(loadAjaxSideFacets, 50);
  }

  /**
   * Save the state of a facet group in session storage.
   * @param {Event}  e    The event object.
   * @param {string} data The state to save.
   */
  function facetSessionStorage(e, data) {
    var source = $('#result0 .hiddenSource').val();
    var id = e.target.id;
    var key = 'sidefacet-' + source + id;
    sessionStorage.setItem(key, data);
  }

  /**
   * Initialize the side facets, including listeners for state changes and AJAX loading.
   */
  function init() {
    if (isMultiFacetsSelectionEnabled()) {
      VuFind.multiFacetsSelection.registerCallbackOnApply(showLoadingOverlay);
      VuFind.multiFacetsSelection.registerCallbackWhenDeactivated(showLoadingOverlay);
    } else {
      // Display "loading" message after user clicks facet:
      activateFacetBlocking();
    }

    $('.facet-group .collapse').each(function openStoredFacets(index, item) {
      var source = $('#result0 .hiddenSource').val();
      var storedItem = sessionStorage.getItem('sidefacet-' + source + item.id);
      if (storedItem) {
        const oldTransitionState = VuFind.disableTransitions(item);
        try {
          if ((' ' + storedItem + ' ').indexOf(' in ') > -1) {
            $(item).collapse('show');
          } else if (!$(item).data('forceUncollapsed')) {
            $(item).collapse('hide');
          }
        } finally {
          VuFind.restoreTransitions(item, oldTransitionState);
        }
      }
    });

    // Save state on collapse/expand:
    let facetGroup = $('.facet-group');
    facetGroup.on('shown.bs.collapse', (e) => facetSessionStorage(e, 'in'));
    facetGroup.on('hidden.bs.collapse', (e) => facetSessionStorage(e, 'collapsed'));

    // Side facets loaded with AJAX
    document.querySelectorAll('.side-facets-container-ajax div[data-facet]').forEach((collapseEl) => {
      collapseEl.addEventListener('shown.bs.collapse', delayLoadAjaxSideFacets);
    });
    delayLoadAjaxSideFacets();

    // Keep filter dropdowns on screen
    document.querySelectorAll('.search-filter-dropdown').forEach((dropdown) => {
      dropdown.addEventListener('shown.bs.dropdown', () => {
        let dropdownMenu = dropdown.querySelector('.dropdown-menu');
        if (dropdown.getBoundingClientRect().left + dropdownMenu.offsetWidth >= window.innerWidth) {
          dropdownMenu.classList.add('dropdown-menu-end');
        } else {
          dropdownMenu.classList.remove('dropdown-menu-end');
        }
      });
    });

    setupFacetFormListeners();
  }

  return { init: init };
});

/* --- Lightbox Facets --- */
VuFind.register('lightbox_facets', function LightboxFacets() {
  /**
   * Handle sorting for facets displayed in a lightbox.
   */
  function lightboxFacetSorting() {
    var sortButtons = $('.js-facet-sort');
    
    /**
     * Trigger an AJAX call to update the facet list with a new sort order.
     * @param {HTMLElement} button The button element that was clicked to trigger the sort.
     */
    function sortAjax(button) {
      var sort = $(button).data('sort');
      VuFind.facetList.updateContent({facetsort: sort});
      $('.full-facet-list').addClass('hidden');
      sortButtons.removeClass('active');
    }
    sortButtons.off('click');
    sortButtons.on('click', function facetSortButton() {
      sortAjax(this);
      $(this).addClass('active');
      return false;
    });
  }

  /**
   * Set up all the event handlers and features for facets within a lightbox.
   */
  function setup() {
    if (isMultiFacetsSelectionEnabled()) {
      const elem = document.querySelector('.js-full-facet-list');
      if (elem) {
        VuFind.multiFacetsSelection.init(elem);
      }
    }
    lightboxFacetSorting();
    $('.js-facet-next-page').on("click", function facetLightboxMore() {
      const button = $(this);
      const page = parseInt(button.attr('data-page'), 10);
      if (button.attr('disabled')) {
        return false;
      }
      button.attr('disabled', 1);
      button.html(VuFind.translate('loading_ellipsis'));

      const overrideParams = { facetpage: page, layout: 'lightbox', ajax: 1 };
      VuFind.facetList.getContent(overrideParams).then(data => {
        $(data).find('.js-facet-item').each(function eachItem() {
          button.before($(this).prop('outerHTML'));
        });
        const list = $(data).find('.js-facet-item');
        if (list.length && $(data).find('.js-facet-next-page').length) {
          button.attr('data-page', page + 1);
          button.attr('href', button.attr('href').replace(/facetpage=\d+/, 'facetpage=' + (page + 1)));
          button.html(VuFind.translate('more_ellipsis'));
          button.removeAttr('disabled');
        } else {
          button.remove();
        }
        if (isMultiFacetsSelectionEnabled()) {
          document.querySelectorAll('.full-facet-list')
            .forEach(facetList => VuFind.multiFacetsSelection.initFacetClickHandler(facetList));
        }
      });
      return false;
    });
    const updateFacetListHeightFunc = function () {
      const margin = 230;
      $('#modal .lightbox-scroll').css('max-height', window.innerHeight - margin);
    };
    $(window).on('resize', updateFacetListHeightFunc);
    // Initial resize:
    updateFacetListHeightFunc();
  }

  return { setup: setup };
});

/**
 * Register the facet truncation functionality for side facets.
 */
function registerSideFacetTruncation() {
  VuFind.truncate.initTruncate('.truncate-facets', '.facet__list__item');
  // Only top level is truncatable with hierarchical facets:
  VuFind.truncate.initTruncate('.truncate-hierarchical-facets', '> li');
}

VuFind.listen('VuFind.sidefacets.loaded', registerSideFacetTruncation);
