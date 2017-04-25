/*global htmlEncode, itemStatusFail, userIsLoggedIn, Hunt, VuFind */
/*exported checkSaveStatuses */

function displaySaveStatus(itemLists, $item) {
  if (itemLists.length > 0) {
    var html = '<ul>' + itemLists.map(function convertToLi(l) {
      return '<li><a href="' + l.list_url + '">' + htmlEncode(l.list_title) + '</a></li>';
    }).join('') + '</ul>';
    $item.find('.savedLists').html($item.find('.savedLists strong')[0].outerHTML + html).removeClass('hidden');
  }
}

function checkSaveStatus(el) {
  if (!userIsLoggedIn) {
    return;
  }
  var $item = $(el);

  var $id = $item.find('.hiddenId');
  var $source = $item.find('.hiddenSource');
  if ($id.length === 0 || $source.length === 0) {
    return null;
  }
  $.ajax({
    dataType: 'json',
    method: 'POST',
    url: VuFind.path + '/AJAX/JSON?method=getSaveStatuses',
    data: {
      id: [ $id.val() ],
      source: [ $source.val() ]
    }
  })
  .done(function checkSaveStatusDone(response) {
    displaySaveStatus(response.data[0], $item);
  })
  .fail(function checkItemStatusFail(response, textStatus) {
    itemStatusFail(el, response, textStatus);
  });
}

function checkSaveStatuses(_container) {
  if (!userIsLoggedIn) {
    return;
  }

  var container = _container instanceof Element
    ? _container
    : document.body;

  var ajaxItems = $(container).find('.result,.record');
  var elements = {};
  var ids = [];
  var sources = [];
  for (var i = 0; i < ajaxItems.length; i++) {
    var $id = $(ajaxItems[i]).find('.hiddenId').val();
    var $source = $(ajaxItems[i]).find('.hiddenSource').val();
    if ($id.length === 0 || $source.length === 0) {
      var idval = $id.val();
      elements[idval] = $(ajaxItems[i]);
      ids.push(idval);
      sources.push($source.val());
    }
  }

  $.ajax({
    dataType: 'json',
    method: 'POST',
    url: VuFind.path + '/AJAX/JSON?method=getSaveStatuses',
    data: {
      'ids': ids,
      'sources': sources
    }
  })
  .done(function checkSaveStatusDone(response) {
    for (var id in response.data) {
      if (response.data.hasOwnProperty(id)) {
        displaySaveStatus(response.data[id], elements[id]);
      }
    }
  })
  .fail(function checkItemStatusFail(response, textStatus) {
    itemStatusFail(container, response, textStatus);
  });
  // Stop looking for a scroll loader
  if (saveStatusObserver) {
    saveStatusObserver.disconnect();
  }
}
var saveStatusObserver = null;
$(document).ready(function checkSaveStatusFail() {
  saveStatusObserver = new Hunt(
    $('.result,.record').toArray(), {
      enter: checkSaveStatus
    });
});
