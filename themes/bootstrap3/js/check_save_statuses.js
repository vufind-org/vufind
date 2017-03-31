/*global hunt, htmlEncode, userIsLoggedIn, VuFind */
/*exported checkSaveStatuses */

function checkSaveStatus(el) {
  if (!userIsLoggedIn) {
    return;
  }
  var $item = $(el);

  if ($item.find('.hiddenId').length === 0 || $item.find('.hiddenSource').length === 0) {
    return null;
  }
  $.ajax({
    dataType: 'json',
    method: 'POST',
    url: VuFind.path + '/AJAX/JSON?method=getSingleSaveStatus',
    data: {
      id: $item.find('.hiddenId').val(),
      source: $item.find('.hiddenSource').val()
    }
  })
  .done(function checkSaveStatusDone(response) {
    if (response.data.length > 0) {
      var html = '<ul>' + response.data.map(function convertToLi(l) {
        return '<li><a href="' + l.list_url + '">' + htmlEncode(l.list_title) + '</a></li>';
      }).join('') + '</ul>';
      $item.find('.savedLists').html($item.find('.savedLists strong')[0].outerHTML + html).removeClass('hidden');
    }
  });
}

function checkSaveStatuses(_container) {
  if (!userIsLoggedIn) {
    return;
  }

  var container = _container instanceof Element
    ? _container
    : document.body;

  $.map($(container).find('.result,.record').toArray(), checkSaveStatus);
}

$(document).ready(function checkSaveStatusFail() {
  hunt($('.result,.record').toArray(), {
    enter: function huntEnter() {
      checkSaveStatus(this);
    }
  });
});
