/*global htmlEncode, userIsLoggedIn, Hunt, VuFind */
/*exported checkSaveStatuses, checkSaveStatusesCallback */

function displaySaveStatus(itemLists, $item) {
  if (itemLists.length > 0) {
    var html = '<ul>' + itemLists.map(function convertToLi(l) {
      return '<li><a href="' + l.list_url + '">' + htmlEncode(l.list_title) + '</a></li>';
    }).join('') + '</ul>';
    $item.find('.savedLists').html($item.find('.savedLists strong')[0].outerHTML + html).addClass('loaded');
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
  saveStatusEls[obj.source + '|' + obj.id] = el;
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
        'id': ids,
        'source': sources
      }
    })
    .done(function checkSaveStatusDone(response) {
      for (var id in response.data) {
        if (response.data.hasOwnProperty(id)) {
          displaySaveStatus(response.data[id], saveStatusEls[id]);
        }
      }
      saveStatusObjs = [];
    })
    .fail(function checkItemStatusFail(response, textStatus) {
      saveStatusFail(response, textStatus);
    });
    for (var sel in saveStatusEls) {
      if (saveStatusEls.hasOwnProperty(sel)) {
        saveStatusEls[sel].find('.ajax-availability').addClass('ajax-pending');
      }
    }
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
    id: $id.val() + '',
    source: $source.val() + ''
  }, $item);
}

function checkSaveStatuses(_container) {
  if (!userIsLoggedIn) {
    return;
  }
  var container = _container || $('body');

  var ajaxItems = container.find('.result,.record');
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

function checkSaveStatusesCallback() {
  // Make sure no event parameter etc. is passed to checkSaveStatuses()
  checkSaveStatuses();
}

var saveStatusObserver = null;
$(document).ready(function checkSaveStatusFail() {
  if (typeof Hunt === 'undefined') {
    checkSaveStatuses();
  } else {
    saveStatusObserver = new Hunt(
      $('.result,.record').toArray(),
      { enter: checkSaveStatus }
    );
  }
});
