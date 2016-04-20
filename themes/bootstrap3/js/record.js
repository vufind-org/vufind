/*global checkSaveStatuses, deparam, extractClassParams, getListUrlFromHTML, htmlEncode, Lightbox, syn_get_widget, userIsLoggedIn, VuFind */

/**
 * Functions and event handlers specific to record pages.
 */
function checkRequestIsValid(element, requestType) {
  var recordId = element.href.match(/\/Record\/([^\/]+)\//)[1];
  var vars = deparam(element.href);
  vars['id'] = recordId;

  var url = VuFind.path + '/AJAX/JSON?' + $.param({method:'checkRequestIsValid', id: recordId, requestType: requestType, data: vars});
  $(element).find('i.fa').removeClass('fa-flag').addClass('fa-spinner fa-spin');
  $.ajax({
    dataType: 'json',
    cache: false,
    url: url
  })
  .done(function(response) {
    if (response.data.status) {
      $(element)
        .attr('title', response.data.msg)
        .html('<i class="fa fa-flag"></i>&nbsp;'+response.data.msg);
    } else {
      $(element).remove();
    }
  })
  .fail(function(response) {
    $(element).remove();
  });
}

function setUpCheckRequest() {
  $('.checkRequest').each(function(i) {
    checkRequestIsValid(this, 'Hold');
  });
  $('.checkStorageRetrievalRequest').each(function(i) {
    checkRequestIsValid(this, 'StorageRetrievalRequest');
  });
  $('.checkILLRequest').each(function(i) {
    checkRequestIsValid(this, 'ILLRequest');
  });
}

function deleteRecordComment(element, recordId, recordSource, commentId) {
  var url = VuFind.path + '/AJAX/JSON?' + $.param({method:'deleteRecordComment',id:commentId});
  $.ajax({
    dataType: 'json',
    url: url
  })
  .done(function(response) {
    $($(element).closest('.comment')[0]).remove();
  });
}

function refreshCommentList($target, recordId, recordSource) {
  var url = VuFind.path + '/AJAX/JSON?' + $.param({method:'getRecordCommentsAsHTML',id:recordId,'source':recordSource});
  $.ajax({
    dataType: 'json',
    url: url
  })
  .done(function(response) {
    // Update HTML
    var $commentList = $target.find('.comment-list');
    $commentList.empty();
    $commentList.append(response.data);
    $commentList.find('.delete').unbind('click').click(function() {
      var commentId = $(this).attr('id').substr('recordComment'.length);
      deleteRecordComment(this, recordId, recordSource, commentId);
      return false;
    });
    $target.find('.comment-form input[type="submit"]').button('reset');
  });
}

function registerAjaxCommentRecord() {
  // Form submission
  $('form.comment-form').unbind('submit').submit(function() {
    var form = this;
    var id = form.id.value;
    var recordSource = form.source.value;
    var url = VuFind.path + '/AJAX/JSON?' + $.param({method:'commentRecord'});
    var data = {
      comment:form.comment.value,
      id:id,
      source:recordSource
    };
    $.ajax({
      type: 'POST',
      url:  url,
      data: data,
      dataType: 'json'
    })
    .done(function(response) {
      var $tab = $(form).closest('.tab-pane');
      refreshCommentList($tab, id, recordSource);
      $(form).find('textarea[name="comment"]').val('');
      $(form).find('input[type="submit"]').button('loading');
    })
    .fail(function(response, textStatus) {
      if (textStatus == 'abort' || typeof response.responseJSON === 'undefined') { return; }
      VuFind.lightbox.update(response.responseJSON.data);
    });
    return false;
  });
  // Delete links
  $('.delete').click(function() {
    var commentId = this.id.substr('recordComment'.length);
    deleteRecordComment(this, $('.hiddenId').val(), $('.hiddenSource').val(), commentId);
    return false;
  });
  // Prevent form submit
  return false;
}

function registerTabEvents() {
  // Logged in AJAX
  registerAjaxCommentRecord();
  // Delete links
  $('.delete').click(function(){deleteRecordComment(this, $('.hiddenId').val(), $('.hiddenSource').val(), this.id.substr(13));return false;});

  setUpCheckRequest();

  VuFind.lightbox.bind('.tab-pane.active');
}

function ajaxLoadTab($newTab, tabid, setHash) {
  // Parse out the base URL for the current record:
  var urlParts = document.URL.split(/[?#]/);
  var urlWithoutFragment = urlParts[0];
  var path = VuFind.path;
  var urlroot = null;
  if (path === '') {
    // special case -- VuFind installed at site root:
    var chunks = urlWithoutFragment.split('/');
    urlroot = '/' + chunks[3] + '/' + chunks[4];
  } else {
    // standard case -- VuFind has its own path under site:
    var pathInUrl = urlWithoutFragment.indexOf(path);
    var parts = urlWithoutFragment.substring(pathInUrl + path.length + 1).split('/');
    urlroot = '/' + parts[0] + '/' + parts[1];
  }

  // Request the tab via AJAX:
  $.ajax({
    url: path + urlroot + '/AjaxTab',
    type: 'POST',
    data: {tab: tabid}
  })
  .done(function(data) {
    $newTab.html(data);
    registerTabEvents();
    if(typeof syn_get_widget === "function") {
      syn_get_widget();
    }
    if (typeof setHash == 'undefined' || setHash) {
      window.location.hash = tabid;
    }
  });
  return false;
}

function refreshTagList(target, loggedin) {
  loggedin = !!loggedin || userIsLoggedIn;
  if (typeof target === 'undefined') {
    target = document;
  }
  var recordId = $(target).find('.hiddenId').val();
  var recordSource = $(target).find('.hiddenSource').val();
  var $tagList = $(target).find('.tagList');
  if ($tagList.length > 0) {
    var url = VuFind.path + '/AJAX/JSON?' + $.param({method:'getRecordTags',id:recordId,'source':recordSource});
    $.ajax({
      dataType: 'html',
      url: url
    })
    .done(function(response) {
      $tagList.empty();
      $tagList.replaceWith(response);
      if(loggedin) {
        $tagList.addClass('loggedin');
      } else {
        $tagList.removeClass('loggedin');
      }
    });
  }
}

function ajaxTagUpdate(link, tag, remove) {
  if(typeof link === "undefined") {
    link = document;
  }
  if(typeof remove === "undefined") {
    remove = false;
  }
  var $target = $(link).closest('.record');
  var recordId = $target.find('.hiddenId').val();
  var recordSource = $target.find('.hiddenSource').val();
  $.ajax({
    url:VuFind.path + '/AJAX/JSON?method=tagRecord',
    method:'POST',
    data:{
      tag:'"'+tag.replace(/\+/g, ' ')+'"',
      id:recordId,
      source:recordSource,
      remove:remove
    }
  })
  .always(function() {
    refreshTagList($target, false);
  });
}

function applyRecordTabHash() {
  var activeTab = $('.record-tabs li.active a').attr('class');
  var $initiallyActiveTab = $('.record-tabs li.initiallyActive a');
  var newTab = typeof window.location.hash !== 'undefined'
    ? window.location.hash.toLowerCase() : '';

  // Open tag in url hash
  if (newTab.length == 0 || newTab == '#tabnav') {
    $initiallyActiveTab.click();
  } else if (newTab.length > 0 && '#' + activeTab != newTab) {
    $('.'+newTab.substr(1)).click();
  }
}

$(window).on('hashchange', applyRecordTabHash);

function recordDocReady() {
  $('.record-tabs .nav-tabs a').click(function (e) {
    var $li = $(this).parent();
    // If it's an active tab, click again to follow to a shareable link.
    // if we're flagged to skip AJAX for this tab, just return true and let the browser handle it.
    if($li.hasClass('active') || $li.hasClass('noajax')) {
      return true;
    }
    var tabid = this.className;
    var $top = $(this).closest('.record-tabs');
    $top.find('.tab-pane.active').removeClass('active');
    $(this).tab('show');
    if ($top.find('.'+tabid+'-tab').length > 0) {
      $top.find('.'+tabid+'-tab').addClass('active');
      window.location.hash = tabid;
      return false;
    } else {
      var newTab = $('<div class="tab-pane active '+tabid+'-tab"><i class="fa fa-spinner fa-spin"></i> '+VuFind.translate('loading')+'...</div>');
      $top.find('.tab-content').append(newTab);
      return ajaxLoadTab(newTab, tabid, !$(this).parent().hasClass('initiallyActive'));
    }
  });

  registerTabEvents();
  applyRecordTabHash();
}
