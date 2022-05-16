/*global htmlEncode, userIsLoggedIn, Hunt, VuFind */
/*exported checkSaveStatuses, checkSaveStatusesCallback */

function displaySaveStatus(itemLists, $item) {
  if (itemLists.length > 0) {
    // If we got lists back, display them!
    var html = '<ul>' + itemLists.map(function convertToLi(l) {
      return '<li><a href="' + l.list_url + '">' + htmlEncode(l.list_title) + '</a></li>';
    }).join('') + '</ul>';
    $item.find('.savedLists').addClass('loaded');
    $item.find('.js-load').replaceWith(html);
  } else {
    // If we got nothing back, remove the pending status:
    $item.find('.js-load').remove();
  }
  // No matter what, clear the flag that we have a pending save:
  $item.removeClass('js-save-pending');
}

function saveStatusFail(response, textStatus) {
  if (textStatus === 'abort' || typeof response.responseJSON === 'undefined') {
    $('.js-save-pending .savedLists').addClass('hidden');
    return;
  }
  // display the error message on each of the ajax status place holder
  $('.js-save-pending .savedLists').addClass('alert-danger').append(response.responseJSON.data);
}

var saveStatusObjs = [];
var saveStatusEls = {};
var saveStatusTimer = null;
var saveStatusDelay = 200;
var saveStatusRunning = false;

function runSaveAjaxForQueue() {
  // Only run one save status AJAX request at a time:
  if (saveStatusRunning) {
    saveStatusTimer = setTimeout(runSaveAjaxForQueue, saveStatusDelay);
    return;
  }
  saveStatusRunning = true;
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
      for (var id in response.data.statuses) {
        if (Object.prototype.hasOwnProperty.call(response.data.statuses, id)) {
          displaySaveStatus(response.data.statuses[id], saveStatusEls[id]);

          // Remove populated ids from the queue
          for (var j = saveStatusObjs.length - 1; j >= 0; j--) {
            var parts = id.split('|');
            if (saveStatusObjs[j].id === parts[1] && saveStatusObjs[j].source === parts[0]) {
              saveStatusObjs.splice(j, 1);
            }
          }
        }
      }
      saveStatusRunning = false;
    })
    .fail(function checkItemStatusFail(response, textStatus) {
      saveStatusFail(response, textStatus);
      saveStatusRunning = false;
    });
}
function saveQueueAjax(obj, el) {
  if (el.hasClass('js-save-pending')) {
    return;
  }
  clearTimeout(saveStatusTimer);
  saveStatusObjs.push(obj);
  saveStatusEls[obj.source + '|' + obj.id] = el;
  saveStatusTimer = setTimeout(runSaveAjaxForQueue, saveStatusDelay);
  el.addClass('js-save-pending');
  el.find('.savedLists')
    .removeClass('loaded hidden')
    .append('<span class="js-load">' + VuFind.translate('loading_ellipsis') + '</span>');
  el.find('.savedLists ul').remove();
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

var saveStatusObserver = null;
function checkSaveStatuses(_container) {
  if (!userIsLoggedIn) {
    return;
  }
  var container = _container || $('body');

  var ajaxItems = container.find('.result,.record');
  for (var i = 0; i < ajaxItems.length; i++) {
    var $id = $(ajaxItems[i]).find('.hiddenId');
    var $source = $(ajaxItems[i]).find('.hiddenSource');
    if ($id.length > 0 && $source.length > 0) {
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
