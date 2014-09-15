/*global path*/

$(document).ready(function() {
  $('.getFull').click(function() {
    var div_id = $(this).parent().parent().find(".hiddenId")[0].value;
    var div_html_id = div_id.replace(/\W/g, "_");

    var shortNode = jQuery('#short_'+div_html_id);
    var mainNode = shortNode.parent();
    var longNode = jQuery('#long_'+div_html_id);
    if ( !longNode.is(":visible") ) {
      console.debug("unsichtbar");

      console.debug(div_id);
      var url = path + '/AJAX/JSON?' + $.param({method:'getRecordDetails',id:div_id});
      $.ajax({
        dataType: 'json',
        url: url,
        success: function(response) {
          if (response.status == 'OK') {
            shortNode.addClass('hidden');

            longNode.html(response.data);
            longNode.addClass("ajaxItem");
            longNode.removeClass("hidden");
            //console.debug(response.data);
          }
        }
      });
      return false;
    } else {
      console.debug("sichtbar");
      longNode.addClass("hidden");

      shortNode.removeClass("hidden");
      return false;
    }
    return false;
  });
});
