/*global path, registerTabEvents*/

function showhideTabs(tabid) {
  //console.log(tabid);
  $('#'+tabid).parent().parent().find('.active').removeClass('active');
  $('#'+tabid).parent().addClass('active');
  $('.recordsubcontent > div:visible').hide();
  $('#'+tabid+'-content').show();
}

function ajaxFLLoadTab(tabid) {
  //console.log(tabid);
  var $parent = $('#'+tabid).parent().parent().parent().parent();
  var id = $parent.find(".hiddenId")[0].value;
  var source = $parent.find(".hiddenSource")[0].value;
  if (source == 'VuFind') {
        urlroot = 'Record';
  } else {
	urlroot = source + 'record';
  }
  var tab = tabid.split('_');
  tab = tab[0];
  if($('#'+tabid+'-content').length > 0) {
    showhideTabs(tabid);
  } else {
    $.ajax({
      url: path + '/' + urlroot + '/' + id + '/AjaxTab',
      type: 'POST',
      data: {tab: tab},
      success: function(data) {
        $parent.find('.recordsubcontent').append('<div id="'+tabid+'-content">'+data+'</div>');
        showhideTabs(tabid);
        if(typeof syn_get_widget === "function") {
          syn_get_widget();
        }
      }
    });
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
    if (longNode.is(':empty')) {
      loadingNode.show();
      var url = path + '/AJAX/JSON?' + $.param({method:'getRecordDetails',id:div_id,type:viewType,source:div_source});
      $.ajax({
        dataType: 'json',
        url: url,
        success: function(response) {
          if (response.status == 'OK') {
            longNode.html(response.data);
            longNode.addClass("ajaxItem");
            longNode.show();
            loadingNode.hide();
            shortNode.hide();
            $('.search_tabs .recordTabs a').unbind('click').click(function() {
              return ajaxFLLoadTab($(this).attr('id'));
            });
            $('.accordion_group dt').click(function() {
              if($(this).parent().hasClass('open')) {
                $(this).closest('.navmenu').removeClass('open');
              } else {
                $(this).closest('.accordion_group').find('.navmenu.open').removeClass('open');
                $(this).parent().addClass('open');
              }
            });
          }
        }
      });
    } else if (!longNode.is(":visible")) {
      shortNode.hide();
      longNode.show();
    } else {
      longNode.hide();
      shortNode.show();
    }
    return false;
  });
});
