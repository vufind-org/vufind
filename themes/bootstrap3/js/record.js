/*global checkSaveStatuses, deparam, extractClassParams, htmlEncode, Lightbox, path, syn_get_widget, userIsLoggedIn, vufindString */

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
      return false;
    } else {
      $('#record-tabs').append('<div class="tab-pane" id="'+tabid+'-tab"><i class="fa fa-spinner fa-spin"></i> '+vufindString['loading']+'...</div>');
      $('#record-tabs .tab-pane.active').removeClass('active');
      $('#'+tabid+'-tab').addClass('active');
      return ajaxLoadTab(tabid);
    }
  });

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
