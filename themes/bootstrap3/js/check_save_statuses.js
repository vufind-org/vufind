/*global VuFind */

function checkSaveStatuses() {
  var data = $.map($('.result,.record'), function(record) {
    if($(record).find('.hiddenId').length == 0 || $(record).find('.hiddenSource').length == 0) {
      return false;
    }
    return {'id':$(record).find('.hiddenId').val(), 'source':$(record).find('.hiddenSource')[0].value};
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
      method: 'POST',
      url: VuFind.getPath() + '/AJAX/JSON?method=getSaveStatuses',
      data: {id:ids, 'source':srcs},
      success: function(response) {
        if(response.status == 'OK') {
          for (var rn in response.data) {
            var list = $('#result'+rn).find('.savedLists')
            if (list.length == 0) {
              list = $('.savedLists');
            }
            var html = list.find('strong')[0].outerHTML+'<ul>';
            for (var i=0; i<response.data[rn].length; i++) {
              html += '<li><a href="' + VuFind.getPath() + '/MyResearch/MyList/' + response.data[rn][i].list_id + '">'
                       + response.data[rn][i].list_title + '</a></li>';
            }
            html += '</ul>';
            list.html(html).removeClass('hidden');
          }
        }
      }
    });
  }
}

$(document).ready(function() {
  checkSaveStatuses()
});
