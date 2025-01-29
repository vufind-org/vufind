/*global deparam, getUrlRoot, recaptchaOnLoad, resetCaptcha, syn_get_widget, userIsLoggedIn, VuFind, setupJumpMenus, escapeHtmlAttr */
/*exported ajaxTagUpdate, recordDocReady, refreshTagListCallback, addRecordRating */

/**
 * Functions and event handlers specific to record pages.
 */
function checkRequestIsValid(element, requestType, icon = 'place-hold') {
  var recordId = element.href.match(/\/Record\/([^/]+)\//)[1];
  var vars = deparam(element.href);
  vars.id = recordId;

  var url = VuFind.path + '/AJAX/JSON?' + $.param({
    method: 'checkRequestIsValid',
    id: recordId,
    requestType: requestType,
    data: vars
  });
  $.ajax({
    dataType: 'json',
    cache: false,
    url: url
  })
    .done(function checkValidDone(response) {
      if (response.data.status) {
        $(element).removeClass('disabled')
          .removeClass('request-check')
          .attr('title', response.data.msg)
          .html(VuFind.icon(icon) + '<span class="icon-link__label">' + VuFind.updateCspNonce(response.data.msg) + "</span>");
      } else {
        $(element).remove();
      }
    })
    .fail(function checkValidFail(/*response*/) {
      $(element).remove();
    });
}

function setUpCheckRequest() {
  $('.checkRequest').each(function checkRequest() {
    checkRequestIsValid(this, 'Hold', 'place-hold');
  });
  $('.checkStorageRetrievalRequest').each(function checkStorageRetrievalRequest() {
    checkRequestIsValid(this, 'StorageRetrievalRequest', 'place-storage-retrieval');
  });
  $('.checkILLRequest').each(function checkILLRequest() {
    checkRequestIsValid(this, 'ILLRequest', 'place-ill-request');
  });
}

function deleteRecordComment(element, recordId, recordSource, commentId) {
  var url = VuFind.path + '/AJAX/JSON?' + $.param({ method: 'deleteRecordComment', id: commentId });
  $.ajax({
    dataType: 'json',
    url: url
  })
    .done(function deleteCommentDone(/*response*/) {
      $($(element).closest('.comment')[0]).remove();
    });
}

function refreshCommentList($target, recordId, recordSource) {
  var url = VuFind.path + '/AJAX/JSON?' + $.param({
    method: 'getRecordCommentsAsHTML',
    id: recordId,
    source: recordSource
  });
  $.ajax({
    dataType: 'json',
    url: url
  })
    .done(function refreshCommentListDone(response) {
      // Update HTML
      var $commentList = $target.find('.comment-list');
      $commentList.empty();
      $commentList.append(VuFind.updateCspNonce(response.data.html));
      $commentList.find('.delete').off("click").on("click", function commentRefreshDeleteClick() {
        var commentId = $(this).attr('id').substring('recordComment'.length);
        deleteRecordComment(this, recordId, recordSource, commentId);
        return false;
      });
      $target.find('.comment-form input[type="submit"]').button('reset');
      resetCaptcha($target);
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
  var context = typeof _context === "undefined" ? document : _context;
  // Form submission
  $(context).find('form.comment-form').off("submit").on("submit", function commentFormSubmit() {
    var form = this;
    var id = form.id.value;
    var recordSource = form.source.value;
    var url = VuFind.path + '/AJAX/JSON?' + $.param({ method: 'commentRecord' });
    var data = {};
    $(form).find("input,textarea").each(function appendCaptchaData() {
      var input = $(this);
      if (input.attr('type') === 'radio' && !input.prop('checked')) {
        return true;
      }
      data[input.attr('name')] = input.val();
    });
    $.ajax({
      type: 'POST',
      url: url,
      data: data,
      dataType: 'json'
    })
      .done(function addCommentDone(/*response, textStatus*/) {
        var $form = $(form);
        var $tab = $form.closest('.list-tab-content');
        if (!$tab.length) {
          $tab = $form.closest('.tab-pane');
        }
        refreshCommentList($tab, id, recordSource);
        refreshRecordRating(id, recordSource);
        $form.find('textarea[name="comment"]').val('');
        $form.find('input[type="submit"]').button('loading');
        if ($form.data('ratingRemoval') === false && Object.prototype.hasOwnProperty.call(data, 'rating') && '' !== data.rating) {
          $form.find('a[data-click-set-checked]').remove();
        }
        resetCaptcha($form);
      })
      .fail(function addCommentFail(response, textStatus) {
        if (textStatus === 'abort' || typeof response.responseJSON === 'undefined') { return; }
        VuFind.lightbox.alert(response.responseJSON.data, 'danger');
      });
    return false;
  });
  // Delete links
  $('.delete').on("click", function commentDeleteClick() {
    var commentId = this.id.substr('recordComment'.length);
    deleteRecordComment(this, $('.hiddenId').val(), $('.hiddenSource').val(), commentId);
    return false;
  });
  // Prevent form submit
  return false;
}

// Forward declaration
var ajaxLoadTab = function ajaxLoadTabForward() {
};

function handleAjaxTabLinks(_context) {
  var context = typeof _context === "undefined" ? document : _context;
  // Form submission
  $(context).find('a').each(function handleLink() {
    var $a = $(this);
    var href = $a.attr('href');
    if (typeof href !== 'undefined' && href.match(/\/AjaxTab[/?]/)) {
      $a.off("click").on("click", function linkClick() {
        var tabid = $('.record-tabs .nav-tabs li.active').data('tab');
        var $tab = $('.' + tabid + '-tab');
        $tab.html('<div role="tabpanel" class="tab-pane ' + tabid + '-tab">' + VuFind.loading() + '</div>');
        ajaxLoadTab($tab, '', false, href);
        return false;
      });
    }
  });
}

function registerTabEvents() {
  // Logged in AJAX
  registerAjaxCommentRecord();
  // Render recaptcha
  recaptchaOnLoad();

  setUpCheckRequest();

  handleAjaxTabLinks();

  VuFind.lightbox.bind('.tab-pane.active');

  if (typeof VuFind.openurl !== 'undefined') {
    VuFind.openurl.init($('.tab-pane.active'));
  }
}

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

function addTabToURL(tabid) {
  window.location.hash = tabid;
  setPrintBtnHash(tabid);
}

function removeHashFromLocation() {
  if (window.history.replaceState) {
    var href = window.location.href.split('#');
    window.history.replaceState({}, document.title, href[0]);
  } else {
    window.location.hash = '#';
  }

  setPrintBtnHash(null);
}

ajaxLoadTab = function ajaxLoadTabReal($newTab, tabid, setHash, tabUrl) {
  // Request the tab via AJAX:
  var url = '';
  var postData = {};
  // If tabUrl is defined, it overrides base URL and tabid
  if (typeof tabUrl !== 'undefined') {
    url = tabUrl;
  } else {
    url = VuFind.path + getUrlRoot(document.URL) + '/AjaxTab';
    postData.tab = tabid;
    postData.sid = VuFind.getCurrentSearchId();
  }
  $.ajax({
    url: url,
    type: 'POST',
    data: postData
  })
    .always(function ajaxLoadTabDone(data) {
      if (typeof data === 'object') {
        $newTab.html(data.responseText ? VuFind.updateCspNonce(data.responseText) : VuFind.translate('error_occurred'));
      } else {
        $newTab.html(VuFind.updateCspNonce(data));
      }
      registerTabEvents();
      VuFind.emit('record-tab-init', {container: $newTab.get(0)});
      if (typeof syn_get_widget === "function") {
        syn_get_widget();
      }
      if (typeof setHash == 'undefined' || setHash) {
        addTabToURL(tabid);
      } else {
        removeHashFromLocation();
      }
      setupJumpMenus($newTab);
    });
  return false;
};

function refreshTagList(_target, _loggedin) {
  var loggedin = !!_loggedin || userIsLoggedIn;
  var target = _target || document;
  var recordId = $(target).find('.hiddenId').val();
  var recordSource = $(target).find('.hiddenSource').val();
  var $tagList = $(target).find('.tagList');
  if ($tagList.length > 0) {
    var url = VuFind.path + '/AJAX/JSON?' + $.param({
      method: 'getRecordTags',
      id: recordId,
      source: recordSource
    });
    $.ajax({
      dataType: 'json',
      url: url
    })
      .done(function getRecordTagsDone(response) {
        $tagList.empty();
        $tagList.replaceWith(VuFind.updateCspNonce(response.data.html));
        if (loggedin) {
          $tagList.addClass('loggedin');
        } else {
          $tagList.removeClass('loggedin');
        }
      });
  }
}
function refreshTagListCallback() {
  refreshTagList(false, true);
}

function ajaxTagUpdate(_link, tag, _remove) {
  var link = _link || document;
  var remove = _remove || false;
  var $target = $(link).closest('.record');
  var recordId = $target.find('.hiddenId').val();
  var recordSource = $target.find('.hiddenSource').val();
  $.ajax({
    url: VuFind.path + '/AJAX/JSON?method=tagRecord',
    method: 'POST',
    data: {
      tag: '"' + tag.replace(/\+/g, ' ') + '"',
      id: recordId,
      source: recordSource,
      remove: remove
    }
  })
    .always(function tagRecordAlways() {
      refreshTagList($target, false);
    });
}

function getNewRecordTab(tabid) {
  return $('<div role="tabpanel" class="tab-pane ' + escapeHtmlAttr(tabid) + '-tab" aria-labelledby="record-tab-' + escapeHtmlAttr(tabid) + '">' + VuFind.loading() + '</div>');
}

function backgroundLoadTab(tabid) {
  if ($('.' + tabid + '-tab').length > 0) {
    return;
  }
  var newTab = getNewRecordTab(tabid);
  $('[data-tab="' + tabid + '"]').closest('.result,.record').find('.tab-content').append(newTab);
  return ajaxLoadTab(newTab, tabid, false);
}

function applyRecordTabHash(scrollToTabs) {
  var activeTab = $('.record-tabs li.active').attr('data-tab');
  var $initiallyActiveTab = $('.record-tabs li.initiallyActive a');
  var newTab = typeof window.location.hash !== 'undefined'
    ? window.location.hash.toLowerCase() : '';

  // Open tab in url hash
  if (newTab.length <= 1 || newTab === '#tabnav') {
    $initiallyActiveTab.trigger("click");
  } else if (newTab.length > 1 && '#' + activeTab !== newTab) {
    var $tabLink = $('.record-tabs .' + newTab.substr(1) + ' a');
    if ($tabLink.length > 0) {
      $tabLink.trigger("click");
      if (typeof scrollToTabs === 'undefined' || false !== scrollToTabs) {
        $('html, body').animate({
          scrollTop: $('.record-tabs').offset().top
        }, 500);
        $tabLink.trigger("focus");
      }
    }
  }
}

$(window).on('hashchange', applyRecordTabHash);

function removeCheckRouteParam() {
  if (window.location.search.indexOf('checkRoute=1') >= 0) {
    var newHref = window.location.href.replace('?checkRoute=1&', '?').replace(/[?&]checkRoute=1/, '');
    if (window.history && window.history.replaceState) {
      window.history.replaceState({}, '', newHref);
    }
  }
}

function recordDocReady() {
  removeCheckRouteParam();
  $('.record-tabs .nav-tabs a').attr('aria-selected', 'false');
  $('.record-tabs .nav-tabs .initiallyActive a').attr('aria-selected', 'true');
  // update aria-selected attributes after a tab has been shown
  $('.record-tabs .nav-tabs a').on('shown.bs.tab', function shownTab(e) {
    $('.record-tabs .nav-tabs a').attr('aria-selected', 'false');
    $(e.target).attr('aria-selected', 'true');
  });
  $('.record-tabs .nav-tabs a').on('click', function recordTabsClick() {
    var $li = $(this).parent();
    // If it's an active tab, click again to follow to a shareable link.
    if ($li.hasClass('active')) {
      return true;
    }
    var tabid = $li.attr('data-tab');
    var $top = $(this).closest('.record-tabs');
    // if we're flagged to skip AJAX for this tab, we need special behavior:
    if ($li.hasClass('noajax')) {
      // if this was the initially active tab, we have moved away from it and
      // now need to return -- just switch it back on.
      if ($li.hasClass('initiallyActive')) {
        $(this).tab('show');
        $top.find('.tab-pane.active').removeClass('active');
        $top.find('.' + tabid + '-tab').addClass('active');
        addTabToURL('tabnav');
        return false;
      }
      // otherwise, we need to let the browser follow the link:
      return true;
    }
    $top.find('.tab-pane.active').removeClass('active');
    $(this).tab('show');
    if ($top.find('.' + tabid + '-tab').length > 0) {
      $top.find('.' + tabid + '-tab').addClass('active');
      if ($(this).parent().hasClass('initiallyActive')) {
        removeHashFromLocation();
      } else {
        addTabToURL(tabid);
      }
      return false;
    } else {
      var newTab = getNewRecordTab(tabid).addClass('active');
      $top.find('.tab-content').append(newTab);
      return ajaxLoadTab(newTab, tabid, !$(this).parent().hasClass('initiallyActive'));
    }
  });

  $('[data-background]').each(function setupBackgroundTabs(index, el) {
    backgroundLoadTab(el.dataset.tab);
  });

  VuFind.truncate.initTruncate('.truncate-subjects', '.subject-line');
  VuFind.truncate.initTruncate('table.truncate-field', 'tr.holding-row', function createTd(m) { return '<td colspan="2">' + m + '</td>'; });
  registerTabEvents();
  VuFind.emit('record-tab-init', {container: document.querySelector( '.record-tabs')});
  applyRecordTabHash(false);
}

function addRecordRating() {
  document.querySelector('.rating-average a').click();
}
