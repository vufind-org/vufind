/*global path*/

function showhideTabs(id) {
     $('#record-tabs .tab-pane.active').removeClass('active');
      $('#'+id).addClass('active');
      $('#'+id).tab('show');
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
