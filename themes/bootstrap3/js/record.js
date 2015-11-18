/*global deparam, path, syn_get_widget, userIsLoggedIn, vufindString */

/**
 * Functions and event handlers specific to record pages.
 */
function checkRequestIsValid(element, requestType, blockedClass) {
  var recordId = element.href.match(/\/Record\/([^\/]+)\//)[1];
  var vars = deparam(element.href);
  vars['id'] = recordId;

  var url = path + '/AJAX/JSON?' + $.param({method:'checkRequestIsValid', id: recordId, requestType: requestType, data: vars});
  $.ajax({
    dataType: 'json',
    cache: false,
    url: url,
    success: function(response) {
      if (response.status == 'OK') {
        if (response.data.status) {
          $(element).removeClass('disabled')
            .attr('title', response.data.msg)
            .html('<i class="fa fa-flag"></i>&nbsp;'+response.data.msg);
        } else {
          $(element).remove();
        }
      } else if (response.status == 'NEED_AUTH') {
        $(element).replaceWith('<span class="' + blockedClass + '">' + response.data.msg + '</span>');
      }
    }
  });
}

function setUpCheckRequest() {
  $('.checkRequest').each(function(i) {
    checkRequestIsValid(this, 'Hold', 'holdBlocked');
  });
  $('.checkStorageRetrievalRequest').each(function(i) {
    checkRequestIsValid(this, 'StorageRetrievalRequest', 'StorageRetrievalRequestBlocked');
  });
  $('.checkILLRequest').each(function(i) {
    checkRequestIsValid(this, 'ILLRequest', 'ILLRequestBlocked');
  });
}

function deleteRecordComment(element, recordId, recordSource, commentId) {
  var url = path + '/AJAX/JSON?' + $.param({method:'deleteRecordComment',id:commentId});
  $.ajax({
    dataType: 'json',
    url: url,
    success: function(response) {
      if (response.status == 'OK') {
        $($(element).closest('.comment')[0]).remove();
      }
    }
  });
}

function refreshCommentList($target, recordId, recordSource) {
  var url = path + '/AJAX/JSON?' + $.param({method:'getRecordCommentsAsHTML',id:recordId,'source':recordSource});
  $.ajax({
    dataType: 'json',
    url: url,
    success: function(response) {
      // Update HTML
      if (response.status == 'OK') {
        var $commentList = $target.find('.comment-list');
        $commentList.empty();
        $commentList.append(response.data);
        $commentList.find('.delete').unbind('click').click(function() {
          var commentId = $(this).attr('id').substr('recordComment'.length);
          deleteRecordComment(this, recordId, recordSource, commentId);
          return false;
        });
        $target.find('.comment-form input[type="submit"]').button('reset');
      }
    }
  });
}

function commentLoginCallback() {
  var recordId = $('#record_id').val();
  var recordSource = $('.hiddenSource').val();
  setTimeout('refreshCommentList("'+recordId+'", "'+recordSource+'")', 500);
  $('#modal').modal('hide');
}

function registerAjaxCommentRecord() {
  // Form submission
  var form = this;
  var id = form.id.value;
  var recordSource = form.source.value;
  var url = path + '/AJAX/JSON?' + $.param({method:'commentRecord'});
  var data = {
    comment:form.comment.value,
    id:id,
    source:recordSource
  };
  $.ajax({
    type: 'POST',
    url:  url,
    data: data,
    dataType: 'json',
    success: function(response) {
      var form = 'form[name="commentRecord"]';
      if (response.status == 'OK') {
        refreshCommentList(id, recordSource);
        $(form).find('textarea[name="comment"]').val('');
        $(form).find('input[type="submit"]').button('loading');
      }
    }
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
  $('form[name="commentRecord"]').unbind('submit').submit(registerAjaxCommentRecord);
  // Delete links
  $('.delete').click(function(){deleteRecordComment(this, $('.hiddenId').val(), $('.hiddenSource').val(), this.id.substr(13));return false;});

  setUpCheckRequest();

  constrainForms('form[data-lightbox]');
}

function ajaxLoadTab($newTab, tabid, setHash) {
  // Parse out the base URL for the current record:
  var urlParts = document.URL.split(/[?#]/);
  var urlWithoutFragment = urlParts[0];
  if (path == '') {
    // special case -- VuFind installed at site root:
    var chunks = urlWithoutFragment.split('/');
    var urlroot = '/' + chunks[3] + '/' + chunks[4];
  } else {
    // standard case -- VuFind has its own path under site:
    var pathInUrl = urlWithoutFragment.indexOf(path);
    var chunks = urlWithoutFragment.substring(pathInUrl + path.length + 1).split('/');
    var urlroot = '/' + chunks[0] + '/' + chunks[1];
  }

  // Request the tab via AJAX:
  $.ajax({
    url: path + urlroot + '/AjaxTab',
    type: 'POST',
    data: {tab: tabid},
    success: function(data) {
      $newTab.html(data).addClass('active');
      $newTab.closest('.record-tabs').find('.'+tabid).tab('show');
      registerTabEvents();
      if(typeof syn_get_widget === "function") {
        syn_get_widget();
      }
      if (typeof setHash == 'undefined' || setHash) {
        window.location.hash = tabid;
      }
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
    $tagList.empty();
    var url = path + '/AJAX/JSON?' + $.param({method:'getRecordTags',id:recordId,'source':recordSource});
    $.ajax({
      dataType: 'json',
      url: url,
      complete: function(response) {
        if(response.status == 200) {
          $tagList.replaceWith(response.responseText);
          if(loggedin) {
            $tagList.addClass('loggedin');
          } else {
            $tagList.removeClass('loggedin');
          }
        }
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
    url:path+'/AJAX/JSON?method=tagRecord',
    method:'POST',
    data:{
      tag:'"'+tag.replace(/\+/g, ' ')+'"',
      id:recordId,
      source:recordSource,
      remove:remove
    },
    complete: function() {
      refreshTagList($target, false);
    }
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
    $(newTab).click();
  }
}

$(window).on('hashchange', applyRecordTabHash);

function recordDocReady() {
  var id = $('.hiddenId')[0].value;
  registerTabEvents();

  $('.record-tabs .nav-tabs a').click(function (e) {
    if ($(this.parentNode).hasClass('active')) {
      return true;
    }
    var tabid = this.className;
    var $top = $(this).closest('.record-tabs');
    $top.find('.tab-pane.active').removeClass('active');
    if ($top.find('.'+tabid+'-tab').length > 0) {
      $top.find('.'+tabid+'-tab').addClass('active');
      $(this).tab('show');
      window.location.hash = tabid;
      return false;
    } else {
      // if we're flagged to skip AJAX for this tab, just return true and let the browser handle it.
      if ($(this.parentNode).hasClass('noajax')) {
        return true;
      }
      var newTab = $('<div class="tab-pane active '+tabid+'-tab"><i class="fa fa-spinner fa-spin"></i> '+vufindString['loading']+'...</div>');
      $top.find('.tab-content').append(newTab);
      return ajaxLoadTab(newTab, tabid);
    }
  });
  applyRecordTabHash();
}
