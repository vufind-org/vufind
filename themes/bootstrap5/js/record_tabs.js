/*global bootstrap, getUrlRoot, VuFind, setupJumpMenus */
VuFind.register('recordTabs', function RecordTabs() {
  /**
   * Load tab pane content via ajax.
   * @param {HTMLElement} _tabPane Tab pane
   */
  function _ajaxLoadTab(_tabPane) {
    const tabPane = _tabPane;
    if (tabPane.dataset.init === 'true' || tabPane.dataset.init === 'loading') return;
    // Needs to be passed to a const or it might be changed in the fetch.then block
    tabPane.dataset.init = 'loading';
    VuFind.setInnerHtml(tabPane, VuFind.loading());
    let url = tabPane.dataset.ajaxUrl ? tabPane.dataset.ajaxUrl : VuFind.path + getUrlRoot(document.URL) + '/AjaxTab';
    const postData = {};
    postData.tab = tabPane.dataset.tabName;
    postData.sid = VuFind.getCurrentSearchId();
    fetch(url, {
      method: 'POST',
      body: new URLSearchParams(postData)
    }).then(response => response.text())
      .then((data) => {
        if (typeof data === 'object') {
          VuFind.setInnerHtml(tabPane, data.responseText ? VuFind.updateCspNonce(data.responseText) : VuFind.translate('error_occurred'));
        } else {
          VuFind.setInnerHtml(tabPane, VuFind.updateCspNonce(data));
        }
        tabPane.dataset.init = 'true';
        VuFind.emit('record-tab-init', {container: tabPane});
        setupJumpMenus(tabPane);
        VuFind.emit('record-tab-loaded', {container: tabPane});
      });
  }

  /**
   * Update the container by binding events and loading tabs.
   * @param {object} params An object containing the container element.
   */
  function updateContainer(params) {
    const container = params.container;
    container.querySelectorAll('.record-tab-button')
      .forEach((tabButton) => {
        tabButton.addEventListener('show.bs.tab', () => {
          let tabPane = document.querySelector(tabButton.dataset.bsTarget);
          if (!tabPane) return;
          _ajaxLoadTab(tabPane);
        });
      });

    document.querySelectorAll('.record-tab-pane.active, .record-tab-pane[data-background="true"]')
      .forEach(_ajaxLoadTab);

    const recordTabs = document.querySelector( '.record-tabs');
    VuFind.emit('record-tab-init', {container: (recordTabs !== null) ? recordTabs : document});
  }

  /**
   * Initialize the record tabs.
   */
  function init() {
    updateContainer({container: document});
    VuFind.listen('embedded-record-init', updateContainer);

    // handle location hashes for supporting outdated links
    const hrefParts = window.location.href.split('#');
    if (hrefParts.length > 1) {
      let tabElement = document.querySelector('.record-tabs #tab-button-' + hrefParts[1]);
      if (!tabElement) return;
      if (window.history.replaceState) {
        const href = window.location.href.split('#');
        window.history.replaceState({}, document.title, href[0]);
      } else {
        window.location.hash = '#';
      }
      if (tabElement.classList.contains('active')) return;
      let tab = bootstrap.Tab.getOrCreateInstance(tabElement);
      tab.show();
    }
  }

  return {
    init
  };
});
