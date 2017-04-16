/*global path*/

function checkSaveStatuses() {
  var data = $.map($('.result,.record'), function(i) {
    if($(i).find('.hiddenId').length == 0 || $(i).find('.hiddenSource').length == 0) {
      return false;
    }
    return {'id':$(i).find('.hiddenId').val(), 'source':$(i).find('.hiddenSource')[0].value};
  });
  if (data.length) {
    var ids = [];
    var srcs = [];
    for (var i = 0; i < data.length; i++) {
      ids[i] = data[i].id;
      srcs[i] = data[i].source;
    }
    $.ajax({
      dataType: 'json',
      url: path + '/AJAX/JSON?method=getSaveStatuses',
      data: {id:ids, 'source':srcs},
      success: function(response) {
        if(response.status == 'OK') {
          $('.savedLists > ul').empty();
          $.each(response.data, function(i, result) {
            var $container = $('#result'+result.record_number).find('.savedLists');
            if ($container.length == 0) { // Record view
              $container = $('#savedLists');
            }
            var $ul = $container.children('ul:first');
            if ($ul.length == 0) {
              $container.append('<ul></ul>');
              $ul = $container.children('ul:first');
            }
            var html = '<li><a href="' + path + '/MyResearch/MyList/' + result.list_id + '">'
                     + result.list_title + '</a></li>';
            $ul.append(html);
            $container.removeClass('hidden');
          });
        }
      }
    });
  }
}

$(document).ready(function() {
  checkSaveStatuses();
});
