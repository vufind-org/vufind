/*global bootstrap, VuFind */
VuFind.register('embedded', function embedded() {
  const _STORAGEKEY = 'vufind_search_open';
  const _SEPARATOR = ':::';
  const _DELIM = ',';
  const _STATUS = {};

  /**
   * Synchronize the current status information to session storage for persistence.
   */
  function saveStatusToStorage() {
    const storage = [];
    for (let str in _STATUS) {
      if ({}.hasOwnProperty.call(_STATUS, str)) {
        if (_STATUS[str]) {
          str += _SEPARATOR + _STATUS[str];
        }
        storage.push(str);
      }
    }
    sessionStorage.setItem(_STORAGEKEY, [...new Set(storage)].sort().join(_DELIM));
  }

  /**
   * Add a record and its active tab to the storage status.
   * @param {string} id  The record ID.
   * @param {string} tab The ID of the active tab.
   * @private
   */
  function addToStorage(id, tab) {
    _STATUS[id] = tab;
    saveStatusToStorage();
  }
  /**
   * Remove a record from the storage status.
   * @param {string} id The record ID.
   */
  function removeFromStorage(id) {
    if (delete _STATUS[id]) {
      saveStatusToStorage();
    }
  }

  /**
   * Load the content for a specific record tab via AJAX.
   * @param {string}  tabId    The ID of the tab to load.
   * @param {boolean} [_click] Whether to trigger a click on the tab after loading (default = false).
   * @returns {boolean} Returns false if the tab redirects to a new page, otherwise true.
   */
  function ajaxLoadTab(tabId, _click) {
    const click = _click || false;
    const tab = document.getElementById(tabId);
    if (!tab) {
      return true;
    }
    const result = tab.closest('.result');
    if (!result) {
      return true;
    }
    const idElement = result.querySelector('.hiddenId');
    const sourceElement = result.querySelector('.hiddenSource');
    if (!idElement || !sourceElement) {
      return true;
    }
    const id = idElement.value;
    const source = sourceElement.value;
    if (tab.parentElement.classList.contains('noajax')) {
      if (tab.tagName.toLowerCase() === 'a') {
        window.location.href = tab.href;
      } else {
        const link = tab.querySelector('a');
        if (link) {
          window.location.href = link.dataset.href;
        }
      }
      return false;
    }
    let urlRoot;
    if (source === VuFind.defaultSearchBackend) {
      urlRoot = 'Record';
    } else {
      urlRoot = source.charAt(0).toUpperCase() + source.slice(1).toLowerCase() + 'Record';
    }
    if (!tab.classList.contains('loaded')) {
      const content = document.getElementById(tabId + '-content');
      if (!content) {
        return true;
      }
      VuFind.setInnerHtml(content, VuFind.loading());
      const tabType = tabId.split('_')[0];
      fetch(
        VuFind.path + '/' + urlRoot + '/' + encodeURIComponent(id) + '/AjaxTab',
        {
          method: 'POST',
          body: new URLSearchParams({ tab: tabType })
        }
      ).then(response => response.text())
        .then((data) => {
          const html = data.trim();
          if (html.length > 0) {
            VuFind.setInnerHtml(content, VuFind.updateCspNonce(html));
            VuFind.emit('record-tab-init', {container: content});
          } else {
            VuFind.setInnerHtml(content, VuFind.translate('collection_empty'));
          }
          tab.classList.add('loaded');
        });
    }
    if (click && !tab.parentElement.classList.contains('default')) {
      tab.click();
    }
    return true;
  }

  /**
   * Toggle the embedded detailed view of a record.
   * @param {HTMLElement} link The link element.
   * @param {string} [tabId] The ID of the tab to open (default = first available tab).
   * @returns {boolean} Return false to prevent the default link behavior.
   */
  function toggleDataView(link, tabId) {
    const viewType = link.dataset.view;
    // If full, return true
    if (viewType === 'full') {
      return true;
    }
    const result = link.closest('.result');
    const mediaBody = result.querySelector('.media-body');
    if (!mediaBody) return false;
    const shortNode = mediaBody.querySelector('.result-body');
    const linksNode = mediaBody.querySelector('.result-links');
    let longNode = mediaBody.querySelector('.long-view');
    // Insert new elements
    if (!link.classList.contains('js-setup')) {
      mediaBody.insertBefore(link, mediaBody.firstChild);
      result.classList.add('embedded');
      shortNode.classList.add('collapse');
      linksNode.classList.add('collapse');
      longNode = document.createElement('div');
      longNode.classList.add('long-view', 'collapse');

      // Add loading status
      const loadingHint = document.createElement('div');
      loadingHint.classList.add('loading', 'hidden');
      VuFind.setInnerHtml(loadingHint, VuFind.loading());
      const shortNodeParent = shortNode.parentNode;
      shortNodeParent.insertBefore(loadingHint, shortNode);
      shortNodeParent.insertBefore(longNode, shortNode);

      longNode.addEventListener('show.bs.collapse', function embeddedExpand() {
        link.classList.add('expanded');
      });

      longNode.addEventListener('hidden.bs.collapse', function embeddedCollapsed(e) {
        if (e.target.classList.contains('long-view')) {
          link.classList.remove('expanded');
        }
      });
      link.classList.add('expanded', 'js-setup');
    }
    // Gather information
    const divID = result.querySelector('.hiddenId').value;
    const shortNodeCollapse = bootstrap.Collapse.getOrCreateInstance(shortNode);
    const longNodeCollapse = bootstrap.Collapse.getOrCreateInstance(longNode);
    const linksNodeCollapse = bootstrap.Collapse.getOrCreateInstance(linksNode);
    // Toggle visibility
    if (!longNode.classList.contains('show')) {
      // AJAX for information
      if (longNode.childNodes.length === 0) {
        const loadingNode = mediaBody.querySelector('.loading');
        loadingNode.classList.remove('hidden');
        link.classList.add('expanded');
        const url = VuFind.path + '/AJAX/JSON?' + (new URLSearchParams({
          method: 'getRecordDetails',
          id: divID,
          type: viewType,
          source: result.querySelector('.hiddenSource').value
        }));
        fetch(url).then(response => response.json())
          .then((response) => {
            // Insert tabs html
            VuFind.setInnerHtml(longNode, VuFind.updateCspNonce(response.data.html));
            // Hide loading
            loadingNode.classList.add('hidden');
            longNodeCollapse.show();
            // Load first tab
            if (tabId) {
              document.getElementById(tabId).click();
              ajaxLoadTab(tabId, true);
            } else {
              let firstTab = longNode.querySelector('.list-tab-toggle.active');
              if (!firstTab) {
                firstTab = longNode.querySelector('.list-tab-toggle');
              }
              if (firstTab) {
                ajaxLoadTab(firstTab.id, true);
              }
            }

            longNode.querySelectorAll('.list-tab-toggle').forEach((toggle) => toggle.addEventListener('click', function embeddedTabLoad() {
              if (!toggle.parentElement.classList.contains('noajax')) {
                addToStorage(divID, toggle.id);
              }
              return ajaxLoadTab(toggle.id);
            }));
            longNode.querySelectorAll('[data-background]').forEach(function setupEmbeddedBackgroundTabs(el) {
              ajaxLoadTab(el.id, false);
            });
            // Add events to record toolbar
            VuFind.lightbox.bind(longNode);
            if (typeof VuFind.saveStatuses.init === 'function') {
              VuFind.saveStatuses.init(longNode);
            }

            VuFind.emit('embedded-record-init', {container: mediaBody});
          });
      } else {
        longNodeCollapse.show();
      }
      shortNodeCollapse.hide();
      linksNodeCollapse.hide();
      if (!link.classList.contains('auto')) {
        const activeTab = longNode.querySelector('.nav-link.active');
        const activeTabId = activeTab ? activeTab.id : null;
        addToStorage(divID, activeTabId);
      } else {
        link.classList.remove('auto');
      }
    } else {
      shortNodeCollapse.show();
      linksNodeCollapse.show();
      longNodeCollapse.hide();
      removeFromStorage(divID);
    }
    return false;
  }

  /**
   * Load the status of open records from session storage.
   */
  function loadStorage() {
    const storage = sessionStorage.getItem(_STORAGEKEY);
    if (!storage) {
      return;
    }
    const items = storage.split(_DELIM);
    const doomed = [];
    let hiddenIds;
    let parts;
    let result;
    let i;
    let j;
    hiddenIds = document.querySelectorAll('.hiddenId');
    for (i = 0; i < items.length; i++) {
      parts = items[i].split(_SEPARATOR);
      _STATUS[parts[0]] = parts[1] || null;
      result = null;
      for (j = 0; j < hiddenIds.length; j++) {
        if (hiddenIds[j].value === parts[0]) {
          result = hiddenIds[j].closest('.result');
          break;
        }
      }
      if (result === null) {
        doomed.push(parts[0]);
        continue;
      }
      const link = result.querySelector('.getFull');
      link.classList.add('auto', 'expanded');
      toggleDataView(link, parts[1]);
    }
    for (i = 0; i < doomed.length; i++) {
      removeFromStorage(doomed[i]);
    }
  }

  /**
   * Update the container by binding events and loading stored states.
   * @param {object} params An object containing the container element.
   */
  function updateContainer(params) {
    const container = params.container;
    container.querySelectorAll('.getFull').forEach((item) => item
      .addEventListener('click', function linkToggle(event) {
        event.preventDefault();
        return toggleDataView(item);
      }));
    container.querySelectorAll('.full-record-link').forEach((link) => link.classList.remove('hidden'));
    loadStorage();
  }

  /**
   * Initialize the embedded module.
   */
  function init() {
    updateContainer({container: document});
    VuFind.listen('results-init', updateContainer);
  }

  return {
    init: init
  };
});
