/*global getUrlRoot, VuFind, setupJumpMenus */
VuFind.register('recordTabs', function RecordTabs() {
  // Forward declaration
  let ajaxLoadTab = function ajaxLoadTabForward() {
  };

  /**
   * Handle a click on an AJAX tab link.
   * @param {Event} event The click event.
   */
  function handleAjaxTabLinkClick(event){
    event.preventDefault();
    const href = event.target.href;
    const activeTab = document.querySelector('.record-tabs .nav-tabs li.active');
    if (!activeTab) return;
    const tabId = activeTab.dataset.tab;
    const tab = document.querySelector('.' + tabId + '-tab');
    if (tab) {
      VuFind.setInnerHtml(tab, '<div role="tabpanel" class="tab-pane ' + tabId + '-tab">' + VuFind.loading() + '</div>');
      ajaxLoadTab(tab, '', false, href);
    }
  }

  /**
   * Register click handlers for AJAX tab links.
   */
  function handleAjaxTabLinks() {
    document.querySelectorAll('a').forEach((a) => {
      const href = a.href;
      if (typeof href !== 'undefined' && href.match(/\/AjaxTab[/?]/)) {
        a.addEventListener('click', handleAjaxTabLinkClick);
      }
    });
  }

  /**
   * Update the print button's URL hash.
   * @param {string|null} hash The hash to set.
   */
  function setPrintBtnHash(hash) {
    const printBtn = document.querySelector(".print-record");
    if (!printBtn) {
      return;
    }
    const printHref = printBtn.href;
    const printURL = new URL(printHref, window.location.origin);
    printURL.hash = hash === null ? "" : hash;
    printBtn.setAttribute("href", printURL.href);
  }

  /**
   * Add a tab ID to the URL hash.
   * @param {string} tabId The ID of the tab.
   */
  function addTabToURL(tabId) {
    window.location.hash = tabId;
    setPrintBtnHash(tabId);
  }

  /**
   * Remove the hash from the URL.
   */
  function removeHashFromLocation() {
    if (window.history.replaceState) {
      const href = window.location.href.split('#');
      window.history.replaceState({}, document.title, href[0]);
    } else {
      window.location.hash = '#';
    }

    setPrintBtnHash(null);
  }

  ajaxLoadTab = function ajaxLoadTabReal(newTab, tabId, _setHash, tabUrl) {
    // Request the tab via AJAX:
    let url = '';
    // Needs to be passed to a const or it might be changed in the fetch.then block
    const setHash = _setHash;
    const postData = {};
    // If tabUrl is defined, it overrides base URL and tabId
    if (typeof tabUrl !== 'undefined') {
      url = tabUrl;
    } else {
      url = VuFind.path + getUrlRoot(document.URL) + '/AjaxTab';
      postData.tab = tabId;
      postData.sid = VuFind.getCurrentSearchId();
    }
    fetch(url, {
      method: 'POST',
      body: new URLSearchParams(postData)
    }).then(response => response.text())
      .then((data) => {
        if (typeof data === 'object') {
          VuFind.setInnerHtml(newTab, data.responseText ? VuFind.updateCspNonce(data.responseText) : VuFind.translate('error_occurred'));
        } else {
          VuFind.setInnerHtml(newTab, VuFind.updateCspNonce(data));
        }
        VuFind.emit('record-tab-init', {container: newTab});
        if (typeof setHash == 'undefined' || setHash) {
          addTabToURL(tabId);
        } else {
          removeHashFromLocation();
        }
        setupJumpMenus(newTab);
        VuFind.emit('record-tab-loaded', {container: newTab});
      });
  };

  /**
   * Create a new tab content element for an AJAX tab.
   * @param {string} tabId The ID of the tab.
   * @returns {HTMLElement} The new tab element.
   */
  function getNewRecordTab(tabId) {
    const newRecordTab = document.createElement("div");
    newRecordTab.role = 'tabpanel';
    newRecordTab.classList.add('tab-pane', tabId + '-tab');
    newRecordTab.setAttribute('aria-labelledby', 'record-tab-' + tabId);
    VuFind.setInnerHtml(newRecordTab, VuFind.loading());
    return newRecordTab;
  }

  /**
   * Load a record tab in the background if it's not already present.
   * @param {string} tabId The ID of the tab to load.
   */
  function backgroundLoadTab(tabId) {
    if (document.querySelector('.' + tabId + '-tab')) {
      return;
    }
    const newTab = getNewRecordTab(tabId);
    const tab = document.querySelector('[data-tab="' + tabId + '"]');
    if (!tab) return;
    const container = tab.closest('.result,.record');
    if (!container) return;
    const tabContent = container.querySelector('.tab-content');
    if (!tabContent) return;
    tabContent.append(newTab);
    ajaxLoadTab(newTab, tabId, false);
  }

  /**
   * Apply the tab hash from the URL to open the corresponding tab.
   * @param {boolean} scrollToTabs Whether to scroll to the tabs section.
   */
  function applyRecordTabHash(scrollToTabs) {
    const activeLi = document.querySelector('.record-tabs li.active');
    const activeTab = activeLi ? activeLi.dataset.tab : undefined;
    const initiallyActiveTab = document.querySelector('.record-tabs li.initiallyActive a');
    const newTab = typeof window.location.hash !== 'undefined' ? window.location.hash.toLowerCase() : '';

    // Open tab in url hash
    if (initiallyActiveTab && (newTab.length <= 1 || newTab === '#tabnav')) {
      initiallyActiveTab.click();
      if (newTab === '#tabnav') {
        initiallyActiveTab.focus();
      }
    } else if (newTab.length > 1 && '#' + activeTab !== newTab) {
      const tabLink = document.querySelector('.record-tabs .' + newTab.substring(1) + ' a');
      if (tabLink) {
        tabLink.click();
        if (typeof scrollToTabs === 'undefined' || false !== scrollToTabs) {
          $('html, body').animate({
            scrollTop: $('.record-tabs').offset().top
          }, 500);
          tabLink.focus();
        }
      }
    }
  }

  /**
   * Update the container by binding events and loading tabs.
   * @param {object} params An object containing the container element.
   */
  function updateContainer(params) {
    const container = params.container;
    container.querySelectorAll('.record-tabs .nav-tabs a')
      .forEach((tab) => {
        const tabEventHandler = (event) => {
          const li = tab.parentNode;
          const tabId = li.dataset.tab;
          const top = tab.closest('.record-tabs');
          if (!top) return;
          const targetPane = top.querySelector('.tab-pane.' + tabId + '-tab');
          // Only trigger show on already active tabs to set up all attributes required for keyboard controls:
          if (tab.classList.contains('active') && targetPane && targetPane.classList.contains('active')) {
            event.preventDefault();
            $(tab).tab('show');
            return;
          }
          // if we're flagged to skip AJAX for this tab, we need special behavior:
          if (li.classList.contains('noajax')) {
            // if this was the initially active tab, we have moved away from it and
            // now need to return -- just switch it back on.
            if (li.classList.contains('initiallyActive')) {
              $(tab).tab('show');
              top.querySelectorAll('.tab-pane.active').forEach(e => e.classList.remove('active'));
              top.querySelectorAll('.' + tabId + '-tab').forEach(e => e.classList.add('active'));
              addTabToURL('tabnav');
              event.preventDefault();
            }
            // otherwise, we need to let the browser follow the link:
            return;
          }
          event.preventDefault();
          top.querySelectorAll('.tab-pane.active').forEach((e) => e.classList.remove('active'));
          $(tab).tab('show');
          const tabById = top.querySelector('.' + tabId + '-tab');
          if (tabById) {
            tabById.classList.add('active');
            if (li.classList.contains('initiallyActive')) {
              removeHashFromLocation();
            } else {
              addTabToURL(tabId);
            }
          } else {
            const newTab = getNewRecordTab(tabId);
            newTab.classList.add('active');
            const tabContent = top.querySelector('.tab-content');
            if (tabContent) {
              tabContent.append(newTab);
            }
            ajaxLoadTab(newTab, tabId, !li.classList.contains('initiallyActive'));
          }
        };
        tab.addEventListener('click', tabEventHandler);
        tab.addEventListener('focus', tabEventHandler);
      });

    container.querySelectorAll('[data-background]').forEach((el) => {
      backgroundLoadTab(el.dataset.tab);
    });

    const recordTabs = container.querySelector( '.record-tabs');
    VuFind.emit('record-tab-init', {container: (recordTabs !== null) ? recordTabs : document});
  }

  /**
   * Initialize the record tabs.
   */
  function init() {
    window.addEventListener('hashchange', applyRecordTabHash);
    handleAjaxTabLinks();
    updateContainer({container: document});
    applyRecordTabHash(false);
  }

  return {
    init
  };
});
