/*global path*/

$(document).ready(function() {
  $('.getFull').click(function() {
	var div_id = $(this).parent().parent().find(".hiddenId")[0].value;

	var shortNode = jQuery('#short_'+div_id);
	var mainNode = shortNode.parent();
        var longNode = jQuery('#long_'+div_id);
	if ( !longNode.is(":visible") ) {
	console.debug("unsichtbar");

	console.debug(div_id);
         var url = path + '/AJAX/JSON?' + $.param({method:'getRecordDetails',id:div_id});
         $.ajax({
                dataType: 'json',
                url: url,
                success: function(response) {
                        if (response.status == 'OK') {
					shortNode.hide();
					//$('#id_'+div_id).addClass("col-xs-11");
				 			
        				longNode.html(response.data);
					longNode.addClass("ajaxItem");
					longNode.show();
					console.debug(response);
                                                                                                                                          }
		}
         });
	return false;
	} else {
        console.debug("sichtbar");
		longNode.hide();
                     
   shortNode.show();
	return false;

	}
	return false;
  });

});
