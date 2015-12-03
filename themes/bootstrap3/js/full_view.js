/*global registerAjaxCommentRecord, registerTabEvents, setupRecordToolbar, VuFind */

function showhideTabs(tabid) {
  //console.log(tabid);
  $('#'+tabid).parents('.search_tabs').find('.tab-pane.active').removeClass('active');
  $('#'+tabid+'-tab').addClass('active');
  $('#'+tabid).tab('show');
}

function ajaxFLLoadTab(tabid, reload) {
  if(typeof reload === "undefined") {
    reload = false;
  }
  if($('#'+tabid).parent().hasClass('noajax')) {
    window.location.href = $('#'+tabid).attr('href');
    return true;
  }
  var id = $('#'+tabid).closest('.record').find(".hiddenId")[0].value;
  var source = $('#'+tabid).closest('.record').find(".hiddenSource")[0].value;
  var urlroot;
  if (source == 'VuFind') {
    urlroot = 'Record';
  } else {
    urlroot = source + 'record';
  }
  var tab = tabid.split('_');
  tab = tab[0];
  if(reload || $('#'+tabid+'-tab').is(':empty')) {
    showhideTabs(tabid);
    $('#'+tabid+'-tab').html('<i class="fa fa-spinner fa-spin"></i> '+VuFind.translate('loading')+'...');
    $.ajax({
      url: VuFind.getPath() + '/' + urlroot + '/' + id + '/AjaxTab',
      type: 'POST',
      data: {tab: tab},
      success: function(data) {
        if (data.length > 0) {
          $('#'+tabid+'-tab').html(data);
          registerTabEvents();
        }
        if(typeof syn_get_widget === "function") {
          syn_get_widget();
        }
      }
    });
  } else {
    showhideTabs(tabid);
  }
  return false;
}

$(document).ready(function() {
  $('.getFull').click(function(type) {
    var mainNode = $(this).closest('.result');
    var div_id = mainNode.find(".hiddenId")[0].value;
    var div_source = mainNode.find(".hiddenSource")[0].value;
    var div_html_id = div_id.replace(/\W/g, "_");
    var viewType = $(this).attr("data-view");
    var shortNode = mainNode.find('.short-view');
    var loadingNode = mainNode.find('.loading');
    var longNode = mainNode.find('.long-view');
    if (!longNode.is(":visible")) {
      shortNode.addClass("hidden");
      longNode.removeClass("hidden");
      if (longNode.is(':empty')) {
        loadingNode.removeClass("hidden");
        var url = VuFind.getPath() + '/AJAX/JSON?' + $.param({method:'getRecordDetails',id:div_id,type:viewType,source:div_source});
        $.ajax({
          dataType: 'json',
          url: url,
          success: function(response) {
            if (response.status == 'OK') {
              // Insert tabs html
              longNode.html(response.data);
              // Hide loading
              loadingNode.addClass("hidden");
              // Load first tab
              ajaxFLLoadTab($(longNode).find('.recordTabs li.active a').attr('id'));
              // Add events to record toolbar
              setupRecordToolbar(longNode, div_id);
              setupModalLinkTitles(longNode);
              // Lightbox handler for tagRecord
              Lightbox.addFormCallback('tagRecord', function() {
                refreshTagList(true, longNode);
                Lightbox.confirm(VuFind.translate('add_tag_success'));
              });
              longNode.find('.search_tabs .recordTabs a').click(function() {
                return ajaxFLLoadTab($(this).attr('id'));
              });
              longNode.find('[id^=usercomment]').find('input[type=submit]').unbind('click').click(function() {
                return registerAjaxCommentRecord(
                  longNode.find('[id^=usercomment]').find('input[type=submit]').closest('form')
                );
              });
            }
          }
        });
      }
    } else {
      longNode.addClass("hidden");
      shortNode.removeClass("hidden");
    }
    return false;
  });
});
