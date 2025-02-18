/*global deparam, getUrlRoot, recaptchaOnLoad, resetCaptcha, syn_get_widget, userIsLoggedIn, VuFind, setupJumpMenus, escapeHtmlAttr */
/*exported ajaxTagUpdate, recordDocReady, refreshTagListCallback, addRecordRating */

/**
 * Functions and event handlers specific to record pages.
 */
function checkRequestIsValid(element, requestType, icon = 'place-hold') {
  let recordId = element.href.match(/\/Record\/([^/]+)\//)[1];
  let vars = deparam(element.href);
  vars.id = recordId;

  let url = VuFind.path + '/AJAX/JSON?' + new URLSearchParams({
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
        element.classList.remove('disabled');
        element.classList.remove('request-check');
        element.title = response.data.msg;
        VuFind.setInnerHtml(element, VuFind.icon(icon) + '<span class="icon-link__label">' + VuFind.updateCspNonce(response.data.msg) + '</span>');
      } else {
        element.parentNode.removeChild(element);
      }
    })
    .catch(() => element.parentNode.removeChild(element));
}

function setUpCheckRequest(_context) {
  let context = typeof _context === "undefined" ? document : _context;
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
  let url = VuFind.path + '/AJAX/JSON?' + new URLSearchParams({ method: 'deleteRecordComment', id: commentId });
  fetch(url, {
    headers: {'Accept': 'application/json'}
  }).then(function deleteCommentDone() {
    let comment = element.closest('.comment');
    comment.parentNode.removeChild(comment);
  });
}

function refreshCommentList(target, recordId, recordSource) {
  let url = VuFind.path + '/AJAX/JSON?' + new URLSearchParams({
    method: 'getRecordCommentsAsHTML',
    id: recordId,
    source: recordSource
  });
  fetch(url, {
    headers: {'Accept': 'application/json'}
  }).then(response => response.json())
    .then(function refreshCommentListDone(response) {
      // Update HTML
      let commentList = target.querySelector('.comment-list');
      VuFind.setInnerHtml(commentList, '');
      commentList.insertAdjacentHTML('beforeend', VuFind.updateCspNonce(response.data.html));
      commentList.querySelectorAll('.delete')
        .forEach((deleteLink) => deleteLink.addEventListener('click', event => {
          event.preventDefault();
          let commentId = deleteLink.id.substring('recordComment'.length);
          deleteRecordComment(deleteLink, recordId, recordSource, commentId);
        }));
      resetCaptcha(target);
    });
}

function refreshRecordRating(recordId, recordSource) {
  let rating = document.querySelector('.media-left .rating');
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
      rating.outerHTML = result.data.html;
      // Bind lightbox to the new content:
      VuFind.lightbox.bind(document.querySelector('.media-left .rating'));
    });
}

function registerAjaxCommentRecord(_context) {
  let context = typeof _context === "undefined" ? document : _context;
  // Form submission
  context.querySelectorAll('form.comment-form')
    .forEach((form) => form.addEventListener('submit', event => {
      event.preventDefault();
      let id = form.id.value;
      let recordSource = form.source.value;
      let url = VuFind.path + '/AJAX/JSON?' + new URLSearchParams({ method: 'commentRecord' });
      let data = {};
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
          refreshCommentList(tab, id, recordSource);
          refreshRecordRating(id, recordSource);
          form.querySelector('textarea[name="comment"]').value = '';
          if (form.dataset.ratingRemoval === "false" && Object.prototype.hasOwnProperty.call(data, 'rating') && '' !== data.rating) {
            let link = form.querySelector('a[data-click-set-checked]');
            if (link !== null) {
              link.parentNode.removeChild(link);
            }
          }
          resetCaptcha(form);
        });
    }));

  // Delete links
  context.querySelectorAll('.delete')
    .forEach((deleteLink) => deleteLink.addEventListener('click', event => {
      event.preventDefault();
      let commentId = deleteLink.id.substring('recordComment'.length);
      let id = document.querySelector('.hiddenId').value;
      let source = document.querySelector('.hiddenSource').value;
      deleteRecordComment(deleteLink, id, source, commentId);
    }));
}

// Forward declaration
let ajaxLoadTab = function ajaxLoadTabForward() {
};

function handleAjaxTabLinks() {
  // Form submission
  document.querySelectorAll('a').forEach(function handleLink(a) {
    let href = a.href;
    if (typeof href !== 'undefined' && href.match(/\/AjaxTab[/?]/)) {
      a.addEventListener('click', event => {
        event.preventDefault();
        let tabId = document.querySelector('.record-tabs .nav-tabs li.active').dataset.tab;
        let tab = document.querySelector('.' + tabId + '-tab');
        VuFind.setInnerHtml(tab, '<div role="tabpanel" class="tab-pane ' + tabId + '-tab">' + VuFind.loading() + '</div>');
        ajaxLoadTab(tab, '', false, href);
      });
    }
  });
}

function registerTabEvents(params) {
  let container = params.container;

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
  let printBtn = document.querySelector(".print-record");
  if (!printBtn) {
    return;
  }
  let printHref = printBtn.getAttribute("href");
  let printURL = new URL(printHref, window.location.origin);
  printURL.hash = hash === null ? "" : hash;
  printBtn.setAttribute("href", printURL.href);
}

function addTabToURL(tabId) {
  window.location.hash = tabId;
  setPrintBtnHash(tabId);
}

function removeHashFromLocation() {
  if (window.history.replaceState) {
    let href = window.location.href.split('#');
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
  let postData = {};
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
  let loggedin = !!_loggedin || userIsLoggedIn;
  let target = _target || document;
  let recordId = target.querySelector('.hiddenId').value;
  let recordSource = target.querySelector('.hiddenSource').value;
  let tagList = target.querySelector('.tagList');
  if (tagList) {
    let url = VuFind.path + '/AJAX/JSON?' + new URLSearchParams({
      method: 'getRecordTags',
      id: recordId,
      source: recordSource
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
  let link = _link || document;
  let remove = _remove || false;
  let target = link.closest('.record');
  let recordId = target.querySelector('.hiddenId').value;
  let recordSource = target.querySelector('.hiddenSource').value;
  fetch(VuFind.path + '/AJAX/JSON?method=tagRecord', {
    method: 'POST',
    headers: {'Accept': 'application/json'},
    body: new URLSearchParams({
      tag: '"' + tag.replace(/\+/g, ' ') + '"',
      id: recordId,
      source: recordSource,
      remove: remove
    })
  }).finally(function tagRecordAlways() {
    refreshTagList(target, false);
  });
}

function getNewRecordTab(tabId) {
  let newRecordTab = document.createElement("div");
  newRecordTab.role = 'tabpanel';
  newRecordTab.classList.add('tab-pane', escapeHtmlAttr(tabId) + '-tab');
  newRecordTab.setAttribute('aria-labelledby', 'record-tab-' + escapeHtmlAttr(tabId));
  VuFind.setInnerHtml(newRecordTab, VuFind.loading());
  return newRecordTab;
}

function backgroundLoadTab(tabId) {
  if (document.querySelector('.' + tabId + '-tab')) {
    return;
  }
  let newTab = getNewRecordTab(tabId);
  document.querySelector('[data-tab="' + tabId + '"]')
    .closest('.result,.record')
    .querySelector('.tab-content')
    .append(newTab);
  return ajaxLoadTab(newTab, tabId, false);
}

function applyRecordTabHash(scrollToTabs) {
  let activeLi = document.querySelector('.record-tabs li.active');
  let activeTab = activeLi ? activeLi.dataset.tab : undefined;
  let initiallyActiveTab = document.querySelector('.record-tabs li.initiallyActive a');
  let newTab = typeof window.location.hash !== 'undefined' ? window.location.hash.toLowerCase() : '';

  // Open tab in url hash
  if (newTab.length <= 1 || newTab === '#tabnav') {
    initiallyActiveTab.dispatchEvent(new Event('click'));
  } else if (newTab.length > 1 && '#' + activeTab !== newTab) {
    let tabLink = document.querySelector('.record-tabs .' + newTab.substring(1) + ' a');
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
    let newHref = window.location.href.replace('?checkRoute=1&', '?').replace(/[?&]checkRoute=1/, '');
    if (window.history && window.history.replaceState) {
      window.history.replaceState({}, '', newHref);
    }
  }
}

function recordDocReady() {
  removeCheckRouteParam();
  document.querySelectorAll('.record-tabs .nav-tabs a')
    .forEach((tab) => tab.addEventListener('click', function recordTabsClick(event) {
      let li = tab.parentNode;
      // Don't change behavior of active tab.
      if (tab.classList.contains('active')) {
        return;
      }
      let tabId = li.dataset.tab;
      let top = tab.closest('.record-tabs');
      // if we're flagged to skip AJAX for this tab, we need special behavior:
      if (li.classList.contains('noajax')) {
        // if this was the initially active tab, we have moved away from it and
        // now need to return -- just switch it back on.
        if (li.classList.contains('initiallyActive')) {
          $(tab).tab('show');
          top.querySelector('.tab-pane.active').classList.remove('active');
          top.querySelector('.' + tabId + '-tab').classList.add('active');
          addTabToURL('tabnav');
          event.preventDefault();
        }
        // otherwise, we need to let the browser follow the link:
        return;
      }
      event.preventDefault();
      top.querySelectorAll('.tab-pane.active').forEach((e) => e.classList.remove('active'));
      $(tab).tab('show');
      if (top.querySelector('.' + tabId + '-tab')) {
        top.querySelector('.' + tabId + '-tab').classList.add('active');
        if (li.classList.contains('initiallyActive')) {
          removeHashFromLocation();
        } else {
          addTabToURL(tabId);
        }
      } else {
        let newTab = getNewRecordTab(tabId);
        newTab.classList.add('active');
        top.querySelector('.tab-content').append(newTab);
        ajaxLoadTab(newTab, tabId, !li.classList.contains('initiallyActive'));
      }
    }));

  document.querySelectorAll('[data-background]').forEach(function setupBackgroundTabs(el) {
    backgroundLoadTab(el.dataset.tab);
  });

  VuFind.truncate.initTruncate('.truncate-subjects', '.subject-line');
  VuFind.truncate.initTruncate('table.truncate-field', 'tr.holding-row', function createTd(m) { return '<td colspan="2">' + m + '</td>'; });
  VuFind.emit('record-tab-init', {container: document.querySelector( '.record-tabs')});
  applyRecordTabHash(false);
}

function addRecordRating() {
  document.querySelector('.rating-average a').click();
}
