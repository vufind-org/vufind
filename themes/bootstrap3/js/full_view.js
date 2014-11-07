/*global path, registerTabEvents*/

function showhideTabs(tabid) {
  console.log(tabid);
  $('#'+tabid).parents('.search_tabs').find('.tab-pane.active').removeClass('active');
  $('#'+tabid+'-tab').addClass('active');
  $('#'+tabid).tab('show');
}

function ajaxFLLoadTab(tabid) {
  var id = $('#'+tabid).parent().parent().parent().find(".hiddenId")[0].value;
  var source = $('#'+tabid).parent().parent().parent().find(".hiddenSource")[0].value;
  if (source == 'VuFind') {
        urlroot = 'Record';
  } else {
	urlroot = source + 'record';
  }
  var tab = tabid.split('_');
  tab = tab[0];
  if($('#'+tabid+'-tab').is(':empty')) {
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
      }
    });
  } else {
    showhideTabs(tabid);
  }
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
