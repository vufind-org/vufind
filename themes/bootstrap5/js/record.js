/*global userIsLoggedIn, VuFind */
VuFind.register('record', function Record() {
  /**
   * Refresh the tag list for a record.
   * @param {HTMLElement} [_target]   The container element for the record (default = document).
   * @param {boolean}     [_loggedin] Whether the user is logged in.
   */
  function _refreshTagList(_target, _loggedin) {
    const loggedin = !!_loggedin || userIsLoggedIn;
    const target = _target || document;
    const recordId = target.querySelector('.hiddenId');
    const recordSource = target.querySelector('.hiddenSource');
    if (!recordId || !recordSource) return;
    const tagList = target.querySelector('.tagList');
    if (tagList) {
      let url = VuFind.path + '/AJAX/JSON?' + new URLSearchParams({
        method: 'getRecordTags',
        id: recordId.value,
        source: recordSource.value
      });
      fetch(url, {
        headers: {'Accept': 'application/json'},
      }).then(response => response.json())
        .then(response => {
          VuFind.setOuterHtml(tagList, VuFind.updateCspNonce(response.data.html));
          if (loggedin) {
            tagList.classList.add('loggedin');
          } else {
            tagList.classList.remove('loggedin');
          }
        });
    }
  }

  /**
   * Callback function to refresh the tag list for a logged-in user.
   */
  function refreshTagListCallback() {
    _refreshTagList(false, true);
  }

  /**
   * Update a record tag via an AJAX call.
   * @param {HTMLElement} [_link]   The link element that triggered the update (default = document).
   * @param {string}      tag       The tag to add or remove.
   * @param {boolean}     [_remove] Whether to remove the tag (default = false).
   */
  function ajaxTagUpdate(_link, tag, _remove) {
    const link = _link || document;
    const remove = _remove || false;
    const target = link.closest('.record');
    if (!target) return;
    const recordId = target.querySelector('.hiddenId');
    const recordSource = target.querySelector('.hiddenSource');
    if (!recordId || !recordSource) return;
    fetch(VuFind.path + '/AJAX/JSON?method=tagRecord', {
      method: 'POST',
      headers: {'Accept': 'application/json'},
      body: new URLSearchParams({
        tag: '"' + tag.replace(/\+/g, ' ') + '"',
        id: recordId.value,
        source: recordSource.value,
        remove: remove
      })
    }).finally(() => {
      _refreshTagList(target, false);
    });
  }

  /**
   * Remove the 'checkRoute' parameter from the URL.
   */
  function removeCheckRouteParam() {
    if (window.location.search.indexOf('checkRoute=1') >= 0) {
      const newHref = window.location.href.replace('?checkRoute=1&', '?').replace(/[?&]checkRoute=1/, '');
      if (window.history && window.history.replaceState) {
        window.history.replaceState({}, '', newHref);
      }
    }
  }

  /**
   * Initialize record functionality.
   */
  function init() {
    removeCheckRouteParam();
    VuFind.truncate.initTruncate('.truncate-subjects', '.subject-line');
    VuFind.truncate.initTruncate('table.truncate-field', 'tr.holding-row', function createTd(m) { return '<td colspan="2">' + m + '</td>'; });
  }

  return {
    init,
    refreshTagListCallback,
    ajaxTagUpdate
  };
});
