/*global bootstrap, VuFind */
VuFind.register('embedded', function embedded() {
  let _STORAGEKEY = 'vufind_search_open';
  let _SEPARATOR = ':::';
  let _DELIM = ',';
  let _STATUS = {};

  /**
   * Synchronize the current status information to session storage for persistence.
   */
  function saveStatusToStorage() {
    let storage = [];
    let str;
    for (str in _STATUS) {
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
   * @param {string} id The record ID.
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
   * Toggle the embedded detailed view of a record.
   * @param {HTMLElement} link The link element.
   * @param {string} [tabId] The ID of the tab to open (default = first available tab).
   * @returns {boolean} Return false to prevent the default link behavior.
   */
  function toggleDataView(link, tabId) {
    let viewType = link.dataset.view;
    // If full, return true
    if (viewType === 'full') {
      return true;
    }
    let result = link.closest('.result');
    let mediaBody = result.querySelector('.media-body');
    if (!mediaBody) return false;
    let shortNode = mediaBody.querySelector('.result-body');
    let linksNode = mediaBody.querySelector('.result-links');
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
      let loadingHint = document.createElement('div');
      loadingHint.classList.add('loading', 'hidden');
      VuFind.setInnerHtml(loadingHint, VuFind.loading());
      let shortNodeParent = shortNode.parentNode;
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
    let divID = result.querySelector('.hiddenId').value;
    let shortNodeCollapse = bootstrap.Collapse.getOrCreateInstance(shortNode);
    let longNodeCollapse = bootstrap.Collapse.getOrCreateInstance(longNode);
    let linksNodeCollapse = bootstrap.Collapse.getOrCreateInstance(linksNode);
    // Toggle visibility
    if (!longNode.classList.contains('show')) {
      // AJAX for information
      if (longNode.childNodes.length === 0) {
        let loadingNode = mediaBody.querySelector('.loading');
        loadingNode.classList.remove('hidden');
        link.classList.add('expanded');
        let url = VuFind.path + '/AJAX/JSON?' + (new URLSearchParams({
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
              let tabElement = longNode.querySelector('#' + tabId);
              if (tabElement && !tabElement.classList.contains('active')) {
                bootstrap.Tab.getOrCreateInstance(tabElement).show();
              }
            }

            longNode.querySelectorAll('.nav-link').forEach((tabButton) => tabButton.addEventListener(
              'show.bs.tab',
              () => addToStorage(divID, tabButton.id)
            ));

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
        let activeTab = longNode.querySelector('.nav-link.active');
        let activeTabId = activeTab ? activeTab.id : null;
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
    let storage = sessionStorage.getItem(_STORAGEKEY);
    if (!storage) {
      return;
    }
    let items = storage.split(_DELIM);
    let doomed = [];
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
      let link = result.querySelector('.getFull');
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
