/* global checkSaveStatuses, localStorage, registerAjaxCommentRecord, registerTabEvents, setupRecordToolbar, syn_get_widget, VuFind */
var _EMBEDDED_COOKIE = 'vufind_search_open';
var _EMBEDDED_SEPERATOR = ':::';
var _EMBEDDED_DELIM = ',';
var _EMBEDDED_STATUS = {};

function saveEmbeddedStatusToCookie() {
  var storage = [];
  var str;
  for (str in _EMBEDDED_STATUS) {
    if ({}.hasOwnProperty.call(_EMBEDDED_STATUS, str)) {
      if (_EMBEDDED_STATUS[str]) {
        str += _EMBEDDED_SEPERATOR + _EMBEDDED_STATUS[str];
      }
      storage.push(str);
    }
  }
  localStorage.setItem(_EMBEDDED_COOKIE, $.unique(storage).join(_EMBEDDED_DELIM));
}
function addToEmbeddedCookie(id, tab) {
  _EMBEDDED_STATUS[id] = tab;
  saveEmbeddedStatusToCookie();
}
function removeFromEmbeddedCookie(id) {
  if (delete _EMBEDDED_STATUS[id]) {
    saveEmbeddedStatusToCookie();
  }
}
function loadEmbeddedCookies() {
  var cookies = localStorage.getItem(_EMBEDDED_COOKIE);
  var items = cookies.split(_EMBEDDED_DELIM);
  var doomed = [];
  var hiddenIds;
  var parts;
  var result;
  var i;
  var j;
  if (!cookies) return;
  hiddenIds = $('.hiddenId');
  for (i = 0; i < items.length; i++) {
    parts = items[i].split(_EMBEDDED_SEPERATOR);
    _EMBEDDED_STATUS[parts[0]] = parts[1] || null;
    result = null;
    for (j = 0; j < hiddenIds.length; j++) {
      if (hiddenIds[j].value === parts[0]) {
        result = $(hiddenIds[j]).closest('.result');
        break;
      }
    }
    if (result === null) {
      doomed.push(parts[0]);
      continue;
    }
    var $link = result.find('.getFull');
    $link.addClass('auto expanded');
    toggleDataView($link, parts[1]);
  }
  for (i = 0; i < doomed.length; i++) {
    removeFromEmbeddedCookie(doomed[i]);
  }
}

function ajaxFLLoadTab(tabid, _click) {
  var click = _click || false;
  var $tab = $('#' + tabid);
  var $result = $tab.closest('.result');
  if ($result.length === 0) {
    return true;
  }
  var id = $result.find('.hiddenId')[0].value;
  var source = $result.find('.hiddenSource')[0].value;
  if ($tab.parent().hasClass('noajax')) {
    window.location.href = $tab.attr('href');
    return true;
  }
  var urlroot;
  if (source === VuFind.defaultSearchBackend) {
    urlroot = 'Record';
  } else {
    urlroot = source + 'record';
  }
  if (!$tab.hasClass('loaded')) {
    $('#' + tabid + '-content').html(
      '<i class="fa fa-spinner fa-spin"></i> ' + VuFind.translate('loading') + '...'
    );
    var tab = tabid.split('_');
    tab = tab[0];
    $.ajax({
      url: VuFind.path + '/' + urlroot + '/' + encodeURIComponent(id) + '/AjaxTab',
      type: 'POST',
      data: { tab: tab },
      success: function ajaxTabSuccess(data) {
        var html = data.trim();
        if (html.length > 0) {
          $('#' + tabid + '-content').html(html);
          registerTabEvents();
        } else {
          $('#' + tabid + '-content').html(VuFind.translate('collection_empty'));
        }
        if (typeof syn_get_widget === 'function') {
          syn_get_widget();
        }
        $('#' + tabid).addClass('loaded');
      }
    });
  }
  if (click) {
    $tab.click();
  }
  return true;
}

function toggleDataView(_link, tabid) {
  var $link = $(_link);
  var viewType = $link.attr('data-view');
  var result;
  var mediaBody;
  var shortNode;
  var loadingNode;
  var longNode;
  var divID;
  // If full, return true
  if (viewType === 'full') {
    return true;
  }
  result = $link.closest('.result');
  mediaBody = result.find('.media-body');
  shortNode = mediaBody.find('.short-view');
  // Insert new elements
  if (!$link.hasClass('js-setup')) {
    $link.prependTo(mediaBody);
    result.addClass('embedded');
    mediaBody.find('.short-view').addClass('collapse');
    longNode = $();
    // Add loading status
    shortNode
      .before('<div class="loading hidden"><i class="fa fa-spin fa-spinner"></i> '
              + VuFind.translate('loading') + '...</div>')
      .before('<div class="long-view collapse"></div>');
    $link.addClass('js-setup');
  }
  // Gather information
  divID = result.find('.hiddenId')[0].value;
  longNode = mediaBody.find('.long-view');
  // Toggle visibility
  if (!longNode.is(':visible')) {
    // AJAX for information
    if (longNode.is(':empty')) {
      loadingNode = mediaBody.find('.loading');
      loadingNode.removeClass('hidden');
      $.ajax({
        dataType: 'json',
        url: VuFind.path + '/AJAX/JSON?' + $.param({
          method: 'getRecordDetails',
          id: divID,
          type: viewType,
          source: result.find('.hiddenSource')[0].value
        }),
        success: function getRecordDetailsSuccess(response) {
          if (response.status === 'OK') {
            // Insert tabs html
            longNode.html(response.data);
            // Hide loading
            loadingNode.addClass('hidden');
            longNode.collapse('show');
            // Load first tab
            if (tabid) {
              ajaxFLLoadTab(tabid, true);
            } else {
              var $firstTab = $(longNode).find('.list-tab-toggle.active');
              if ($firstTab.length === 0) {
                $firstTab = $(longNode).find('.list-tab-toggle:eq(0)');
              }
              ajaxFLLoadTab($firstTab.attr('id'), true);
            }
            // Bind tab clicks
            longNode.find('.list-tab-toggle').click(function embeddedTabLoad() {
              addToEmbeddedCookie(divID, this.id);
              return ajaxFLLoadTab(this.id);
            });
            longNode.find('.noajax .list-tab-toggle').click(function accordionNoAjax() {
              window.location.href = $(this).attr('data-href');
            });
            longNode.find('[id^=usercomment]').find('input[type=submit]').unbind('click').click(
              function embeddedComments() {
                return registerAjaxCommentRecord(
                  longNode.find('[id^=usercomment]').find('input[type=submit]').closest('form')
                );
              }
            );
            // Add events to record toolbar
            VuFind.lightbox.bind(longNode);
            checkSaveStatuses(shortNode.closest('.result'));
          }
        }
      });
    } else {
      longNode.collapse('show');
    }
    $link.addClass('expanded');
    shortNode.collapse('hide');
    if (!$link.hasClass('auto')) {
      addToEmbeddedCookie(divID, $(longNode).find('.list-tab-toggle.active').attr('id'));
    } else {
      $link.removeClass('auto');
    }
  } else {
    shortNode.collapse('show');
    longNode.collapse('hide');
    $link.removeClass('expanded');
    removeFromEmbeddedCookie(divID);
  }
  return false;
}

$(document).ready(function embeddedRecordReady() {
  $('.getFull').click(function linkToggle() { return toggleDataView(this); });
  loadEmbeddedCookies();
});
