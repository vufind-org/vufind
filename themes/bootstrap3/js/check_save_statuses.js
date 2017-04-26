/*global htmlEncode, userIsLoggedIn, Hunt, VuFind */
/*exported checkSaveStatuses */

function displaySaveStatus(itemLists, $item) {
  if (itemLists.length > 0) {
    var html = '<ul>' + itemLists.map(function convertToLi(l) {
      return '<li><a href="' + l.list_url + '">' + htmlEncode(l.list_title) + '</a></li>';
    }).join('') + '</ul>';
    $item.find('.savedLists').html($item.find('.savedLists strong')[0].outerHTML + html).removeClass('hidden');
  }
}

function saveStatusFail(response, textStatus) {
  $('.savedLists.ajax-pending').empty();
  if (textStatus === 'abort' || typeof response.responseJSON === 'undefined') {
    return;
  }
  // display the error message on each of the ajax status place holder
  $('.savedLists.ajax-pending').addClass('alert-danger').append(response.responseJSON.data);
}

var saveStatusObjs = [];
var saveStatusEls = {};
var saveStatusTimer = null;
var saveStatusDelay = 200;
function saveQueueAjax(obj, el) {
  clearTimeout(saveStatusTimer);
  saveStatusObjs.push(obj);
  saveStatusEls[obj.id] = el;
  saveStatusTimer = setTimeout(function delaySaveAjax() {
    var ids = [];
    var sources = [];
    for (var i = 0; i < saveStatusObjs.length; i++) {
      ids.push(saveStatusObjs[i].id);
      sources.push(saveStatusObjs[i].source);
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
          displaySaveStatus(response.data[id], saveStatusEls[id]);
        }
      }
    })
    .fail(function checkItemStatusFail(response, textStatus) {
      saveStatusFail(response, textStatus);
    });
    for (var j = 0; j < saveStatusObjs.length; j++) {
      saveStatusEls[saveStatusObjs[j].id].find('.ajax-availability').addClass('ajax-pending');
    }
    saveStatusObjs = [];
    saveStatusEls = {};
  }, saveStatusDelay);
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
  saveQueueAjax({
    id: $id.val(),
    source: $source.val()
  }, $item);
}

function checkSaveStatuses(_container) {
  if (!userIsLoggedIn) {
    return;
  }

  var container = _container instanceof Element
    ? _container
    : document.body;

  var ajaxItems = $(container).find('.result,.record');
  for (var i = 0; i < ajaxItems.length; i++) {
    var $id = $(ajaxItems[i]).find('.hiddenId').val();
    var $source = $(ajaxItems[i]).find('.hiddenSource').val();
    if ($id.length === 0 || $source.length === 0) {
      var idval = $id.val();
      saveQueueAjax({
        id: idval,
        source: $source.val()
      }, $(ajaxItems[i]));
    }
  }

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
