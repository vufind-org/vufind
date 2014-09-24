/*global path, registerTabEvents*/

function ajaxLoadTab(tabid) {
  var id = $('#'+tabid).parent().parent().parent().find(".hiddenId")[0].value;
  console.log(tabid);
  var tab = tabid.split('_');
  tab = tab[0];
  if($('#'+tabid+'-tab').is(':empty')) {
    $.ajax({
      url: path + '/Record/'+id+'/AjaxTab',
      type: 'POST',
      data: {tab: tab},
      success: function(data) {
        $('#'+tabid).parent().parent().find('#record-tabs .tab-pane.active').removeClass('active');
        $('#'+tabid+'-tab').html(data).addClass('active');
        $('#'+tabid).tab('show');
        if(typeof syn_get_widget === "function") {
          syn_get_widget();
        }
      }
    });
  } else {
    $('#'+tabid).parent().parent().find('#record-tabs .tab-pane.active').removeClass('active');
    $('#'+tabid+'-tab').addClass('active');
    $('#'+tabid).tab('show');
  }
}

$(document).ready(function() {
  $('.getFull').click(function(type) {
    var div_id = $(this).parent().parent().find(".hiddenId")[0].value;
    var div_html_id = div_id.replace(/\W/g, "_");
    var viewType = $(this).attr("data-view");
    var shortNode = jQuery('#short_'+div_html_id);
    var mainNode = shortNode.parent();
    var longNode = jQuery('#long_'+div_html_id);
    if ( !longNode.is(":visible") ) {
      var url = path + '/AJAX/JSON?' + $.param({method:'getRecordDetails',id:div_id,type:viewType});
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
    } else {
      longNode.addClass("hidden");
      shortNode.removeClass("hidden");
    }
    return false;
  });
});
