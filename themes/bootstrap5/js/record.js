/*global deparam, getUrlRoot, recaptchaOnLoad, resetCaptcha, syn_get_widget, userIsLoggedIn, VuFind, setupJumpMenus */
/*exported ajaxTagUpdate, recordDocReady, refreshTagListCallback, addRecordRating */

/**
 * Functions and event handlers specific to record pages.
 */
function checkRequestIsValid(element, requestType, icon = 'place-hold') {
  const recordId = element.href.match(/\/Record\/([^/]+)\//)[1];
  const vars = deparam(element.href);
  vars.id = recordId;

  const url = VuFind.path + '/AJAX/JSON?' + new URLSearchParams({
    method: 'checkRequestIsValid',
    id: recordId,
    requestType: requestType,
    data: vars
  });
  fetch(url, {
    headers: {
      'Accept': 'application/json',
      'cache': 'no-store'
    }
  }).then(response => response.json())
    .then(function checkValidDone(response) {
      if (response.data.status) {
        element.classList.remove('disabled', 'request-check');
        element.title = response.data.msg;
        VuFind.setInnerHtml(element, VuFind.icon(icon) + '<span class="icon-link__label">' + VuFind.updateCspNonce(response.data.msg) + '</span>');
      } else {
        element.parentNode.removeChild(element);
      }
    })
    .catch(() => element.parentNode.removeChild(element));
}

function setUpCheckRequest(_context) {
  const context = typeof _context === "undefined" ? document : _context;
  context.querySelectorAll('.checkRequest').forEach(
    (element) => checkRequestIsValid(element, 'Hold', 'place-hold')
  );
  context.querySelectorAll('.checkStorageRetrievalRequest').forEach(
    (element) => checkRequestIsValid(element, 'StorageRetrievalRequest', 'place-storage-retrieval')
  );
  context.querySelectorAll('.checkILLRequest').forEach(
    (element) => checkRequestIsValid(element, 'ILLRequest', 'place-ill-request')
  );
}

function deleteRecordComment(element, recordId, recordSource, commentId) {
  const url = VuFind.path + '/AJAX/JSON?' + new URLSearchParams({ method: 'deleteRecordComment', id: commentId });
  fetch(url, {
    headers: {'Accept': 'application/json'}
  }).then(() => {
    const comment = element.closest('.comment');
    if (comment) {
      comment.parentNode.removeChild(comment);
    }
  });
}

function refreshCommentList(target, recordId, recordSource) {
  const commentList = target.querySelector('.comment-list');
  if (!commentList) return;
  const url = VuFind.path + '/AJAX/JSON?' + new URLSearchParams({
    method: 'getRecordCommentsAsHTML',
    id: recordId,
    source: recordSource
  });
  fetch(url, {
    headers: {'Accept': 'application/json'}
  }).then(response => response.json())
    .then((response) => {
      // Update HTML
      VuFind.setInnerHtml(commentList, VuFind.updateCspNonce(response.data.html));
      commentList.querySelectorAll('.delete')
        .forEach((deleteLink) => deleteLink.addEventListener('click', event => {
          event.preventDefault();
          const commentId = deleteLink.id.substring('recordComment'.length);
          deleteRecordComment(deleteLink, recordId, recordSource, commentId);
        }));
      resetCaptcha(target);
    });
}

function refreshRecordRating(recordId, recordSource) {
  const rating = document.querySelector('.media-left .rating');
  if (!rating) {
    return;
  }
  fetch(VuFind.path + '/AJAX/JSON?' + new URLSearchParams({
    method: 'getRecordRating',
    id: recordId,
    source: recordSource
  }))
    .then(response => response.json())
    .then(result => {
      VuFind.setOuterHtml(rating, result.data.html);
      // Bind lightbox to the new content:
      VuFind.lightbox.bind(rating);
    });
}

function postComment(event) {
  event.preventDefault();
  const form = event.target;
  const id = form.id.value;
  const recordSource = form.source.value;
  const url = VuFind.path + '/AJAX/JSON?' + new URLSearchParams({ method: 'commentRecord' });
  const data = {};
  form.querySelectorAll('input,textarea').forEach((input) => {
    if (input.type !== 'radio' || input.checked) {
      data[input.name] = input.value;
    }
  });
  fetch(url, {
    method: 'POST',
    headers: {'Accept': 'application/json'},
    body: new URLSearchParams(data)
  }).then((response) => {
    if (!response.ok) {
      return response.json();
    }
    return Promise.resolve();
  })
    .then((optionalError) => {
      if (optionalError) {
        VuFind.lightbox.alert(optionalError.data, 'danger');
        return;
      }
      let tab = form.closest('.list-tab-content');
      if (!tab) {
        tab = form.closest('.tab-pane');
      }
      if (tab) {
        refreshCommentList(tab, id, recordSource);
      }
      refreshRecordRating(id, recordSource);
      const textArea = form.querySelector('textarea[name="comment"]');
      if (textArea) {
        textArea.value = '';
      }
      if (form.dataset.ratingRemoval === "false" && Object.prototype.hasOwnProperty.call(data, 'rating') && '' !== data.rating) {
        const link = form.querySelector('a[data-click-set-checked]');
        if (link) {
          link.parentNode.removeChild(link);
        }
      }
      resetCaptcha(form);
    });
}

function registerAjaxCommentRecord(_context) {
  const context = typeof _context === "undefined" ? document : _context;

  // Form submission
  context.querySelectorAll('form.comment-form')
    .forEach((form) => form.addEventListener('submit', postComment));

  // Delete links
  context.querySelectorAll('.delete')
    .forEach((deleteLink) => deleteLink.addEventListener('click', event => {
      event.preventDefault();
      const commentId = deleteLink.id.substring('recordComment'.length);
      const id = document.querySelector('.hiddenId');
      const source = document.querySelector('.hiddenSource');
      if (id && source) {
        deleteRecordComment(deleteLink, id.value, source.value, commentId);
      }
    }));
}

// Forward declaration
let ajaxLoadTab = function ajaxLoadTabForward() {
};

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

function handleAjaxTabLinks() {
  // Form submission
  document.querySelectorAll('a').forEach((a) => {
    const href = a.href;
    if (typeof href !== 'undefined' && href.match(/\/AjaxTab[/?]/)) {
      a.addEventListener('click', handleAjaxTabLinkClick);
    }
  });
}

function registerTabEvents(params) {
  const container = params.container;

  // Logged in AJAX
  registerAjaxCommentRecord(container);
  // Render recaptcha
  recaptchaOnLoad(container);

  setUpCheckRequest(container);

  handleAjaxTabLinks();
}
VuFind.listen('record-tab-init', registerTabEvents);

// Update print button to correct tab prints
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

function addTabToURL(tabId) {
  window.location.hash = tabId;
  setPrintBtnHash(tabId);
}

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
      if (typeof syn_get_widget === 'function') {
        syn_get_widget();
      }
      if (typeof setHash == 'undefined' || setHash) {
        addTabToURL(tabId);
      } else {
        removeHashFromLocation();
      }
      setupJumpMenus(newTab);
    });
};

function refreshTagList(_target, _loggedin) {
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
function refreshTagListCallback() {
  refreshTagList(false, true);
}

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
    refreshTagList(target, false);
  });
}

function getNewRecordTab(tabId) {
  const newRecordTab = document.createElement("div");
  newRecordTab.role = 'tabpanel';
  newRecordTab.classList.add('tab-pane', tabId + '-tab');
  newRecordTab.setAttribute('aria-labelledby', 'record-tab-' + tabId);
  VuFind.setInnerHtml(newRecordTab, VuFind.loading());
  return newRecordTab;
}

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

function applyRecordTabHash(scrollToTabs) {
  const activeLi = document.querySelector('.record-tabs li.active');
  const activeTab = activeLi ? activeLi.dataset.tab : undefined;
  const initiallyActiveTab = document.querySelector('.record-tabs li.initiallyActive a');
  const newTab = typeof window.location.hash !== 'undefined' ? window.location.hash.toLowerCase() : '';

  // Open tab in url hash
  if (initiallyActiveTab && (newTab.length <= 1 || newTab === '#tabnav')) {
    initiallyActiveTab.dispatchEvent(new Event('click'));
  } else if (newTab.length > 1 && '#' + activeTab !== newTab) {
    const tabLink = document.querySelector('.record-tabs .' + newTab.substring(1) + ' a');
    if (tabLink) {
      tabLink.dispatchEvent(new Event('click'));
      if (typeof scrollToTabs === 'undefined' || false !== scrollToTabs) {
        $('html, body').animate({
          scrollTop: $('.record-tabs').offset().top
        }, 500);
        tabLink.dispatchEvent(new Event('focus'));
      }
    }
  }
}

window.addEventListener('hashchange', applyRecordTabHash);

function removeCheckRouteParam() {
  if (window.location.search.indexOf('checkRoute=1') >= 0) {
    const newHref = window.location.href.replace('?checkRoute=1&', '?').replace(/[?&]checkRoute=1/, '');
    if (window.history && window.history.replaceState) {
      window.history.replaceState({}, '', newHref);
    }
  }
}

function recordDocReady() {
  removeCheckRouteParam();
  document.querySelectorAll('.record-tabs .nav-tabs a')
    .forEach((tab) => tab.addEventListener('click', (event) => {
      const li = tab.parentNode;
      // Don't change behavior of active tab.
      if (tab.classList.contains('active')) {
        return;
      }
      const tabId = li.dataset.tab;
      const top = tab.closest('.record-tabs');
      if (!top) return;
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
    }));

  document.querySelectorAll('[data-background]').forEach((el) => {
    backgroundLoadTab(el.dataset.tab);
  });

  VuFind.truncate.initTruncate('.truncate-subjects', '.subject-line');
  VuFind.truncate.initTruncate('table.truncate-field', 'tr.holding-row', function createTd(m) { return '<td colspan="2">' + m + '</td>'; });
  const recordTabs = document.querySelector( '.record-tabs');
  VuFind.emit('record-tab-init', {container: (recordTabs !== null) ? recordTabs : document});
  applyRecordTabHash(false);
}

function addRecordRating() {
  const ratingLink = document.querySelector('.rating-average a');
  if (ratingLink) {
    ratingLink.click();
  }
}
