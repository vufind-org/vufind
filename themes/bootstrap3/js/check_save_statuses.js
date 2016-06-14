/*global htmlEncode, userIsLoggedIn, VuFind */

function checkSaveStatuses(_container) {
  if (!userIsLoggedIn) {
    return;
  }
  var container = _container || $('body');

  var elements = {};
  var data = $.map(container.find('.result,.record'), function checkSaveRecordMap(record) {
    if ($(record).find('.hiddenId').length === 0 || $(record).find('.hiddenSource').length === 0) {
      return null;
    }
    var datum = {
      id: $(record).find('.hiddenId').val(),
      source: $(record).find('.hiddenSource')[0].value
    };
    var key = datum.source + '|' + datum.id;
    if (typeof elements[key] === 'undefined') {
      elements[key] = $();
    }
    elements[key] = elements[key].add($(record).find('.savedLists'));
    return datum;
  });
  if (data.length) {
    var ids = [];
    var srcs = [];
    for (var d = 0; d < data.length; d++) {
      ids.push(data[d].id);
      srcs.push(data[d].source);
    }
    $.ajax({
      dataType: 'json',
      method: 'POST',
      url: VuFind.path + '/AJAX/JSON?method=getSaveStatuses',
      data: {id: ids, source: srcs}
    })
    .done(function checkSaveStatusDone(response) {
      for (var sel in response.data) {
        if (response.data.hasOwnProperty(sel)) {
          var list = elements[sel];
          if (!list) {
            list = $('.savedLists');
          }
          var html = list.find('strong')[0].outerHTML + '<ul>';
          for (var i = 0; i < response.data[sel].length; i++) {
            html += '<li><a href="' + response.data[sel][i].list_url + '">'
              + htmlEncode(response.data[sel][i].list_title) + '</a></li>';
          }
          html += '</ul>';
          list.html(html).removeClass('hidden');
        }
      }
    });
  }
}

$(document).ready(function checkSaveStatusFail() {
  checkSaveStatuses();
});
