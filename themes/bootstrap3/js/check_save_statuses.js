/*global VuFind */

function checkSaveStatuses(target) {
  if ('undefined' == typeof target) {
    target = '.result,.record';
  }
  var data = $.map($(target), function(record) {
    if($(record).find('.hiddenId').length == 0 || $(record).find('.hiddenSource').length == 0) {
      return false;
    }
    return {'id':$(record).find('.hiddenId').val(), 'source':$(record).find('.hiddenSource')[0].value};
  });
  if (data.length) {
    var ids = [];
    var srcs = [];
    for (var i = 0; i < data.length; i++) {
      var index = ids.indexOf(data[i].id);
      // embedded record views cause duplicate ids
      if (index < 0 || srcs[index] != data[i].source) {
        ids[i] = data[i].id;
        srcs[i] = data[i].source;
      }
    }
    $.ajax({
      dataType: 'json',
      method: 'POST',
      url: VuFind.getPath() + '/AJAX/JSON?method=getSaveStatuses',
      data: {id:ids, 'source':srcs}
    })
    .done(function(response) {
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
    });
  }
}

$(document).ready(function() {
  checkSaveStatuses()
});
