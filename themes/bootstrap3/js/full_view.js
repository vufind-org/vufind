/*global path, registerTabEvents*/

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
  var id = $('#'+tabid).parent().parent().parent().find(".hiddenId")[0].value;
  var source = $('#'+tabid).parent().parent().parent().find(".hiddenSource")[0].value;
  if (source == 'VuFind') {
        urlroot = 'Record';
  } else {
	urlroot = source + 'record';
  }
  var tab = tabid.split('_');
  tab = tab[0];
  if(reload || $('#'+tabid+'-tab').is(':empty')) {
    $.ajax({
      url: path + '/' + urlroot + '/' + id + '/AjaxTab',
      type: 'POST',
      data: {tab: tab},
      success: function(data) {
        $('#'+tabid+'-tab').html(data);
        showhideTabs(tabid);
        if(typeof syn_get_widget === "function") {
          syn_get_widget();
        }
        if(tabid.substring(0, 12) == 'usercomments') {
          $('#'+tabid+'-tab input[type=submit]').unbind('click').click(function() {
            var form = $(this).closest('form')[0];
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
                var $form = $('#'+tabid).closest('form');
                if (response.status == 'OK') {
                  refreshCommentList(id, recordSource);
                  $form.find('textarea[name="comment"]').val('');
                  $form.find('input[type="submit"]').button('loading');
                } else {
                  Lightbox.displayError(response.data);
                }
              }
            })
            return false;
          });
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
    var div_id = $(this).parent().parent().find(".hiddenId")[0].value;
    var div_source = $(this).parent().parent().find(".hiddenSource")[0].value;
    var div_html_id = div_id.replace(/\W/g, "_");
    var viewType = $(this).attr("data-view");
    var shortNode = jQuery('#short_'+div_html_id);
    var mainNode = shortNode.parent();
    var longNode = jQuery('#long_'+div_html_id);
    if (longNode.is(':empty')) {
      var url = path + '/AJAX/JSON?' + $.param({method:'getRecordDetails',id:div_id,type:viewType,source:div_source});
      $.ajax({
        dataType: 'json',
        url: url,
        success: function(response) {
          if (response.status == 'OK') {
            shortNode.addClass("hidden");
            longNode.html(response.data);
            longNode.addClass("ajaxItem");
            longNode.removeClass("hidden");
            $('.search_tabs .recordTabs a').unbind('click').click(function() {
              return ajaxFLLoadTab($(this).attr('id'));
            });
          }
        }
      });
    } else if (!longNode.is(":visible")) {
      shortNode.addClass("hidden");
      longNode.removeClass("hidden");
    } else {
      longNode.addClass("hidden");
      shortNode.removeClass("hidden");
    }
    return false;
  });
});
