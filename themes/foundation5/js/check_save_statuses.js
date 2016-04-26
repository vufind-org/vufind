/*global htmlEncode, VuFind, userIsLoggedIn */

function checkSaveStatuses(container) {
  if (!userIsLoggedIn) {
    return;
  }
  if (typeof(container) == 'undefined') {
    container = $('body');
  }

  var elements = {};
  var data = $.map(container.find('.result,.record'), function(record) {
    if ($(record).find('.hiddenId').length == 0 || $(record).find('.hiddenSource').length == 0) {
      return null;
    }
    var datum = {'id':$(record).find('.hiddenId').val(), 'source':$(record).find('.hiddenSource')[0].value};
    var key = datum.source+'|'+datum.id;
    if (typeof elements[key] === 'undefined') {
      elements[key] = $();
    }
    elements[key] = elements[key].add($(record).find('.savedLists'));
    return datum;
  });
  if (data.length) {
    var ids = [];
    var srcs = [];
    for (var i = 0; i < data.length; i++) {
      ids.push(data[i].id);
      srcs.push(data[i].source);
    }
    $.ajax({
      dataType: 'json',
      method: 'POST',
      url: VuFind.path + '/AJAX/JSON?method=getSaveStatuses',
      data: {'id':ids, 'source':srcs}
    })
    .done(function(response) {
      for (var sel in response.data) {
        var list = elements[sel];
        if (!list) {
          list = $('.savedLists');
        }
        var html = list.find('strong')[0].outerHTML+'<ul>';
        for (var i=0; i<response.data[sel].length; i++) {
          html += '<li><a href="' + response.data[sel][i].list_url + '">'
            + htmlEncode(response.data[sel][i].list_title) + '</a></li>';
        }
        html += '</ul>';
        list.html(html).removeClass('hide');
      }
    });
  }
}

$(document).ready(function() {
  checkSaveStatuses();
});
