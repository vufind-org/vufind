/*global hunt, htmlEncode, userIsLoggedIn, VuFind */
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
    url: VuFind.path + '/AJAX/JSON?method=getSingleSaveStatus',
    data: {
      id: $id.val(),
      source: $source.val()
    }
  })
  .done(function checkSaveStatusDone(response) {
    displaySaveStatus(response.data, $item);
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
      var id = $id.val();
      elements[id] = $(ajaxItems[i]);
      ids.push(id);
      sources.push($source,val());
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
  .done(function checkItemStatusDone(response) {
    for (var id in response.data) {
      if (response.data.hasOwnProperty(id)) {
        displaySaveStatus(response.data[id], elements[id]);
      }
    }
  })
  .fail(function checkItemStatusFail(response, textStatus) {
    $(container).find('.ajax-availability').empty();
    if (textStatus === 'abort' || typeof response.responseJSON === 'undefined') { return; }
    // display the error message on each of the ajax status place holder
    $(container).find('.ajax-availability').append(response.responseJSON.data).addClass('text-danger');
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
