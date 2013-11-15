/*global extractClassParams, getLightbox, path */

/**
 * Functions and event handlers specific to record pages.
 */

function checkRequestIsValid(element, requestURL) {
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

  var url = path + '/AJAX/JSON?' + $.param({method:'checkRequestIsValid', id: recordId, data: vars});
  $.ajax({
    dataType: 'json',
    cache: false,
    url: url,
    success: function(response) {
      if (response.status == 'OK') {
        if (response.data.status) {
          $(element).removeClass('disabled').html(response.data.msg);
        } else {
          $(element).remove();
        }
      } else if (response.status == 'NEED_AUTH') {
        $(element).replaceWith('<span class="holdBlocked">' + response.data.msg + '</span>');
      }
    }
  });
}

function setUpCheckRequest() {
  $('.checkRequest').each(function(i) {
    if($(this).hasClass('checkRequest')) {
      var isValid = checkRequestIsValid(this, this.href);
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
      if (response.status == 'OK') {
        $('#commentList').empty();
        $('#commentList').append(response.data);
        $('input[type="submit"]').button('reset');
        $('#commentList a.deleteRecordComment').unbind('click').click(function() {
          var commentId = $(this).attr('id').substr('recordComment'.length);
          deleteRecordComment(this, recordId, recordSource, commentId);
          return false;
        });
      }
    }
  });
}

function registerAjaxCommentRecord() {
  // Delete links
  $('.delete').click(function(){deleteRecordComment(this, id, $('.hiddenSource').val(), this.id.substr(13));return false});
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
        var form = 'form[name="commentRecord"]';
        if (response.status == 'OK') {
          refreshCommentList(id, recordSource);
          $(form).find('textarea[name="comment"]').val('');
        } else if (response.status == 'NEED_AUTH') {
          data['loggingin'] = true;
          return getLightbox(
            'Record', 'AddComment', data, data,
            function(){
              closeLightbox();
              $.ajax({type:'POST',url:url,data:data});
              refreshCommentList(id, recordSource);
            }
          );
        } else {
          $('#modal').find('.modal-body').html(response.data+'!');
          $('#modal').find('.modal-header h3').html('Error!');
          $('#modal').modal('show');
        }
      }
    });
    $(form).find('input[type="submit"]').button('loading');
    return false;
  });
}
$(document).ready(function(){
  var id = document.getElementById('record_id').value;
  
  // Cite lightbox
  $('#cite-record').click(function() {
    var params = extractClassParams(this);
    return getLightbox(params['controller'], 'Cite', {id:id});
  });
  // SMS lightbox
  $('#sms-record').click(function() {
    var params = extractClassParams(this);
    return getLightbox(params['controller'], 'SMS', {id:id});
  });
  // Mail lightbox
  $('#mail-record').click(function() {
    var params = extractClassParams(this);
    return getLightbox(params['controller'], 'Email', {id:id});
  });
  // Save lightbox
  $('#save-record').click(function() {
    var params = extractClassParams(this);
    return getLightbox(params['controller'], 'Save', {id:id});
  });
  
  // register the record comment form to be submitted via AJAX
  registerAjaxCommentRecord();
  
  setUpCheckRequest();
});
