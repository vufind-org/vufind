/*global checkSaveStatuses, deparam, extractClassParams, htmlEncode, Lightbox, VUFIND.getPath(), syn_get_widget, userIsLoggedIn, VUFIND */

/**
 * Functions and event handlers specific to record pages.
 */
function checkRequestIsValid(element, requestType, blockedClass) {
  var recordId = element.href.match(/\/Record\/([^\/]+)\//)[1];
  var vars = deparam(element.href);
  vars['id'] = recordId;

  var url = VUFIND.getPath() + '/AJAX/JSON?' + $.param({method:'checkRequestIsValid', id: recordId, requestType: requestType, data: vars});
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
  var url = VUFIND.getPath() + '/AJAX/JSON?' + $.param({method:'deleteRecordComment',id:commentId});
  $.ajax({
    dataType: 'json',
    url: url,
    success: function(response) {
      if (response.status == 'OK') {
        $($(element).parents('.comment')[0]).remove();
      }
    }
  });
}

function refreshCommentList(recordId, recordSource, parent) {
  var url = path + '/AJAX/JSON?' + $.param({method:'getRecordCommentsAsHTML',id:recordId,'source':recordSource});
  $.ajax({
    dataType: 'json',
    url: url,
    success: function(response) {
      // Update HTML
      if (response.status == 'OK') {
        $commentList = typeof parent === "undefined" || $(parent).find('.commentList').length === 0
          ? $('.commentList')
          : $(parent).find('.commentList');
        $commentList.empty();
        $commentList.append(response.data);
        $('input[type="submit"]').button('reset');
        $('.delete').unbind('click').click(function() {
          var commentId = $(this).attr('id').substr('recordComment'.length);
          deleteRecordComment(this, recordId, recordSource, commentId);
          return false;
        });
      }
    }
  });
}

function registerAjaxCommentRecord() {
  // Form submission
  $('form.comment').unbind('submit').submit(function(){
    var form = this;
    var id = form.id.value;
    var recordSource = form.source.value;
    var url = VUFIND.getPath() + '/AJAX/JSON?' + $.param({method:'commentRecord'});
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
        if (response.status == 'OK') {
          refreshCommentList(id, recordSource, form);
          $(form).find('textarea[name="comment"]').val('');
          $(form).find('input[type="submit"]').button('loading');
        } else {
          Lightbox.displayError(response.data);
        }
      }
    });
    return false;
  });
  // Delete links
  $('.delete').click(function(){deleteRecordComment(this, $('.hiddenId').val(), $('.hiddenSource').val(), this.id.substr(13));return false;});
}

function registerRecordEvents(parent, id) {
  if(typeof parent === "undefined") {
    parent = document;
    id = $(this).closest('.record').find('.hiddenId').val();
  }
  // Cite lightbox
  $(parent).find('.cite-record').unbind('click').click(function() {
    var params = extractClassParams(this);
    return Lightbox.get(params['controller'], 'Cite', {id: id});
  });
  // Mail lightbox
  $(parent).find('.mail-record').unbind('click').click(function() {
    var params = extractClassParams(this);
    return Lightbox.get(params['controller'], 'Email', {id: id});
  });
  // Save lightbox
  $(parent).find('.save-record').unbind('click').click(function() {
    var params = extractClassParams(this);
    return Lightbox.get(params['controller'], 'Save', {id: id});
  });
  // SMS lightbox
  $(parent).find('.sms-record').unbind('click').click(function() {
    var params = extractClassParams(this);
    return Lightbox.get(params['controller'], 'SMS', {id: id});
  });
  // Tag lightbox
  $(parent).find('.tagRecord').unbind('click').click(function() {
    var parts = this.href.split('/');
    return Lightbox.get(parts[parts.length-3], 'AddTag', {id: id});
  });
  // Place a Hold
  // Place a Storage Hold
  // Place an ILL Request
  $(parent).find('.placehold,.placeStorageRetrievalRequest,.placeILLRequest').unbind('click').click(function() {
    var parts = $(this).attr('href').split('?');
    parts = parts[0].split('/');
    var params = deparam($(this).attr('href'));
    params.id = parts[parts.length-2];
    params.hashKey = params.hashKey.split('#')[0]; // Remove #tabnav
    return Lightbox.get('Record', parts[parts.length-1], params, false, function(html) {
      Lightbox.checkForError(html, Lightbox.changeContent);
    });
  });

  $(parent).find('form.comment input[type=submit]').unbind('click').click(function() {
    if($.trim($(this).siblings('textarea').val()) == '') {
      Lightbox.displayError(vufindString['add_comment_fail_blank']);
    } else {
      registerAjaxCommentRecord(parent);
    }
    return false;
  });

  refreshCommentList(id, $(parent).find('.hiddenSource').val(), parent);
  setUpCheckRequest();
}

function ajaxLoadTab(tabid) {
  var id = $('.hiddenId')[0].value;
  // Try to parse out the controller portion of the URL. If this fails, or if
  // we're flagged to skip AJAX for this tab, just return true and let the
  // browser handle it.
  var urlroot = document.URL.match(new RegExp('/[^/]+/'+id));
  if(!urlroot || document.getElementById(tabid).parentNode.className.indexOf('noajax') > -1) {
    return true;
  }
  $.ajax({
    url: VUFIND.getPath() + urlroot + '/AjaxTab',
    type: 'POST',
    data: {tab: tabid},
    success: function(data) {
      $('#record-tabs .tab-pane.active').removeClass('active');
      $('#'+tabid+'-tab').html(data).addClass('active');
      $('#'+tabid).tab('show');
      registerRecordEvents();
      if(typeof syn_get_widget === "function") {
        syn_get_widget();
      }
    }
  });
  return false;
}

function refreshTagList(loggedin) {
  loggedin = !!loggedin || userIsLoggedIn;
  var recordId = $('#record_id').val();
  var recordSource = $('.hiddenSource').val();
  var tagList = $('#tagList');
  if (tagList.length > 0) {
    tagList.empty();
    var url = VUFIND.getPath() + '/AJAX/JSON?' + $.param({method:'getRecordTags',id:recordId,'source':recordSource});
    $.ajax({
      dataType: 'json',
      url: url,
      complete: function(response) {
        if(response.status == 200) {
          tagList.html(response.responseText);
          if(loggedin) {
            $('#tagList').addClass('loggedin');
          } else {
            $('#tagList').removeClass('loggedin');
          }
        }
      }
    });
  }
}

function ajaxTagUpdate(tag, remove) {
  if(typeof remove === "undefined") {
    remove = false;
  }
  var recordId = $('#record_id').val();
  var recordSource = $('.hiddenSource').val();
  $.ajax({
    url:VUFIND.getPath+'/AJAX/JSON?method=tagRecord',
    method:'POST',
    data:{
      tag:'"'+tag.replace(/\+/g, ' ')+'"',
      id:recordId,
      source:recordSource,
      remove:remove
    },
    complete:refreshTagList
  });
}

function recordDocReady() {
  var id = $('.hiddenId')[0].value;
  registerRecordEvents(document, id);
  refreshCommentList(id, $('.hiddenSource').val());

  $('ul.recordTabs a').click(function (e) {
    if($(this).parents('li.active').length > 0) {
      return true;
    }
    var tabid = $(this).attr('id').toLowerCase();
    if($('#'+tabid+'-tab').length > 0) {
      $('#record-tabs .tab-pane.active').removeClass('active');
      $('#'+tabid+'-tab').addClass('active');
      $('#'+tabid).tab('show');
      return false;
    } else {
      $('#record-tabs').append('<div class="tab-pane" id="'+tabid+'-tab"><i class="fa fa-spinner fa-spin"></i> '+VUFIND.translate('loading')+'...</div>');
      $('#record-tabs .tab-pane.active').removeClass('active');
      $('#'+tabid+'-tab').addClass('active');
      return ajaxLoadTab(tabid);
    }
  });
});
