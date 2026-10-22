/* global AjaxRequestQueue, VuFind */
VuFind.register('notices', function notices() {
  let __ajaxRequestQueue;

  /**
   * Create a promise-based function to post edit request.
   * @returns {Promise} A promise for the edit AJAX request.
   */
  function __getEditRequestPromise() {
    return function postEditRequest(items) {
      {
        let requestObject = {};
        items.forEach(item => {
          requestObject = {...requestObject, ...item}
        });
        let body = new URLSearchParams();
        for (const [noticeId, changes] of Object.entries(requestObject)) {
          for (const [key, value] of Object.entries(changes)) {
            body.append('notices[' + noticeId + '][' + key + ']', value.toString());
          }
        }
        return fetch(
          VuFind.path + '/AJAX/JSON?method=editNotices',
          {
            method: 'POST',
            headers: {
              'Accept': 'application/json',
            },
            body: body
          }
        );
      }
    };
  }

  /**
   * Set up button to toggle enabled status.
   */
  function __setUpToggleEnabledButton() {
    document.querySelectorAll('.notice-row').forEach((row) => {
      let noticeId = row.dataset.noticeId;
      let enabledToggleButton = row.querySelector('.notice-enabled-toggle');
      if (!enabledToggleButton) return;
      let enabledIcon = enabledToggleButton.querySelector('.enabled-icon');
      let disabledIcon = enabledToggleButton.querySelector('.disabled-icon');
      if (!enabledIcon || !disabledIcon) return;
      enabledToggleButton.addEventListener('click', (event) => {
        event.preventDefault();
        let enabled = enabledToggleButton.hasAttribute('data-enabled');
        let body = {};
        body[noticeId] = {'enabled': !enabled};
        __ajaxRequestQueue.add(body);
        if (enabled) {
          enabledToggleButton.removeAttribute('data-enabled');
          enabledToggleButton.title = VuFind.translate('Enable');
          enabledIcon.classList.add('hidden');
          disabledIcon.classList.remove('hidden');
        } else {
          enabledToggleButton.setAttribute('data-enabled', '');
          enabledToggleButton.title = VuFind.translate('Disable');
          enabledIcon.classList.remove('hidden');
          disabledIcon.classList.add('hidden');
        }
      });
    });
  }

  /**
   * Initialize notice functionality.
   */
  function init() {
    __ajaxRequestQueue = new AjaxRequestQueue({
      run: __getEditRequestPromise()
    });
    __setUpToggleEnabledButton();
  }

  return {
    init: init
  };
});
