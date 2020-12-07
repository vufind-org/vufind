/*global deparam, getUrlRoot, recaptchaOnLoad, resetCaptcha, syn_get_widget, userIsLoggedIn, VuFind, setupJumpMenus */
/*exported ajaxTagUpdate, recordDocReady, refreshTagListCallback */

/**
 * Functions and event handlers specific to record pages.
 */
function checkRequestIsValid(element, requestType) {
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
          .attr('title', response.data.msg)
          .html('<i class="fa fa-flag" aria-hidden="true"></i>&nbsp;' + response.data.msg);
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
    checkRequestIsValid(this, 'Hold');
  });
  $('.checkStorageRetrievalRequest').each(function checkStorageRetrievalRequest() {
    checkRequestIsValid(this, 'StorageRetrievalRequest');
  });
  $('.checkILLRequest').each(function checkILLRequest() {
    checkRequestIsValid(this, 'ILLRequest');
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
      $commentList.append(response.data.html);
      $commentList.find('.delete').unbind('click').click(function commentRefreshDeleteClick() {
        var commentId = $(this).attr('id').substr('recordComment'.length);
        deleteRecordComment(this, recordId, recordSource, commentId);
        return false;
      });
      $target.find('.comment-form input[type="submit"]').button('reset');
      resetCaptcha($target);
    });
}

function registerAjaxCommentRecord(_context) {
  var context = typeof _context === "undefined" ? document : _context;
  // Form submission
  $(context).find('form.comment-form').unbind('submit').submit(function commentFormSubmit() {
    var form = this;
    var id = form.id.value;
    var recordSource = form.source.value;
    var url = VuFind.path + '/AJAX/JSON?' + $.param({ method: 'commentRecord' });
    var data = {};
    $(form).find("input,textarea").each(function appendCaptchaData() {
      var input = $(this);
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
        $form.find('textarea[name="comment"]').val('');
        $form.find('input[type="submit"]').button('loading');
        resetCaptcha($form);
      })
      .fail(function addCommentFail(response, textStatus) {
        if (textStatus === 'abort' || typeof response.responseJSON === 'undefined') { return; }
        VuFind.lightbox.alert(response.responseJSON.data, 'danger');
      });
    return false;
  });
  // Delete links
  $('.delete').click(function commentDeleteClick() {
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
      $a.unbind('click').click(function linkClick() {
        var tabid = $('.record-tabs .nav-tabs li.active').data('tab');
        var $tab = $('.' + tabid + '-tab');
        $tab.html('<i class="fa fa-spinner fa-spin" aria-hidden="true"></i> ' + VuFind.translate('loading') + '...</div>');
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
}

function removeHashFromLocation() {
  if (window.history.replaceState) {
    var href = window.location.href.split('#');
    window.history.replaceState({}, document.title, href[0]);
  } else {
    window.location.hash = '#';
  }
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
  }
  $.ajax({
    url: url,
    type: 'POST',
    data: postData
  })
    .always(function ajaxLoadTabDone(data) {
      if (typeof data === 'object') {
        $newTab.html(data.responseText ? data.responseText : VuFind.translate('error_occurred'));
      } else {
        $newTab.html(data);
      }
      registerTabEvents();
      if (typeof syn_get_widget === "function") {
        syn_get_widget();
      }
      if (typeof setHash == 'undefined' || setHash) {
        window.location.hash = tabid;
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
        $tagList.replaceWith(response.data.html);
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
  return $('<div class="tab-pane ' + tabid + '-tab"><i class="fa fa-spinner fa-spin" aria-hidden="true"></i> ' + VuFind.translate('loading') + '...</div>');
}

function backgroundLoadTab(tabid) {
  if ($('.' + tabid + '-tab').length > 0) {
    return;
  }
  var newTab = getNewRecordTab(tabid);
  $('[data-tab="' + tabid + '"]').closest('.result,.record').find('.tab-content').append(newTab);
  return ajaxLoadTab(newTab, tabid, false);
}

function applyRecordTabHash() {
  var activeTab = $('.record-tabs li.active').attr('data-tab');
  var $initiallyActiveTab = $('.record-tabs li.initiallyActive a');
  var newTab = typeof window.location.hash !== 'undefined'
    ? window.location.hash.toLowerCase() : '';

  // Open tab in url hash
  if (newTab.length <= 1 || newTab === '#tabnav') {
    $initiallyActiveTab.click();
  } else if (newTab.length > 1 && '#' + activeTab !== newTab) {
    $('.record-tabs .' + newTab.substr(1) + ' a').click();
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

function initTruncate(_holder, _target, _fill = function(m) { return m } ) {
  var holder = typeof _holder === 'undefined' ? $(document) : $(_holder);
  var target = $(_target);
  var targetElemName = target.length && target.prop('tagName').toLowerCase();
  var rowCount = holder.data('rows') || 3;
  var moreLabel = holder.data('more-label') ? holder.data('more-label') : VuFind.translate('show_more');
  var lessLabel = holder.data('less-label') ? holder.data('less-label') : VuFind.translate('show_less');
  var btnSize = holder.data('btn-size') ? ` ${holder.data('btn-size')}` : '';
  var topToggle = holder.data('top-toggle') || Infinity;
  var inPlaceToggle = holder.data('in-place-toggle') || false;

  holder.each(function parseHolder() {
    var self = $(this);
    var numRows = self.find(target).length || 0;
    // Truncate only if there's more than one line to hide
    var shouldTruncate = rowCount < numRows || false;
    self.find(target).each(function hideRows(i) {
      var truncateAfter = rowCount - 1;
      if (i == truncateAfter) {
        $(this).addClass('truncate-after');
      }
      if(i > truncateAfter && shouldTruncate) {
        $(this).hide();
        $(this).addClass('truncate-toggle');
      }
    });

    if (shouldTruncate) {
      var btn = '<button type="button" class="more-link btn' + btnSize + '">' + moreLabel + ' <i class="fa fa-arrow-down" aria-hidden="true"></i></button><button type="button" class="less-link btn' + btnSize + '">' + lessLabel + ' <i class="fa fa-arrow-up" aria-hidden="true"></i></button>';
      var btnLessTop = '<button type="button" class="less-link-top btn' + btnSize + '">' + lessLabel + ' <i class="fa fa-arrow-up" aria-hidden="true"></i></button>';
      var btnWrapper = $('<' + targetElemName + ' class="more-less-btn-wrapper"></' + targetElemName + '>');
      var btnWrapperBtm = btnWrapper.clone().append(_fill(btn));
      var btnWrapperTop = btnWrapper.clone().append(_fill(btnLessTop));

      // Attach show/hide buttons to the top and bottom or display in place
      $(btnWrapperTop).prependTo(self);
      if(inPlaceToggle) {
        $(btnWrapperBtm).insertAfter(self.find('.truncate-after'));
      } else {
        $(btnWrapperBtm).appendTo(self);
      }

      self.find('.less-link, .less-link-top').hide();

      self.find('.less-link, .less-link-top').click(function onClickLessLink(/*event*/) {
        self.find('.less-link, .less-link-top').hide();
        self.find('.more-link').show();
        self.find('.truncate-toggle').toggle();
      });

      self.find('.more-link').click(function onClickMoreLink(/*event*/) {
        $(this).hide();
        self.find('.less-link').show();
        if (numRows > topToggle) {
          self.find('.less-link-top').show();
        }
        self.find('.truncate-toggle').toggle();
      });
    }
    self.addClass('truncate-done');
    self.trigger('truncate-done', [self]);
  });
}

function recordDocReady() {
  removeCheckRouteParam();
  $('.record-tabs .nav-tabs a').click(function recordTabsClick() {
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
        window.location.hash = 'tabnav';
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
        window.location.hash = tabid;
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

  initTruncate('.truncate-subjects', $('.subject-line'));
  initTruncate('table.truncate-field', $('tr[typeof="Offer"]'), function(m) { return '<td colspan="2">' + m + '</td>'; });
  registerTabEvents();
  applyRecordTabHash();
}
