/*global deparam, extractClassParams, htmlEncode, Lightbox, path, syn_get_widget, vufindString */

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
  $('form[name="commentRecord"]').unbind('submit').submit(function(){
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
        console.log(response);
        var form = 'form[name="commentRecord"]';
        if (response.status == 'OK') {
          refreshCommentList(id, recordSource);
          $(form).find('textarea[name="comment"]').val('');
          $(form).find('input[type="submit"]').button('loading');
          window.location.hash = 'tabnav';
        } else {
          Lightbox.open({
            controller:'MyResearch',
            action:'UserLogin',
            title:'Login',
            onClose:function() {
              Lightbox.submit($(form), function() {
                Lightbox.close();
                refreshCommentList(id, recordSource);
                window.location.hash = 'tabnav';
              });
            }
          });
        }
      }
    });
    return false;
  });
  // Delete links
  $('.delete').click(function() {
    deleteRecordComment(this, $('.hiddenId').val(), $('.hiddenSource').val(), this.id.substr(13));
    return false;
  });
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
    return Lightbox.open({controller:'Record', action:parts[parts.length-1], get:params});
  });
}

function ajaxLoadTab(tabid) {
  var id = $('.hiddenId')[0].value;
  // Grab the part of the url that is the Controller and Record ID
  var urlroot = document.URL.match(new RegExp('/[^/]+/'+id+'(/|\\b)'));
  if(urlroot[0].substring(-1) != '/') {
    urlroot[0] += '/';
  }
  $.ajax({
    url: path + urlroot[0] + 'AjaxTab',
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
}

$(document).ready(function(){
  var id = $('.hiddenId')[0].value;
  registerTabEvents();

  $('ul.recordTabs a').click(function (e) {
    if($(this).parents('li.active').length > 0) {
      window.location.href = $(this).attr('href');
      return;
    }
    var tabid = $(this).attr('id').toLowerCase();
    if($('#'+tabid+'-tab').length > 0) {
      $('#record-tabs .tab-pane.active').removeClass('active');
      $('#'+tabid+'-tab').addClass('active');
      $('#'+tabid).tab('show');
    } else {
      $('#record-tabs').append('<div class="tab-pane" id="'+tabid+'-tab"><i class="fa fa-spinner fa-spin"></i> '+vufindString.loading+'...</div>');
      $('#record-tabs .tab-pane.active').removeClass('active');
      $('#'+tabid+'-tab').addClass('active');
      ajaxLoadTab(tabid);
    }
    return false;
  });

  /* --- LIGHTBOX --- */
  // Cite lightbox
  $('#cite-record').click(function() {
    var params = extractClassParams(this);
    return Lightbox.open({controller:params['controller'], action:'Cite', get:{id:id}});
  });
  // Mail lightbox
  $('#mail-record').click(function() {
    var params = extractClassParams(this);
    return Lightbox.open({controller:params['controller'], action:'Email', get:{id:id}});
  });
  // Save lightbox
  $('#save-record').click(function() {
    var params = extractClassParams(this);
    return Lightbox.open({controller:params['controller'], action:'Save', get:{id:id}});
  });
  // SMS lightbox
  $('#sms-record').click(function() {
    var params = extractClassParams(this);
    return Lightbox.open({controller:params['controller'], action:'SMS', get:{id:id}});
  });
  // Tag lightbox
  $('#tagRecord').click(function() {
    var id = $('.hiddenId')[0].value;
    var parts = this.href.split('/');
    Lightbox.addCloseAction(function() {
      var recordId = $('#record_id').val();
      var recordSource = $('.hiddenSource').val();

      // Update tag list (add tag)
      var tagList = $('#tagList');
      if (tagList.length > 0) {
        tagList.empty();
        var url = path + '/AJAX/JSON?' + $.param({method:'getRecordTags',id:recordId,'source':recordSource});
        $.ajax({
          dataType: 'json',
          url: url,
          success: function(response) {
            if (response.status == 'OK') {
              $.each(response.data, function(i, tag) {
                var href = path + '/Tag?' + $.param({lookfor:tag.tag});
                var html = (i>0 ? ', ' : ' ') + '<a href="' + htmlEncode(href) + '">' + htmlEncode(tag.tag) +'</a> (' + htmlEncode(tag.cnt) + ')';
                tagList.append(html);
              });
            } else if (response.data && response.data.length > 0) {
              tagList.append(response.data);
            }
          }
        });
      }
    });
    return Lightbox.open({controller:parts[parts.length-3], action:'AddTag', get:{id:id}});
  });
  // Form handlers
  Lightbox.addFormCallback('saveRecord', function() {
    Lightbox.open({confirm:vufindString['bulk_save_success']})
  });
  Lightbox.addFormCallback('smsRecord', function() {
    Lightbox.open({confirm:vufindString['sms_success']})
  });
  Lightbox.addFormCallback('emailRecord', function(){
    Lightbox.open({confirm:vufindString['bulk_email_success']});
  });
  Lightbox.addFormCallback('placeHold', function() {
    document.location.href = path+'/MyResearch/Holds';
  });
  Lightbox.addFormCallback('placeStorageRetrievalRequest', function() {
    document.location.href = path+'/MyResearch/StorageRetrievalRequests';
  });
  Lightbox.addFormCallback('placeILLRequest', function() {
    document.location.href = path+'/MyResearch/ILLRequests';
  });
});
