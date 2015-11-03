/*global checkSaveStatuses, deparam, extractClassParams, htmlEncode, Lightbox, path, syn_get_widget, userIsLoggedIn, vufindString */

function ajaxLoadTab(tabid) {
  // if we're flagged to skip AJAX for this tab, just return true and let the
  // browser handle it.
  if(document.getElementById(tabid).parentNode.className.indexOf('noajax') > -1) {
    return true;
  }

  // Parse out the base URL for the current record:
  var urlParts = document.URL.split('#');
  var urlWithoutFragment = urlParts[0];
  var pathInUrl = urlWithoutFragment.indexOf(path);
  var chunks = urlWithoutFragment.substring(pathInUrl + path.length + 1).split('/');
  var urlroot = '/' + chunks[0] + '/' + chunks[1];

  // Request the tab via AJAX:
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
      window.location.hash = tabid;
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
          tagList.replaceWith(response.responseText);
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

function applyRecordTabHash()
{
  var activeTab = $('ul.recordTabs li.active a').attr('id');
  var initiallyActiveTab = $('ul.recordTabs li.initiallyActive a').attr('id');
  var newTab = typeof window.location.hash !== 'undefined'
    ? window.location.hash.toLowerCase() : '';

  // Open tag in url hash
  if (newTab.length == 0 || newTab == '#tabnav') {
    $('#' + initiallyActiveTab).click();
  } else if (newTab.length > 0 && '#' + activeTab != newTab) {
    $(newTab).click();
  }
}

$(window).on('hashchange', applyRecordTabHash);

$(document).ready(function(){
  var id = $('.hiddenId')[0].value;
  registerTabEvents();
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
      window.location.hash = tabid;
      return false;
    } else {
      $('#record-tabs').append('<div class="tab-pane" id="'+tabid+'-tab"><i class="fa fa-spinner fa-spin"></i> '+vufindString['loading']+'...</div>');
      $('#record-tabs .tab-pane.active').removeClass('active');
      $('#'+tabid+'-tab').addClass('active');
      return ajaxLoadTab(tabid);
    }
  });
  applyRecordTabHash();

  /* --- LIGHTBOX --- */
  registerLightboxRecordActions(document, id);
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
