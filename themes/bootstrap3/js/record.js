/*global checkSaveStatuses, deparam, extractClassParams, htmlEncode, Lightbox, path, syn_get_widget, userIsLoggedIn, vufindString */

/**
 * Functions and event handlers specific to record pages.
 */
function checkRequestIsValid(element, requestURL, requestType, blockedClass) {
  var recordId = requestURL.match(/\/Record\/([^\/]+)\//)[1];
  var vars = {}, hash;
  var hashes = requestURL.slice(requestURL.indexOf('?') + 1).split('&');

  for(var i = 0; i < hashes.length; i++)
  {
    hash = hashes[i].split('=');
    var x = hash[0];
    var y = hash[1];
    vars[x] = y;
  }
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
    if ($(this).hasClass('checkRequest')) {
      var isValid = checkRequestIsValid(this, this.href, 'Hold', 'holdBlocked');
    }
  });
  $('.checkStorageRetrievalRequest').each(function(i) {
    if ($(this).hasClass('checkStorageRetrievalRequest')) {
      var isValid = checkRequestIsValid(this, this.href, 'StorageRetrievalRequest',
          'StorageRetrievalRequestBlocked');
    }
  });
  $('.checkILLRequest').each(function(i) {
    if ($(this).hasClass('checkILLRequest')) {
      var isValid = checkRequestIsValid(this, this.href, 'ILLRequest',
          'ILLRequestBlocked');
    }
  });
}

function deleteRecordComment(element, recordId, recordSource, commentId) {
  var url = path + '/AJAX/JSON?' + $.param({method:'deleteRecordComment',id:commentId});
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

function refreshCommentList(recordId, recordSource) {
  var url = path + '/AJAX/JSON?' + $.param({method:'getRecordCommentsAsHTML',id:recordId,'source':recordSource});
  $.ajax({
    dataType: 'json',
    url: url,
    success: function(response) {
      // Update HTML
      if (response.status == 'OK') {
        $('#commentList').empty();
        $('#commentList').append(response.data);
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
        if (response.status == 'OK') {
          refreshCommentList(id, recordSource);
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

function registerTabEvents() {
  // register the record comment form to be submitted via AJAX
  registerAjaxCommentRecord();

  setUpCheckRequest();

  // Place a Hold
  // Place a Storage Hold
  // Place an ILL Request
  $('.placehold,.placeStorageRetrievalRequest,.placeILLRequest').click(function() {
    var parts = $(this).attr('href').split('?');
    parts = parts[0].split('/');
    var params = deparam($(this).attr('href'));
    params.id = parts[parts.length-2];
    params.hashKey = params.hashKey.split('#')[0]; // Remove #tabnav
    return Lightbox.get('Record', parts[parts.length-1], params, false, function(html) {
      Lightbox.checkForError(html, Lightbox.changeContent);
    });
  });
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
    url: path + urlroot + '/AjaxTab',
    type: 'POST',
    data: {tab: tabid},
    success: function(data) {
      $('#record-tabs .tab-pane.active').removeClass('active');
      $('#'+tabid+'-tab').html(data).addClass('active');
      $('#'+tabid).tab('show');
      registerTabEvents();
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
    var url = path + '/AJAX/JSON?' + $.param({method:'getRecordTags',id:recordId,'source':recordSource});
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
    url:path+'/AJAX/JSON?method=tagRecord',
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

$(document).ready(function(){
  var id = $('.hiddenId')[0].value;
  registerTabEvents();

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
      $('#record-tabs').append('<div class="tab-pane" id="'+tabid+'-tab"><i class="fa fa-spinner fa-spin"></i> '+vufindString['loading']+'...</div>');
      $('#record-tabs .tab-pane.active').removeClass('active');
      $('#'+tabid+'-tab').addClass('active');
      return ajaxLoadTab(tabid);
    }
  });

  /* --- LIGHTBOX --- */
  // Cite lightbox
  $('#cite-record').click(function() {
    var params = extractClassParams(this);
    return Lightbox.get(params['controller'], 'Cite', {id:id});
  });
  // Mail lightbox
  $('#mail-record').click(function() {
    var params = extractClassParams(this);
    return Lightbox.get(params['controller'], 'Email', {id:id});
  });
  // Save lightbox
  $('#save-record').click(function() {
    var params = extractClassParams(this);
    return Lightbox.get(params['controller'], 'Save', {id:id});
  });
  // SMS lightbox
  $('#sms-record').click(function() {
    var params = extractClassParams(this);
    return Lightbox.get(params['controller'], 'SMS', {id:id});
  });
  $('#tagRecord').click(function() {
    var id = $('.hiddenId')[0].value;
    var parts = this.href.split('/');
    return Lightbox.get(parts[parts.length-3],'AddTag',{id:id});
  });
  // Form handlers
  Lightbox.addFormCallback('emailRecord', function(){
    Lightbox.confirm(vufindString['bulk_email_success']);
  });
  Lightbox.addFormCallback('placeHold', function(html) {
    Lightbox.checkForError(html, function(html) {
      var divPattern = '<div class="alert alert-info">';
      var fi = html.indexOf(divPattern);
      var li = html.indexOf('</div>', fi+divPattern.length);
      Lightbox.confirm(html.substring(fi+divPattern.length, li).replace(/^[\s<>]+|[\s<>]+$/g, ''));
    });
  });
  Lightbox.addFormCallback('placeILLRequest', function() {
    document.location.href = path+'/MyResearch/ILLRequests';
  });
  Lightbox.addFormCallback('placeStorageRetrievalRequest', function() {
    document.location.href = path+'/MyResearch/StorageRetrievalRequests';
  });
  Lightbox.addFormCallback('saveRecord', function() {
    checkSaveStatuses();
    refreshTagList();
    Lightbox.confirm(vufindString['bulk_save_success']);
  });
  Lightbox.addFormCallback('smsRecord', function() {
    Lightbox.confirm(vufindString['sms_success']);
  });
  // Tag lightbox
  Lightbox.addFormCallback('tagRecord', function(html) {
    refreshTagList(true);
    Lightbox.confirm(vufindString['add_tag_success']);
  });
});
