/* global checkSaveStatuses, localStorage, registerAjaxCommentRecord, registerTabEvents, setupRecordToolbar, syn_get_widget, VuFind */
var _EMBEDDED_COOKIE = 'vufind_search_open';
var _EMBEDDED_DELIM = ',';
var _EMBEDDED_STATUS = {};

function saveEmbeddedStatusToCookie() {
  var storage = [];
  var str;
  for (str in _EMBEDDED_STATUS) {
    if ({}.hasOwnProperty.call(_EMBEDDED_STATUS, str)) {
      if (_EMBEDDED_STATUS[str]) {
        str += ':::' + _EMBEDDED_STATUS[str];
      }
      storage.push(str);
    }
  }
  localStorage.setItem(_EMBEDDED_COOKIE, $.unique(storage).join(_EMBEDDED_DELIM));
}
function addToEmbeddedCookie(id, tab) {
  var realID = $('#' + id).find('.hiddenId').val();
  _EMBEDDED_STATUS[realID] = tab;
  saveEmbeddedStatusToCookie();
}
function removeFromEmbeddedCookie(id) {
  delete _EMBEDDED_STATUS[id];
  saveEmbeddedStatusToCookie();
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
    parts = items[i].split(':::');
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
    result.find('.getFull').addClass('auto').click();
  }
  for (i = 0; i < doomed.length; i++) {
    removeFromEmbeddedCookie(doomed[i]);
  }
}

function showhideTabs(tabid) {
  $('#' + tabid).parents('.search_tabs').find('.tab-pane.active').removeClass('active');
  $('#' + tabid + '-tab').addClass('active');
  $('#' + tabid).tab('show');
}
function ajaxFLLoadTab(tabid, _reload) {
  var reload = _reload || false;
  var $record = $('#' + tabid).closest('.result');
  var tab = tabid.split('_');
  var id = $record.find('.hiddenId')[0].value;
  var source = $record.find('.hiddenSource')[0].value;
  var urlroot;
  if ($('#' + tabid).parent().hasClass('noajax')) {
    window.location.href = $('#' + tabid).attr('href');
    return true;
  }
  if ($record.length === 0) {
    $record = $('#' + tabid).closest('.record');
  }
  if ($record.length === 0) {
    return true;
  }
  if (source === VuFind.defaultSearchBackend) {
    urlroot = 'Record';
  } else {
    urlroot = source + 'record';
  }
  tab = tab[0];
  if (reload || $('#' + tabid + '-tab').is(':empty')) {
    showhideTabs(tabid);
    $('#' + tabid + '-tab').html(
      '<i class="fa fa-spinner fa-spin"></i> ' + VuFind.translate('loading') + '...'
    );
    $.ajax({
      url: VuFind.path + '/' + urlroot + '/' + encodeURIComponent(id) + '/AjaxTab',
      type: 'POST',
      data: { tab: tab },
      success: function ajaxTabSuccess(data) {
        var html = data.trim();
        if (html.length > 0) {
          $('#' + tabid + '-tab').html(html);
          registerTabEvents();
        } else {
          $('#' + tabid + '-tab').html(VuFind.translate('collection_empty'));
        }
        // Auto click last tab
        if ($record.find('.getFull').hasClass('auto') && _EMBEDDED_STATUS[id]) {
          $('#' + _EMBEDDED_STATUS[id]).click();
          $record.find('.getFull').removeClass('auto');
        }
        if (typeof syn_get_widget === 'function') {
          syn_get_widget();
        }
      }
    });
  } else {
    showhideTabs(tabid);
  }
  return false;
}

function toggleDataView() {
  // If full, return true
  var viewType = $(this).attr('data-view');
  var result;
  var mediaBody;
  var shortNode;
  var loadingNode;
  var longNode;
  var divID;
  if (viewType === 'full') {
    return true;
  }
  // Insert new elements
  result = $(this).closest('.result');
  mediaBody = result.find('.media-body');
  shortNode = mediaBody.find('.short-view');
  if (!$(this).hasClass('setup')) {
    $(this).prependTo(mediaBody);
    result.addClass('embedded');
    mediaBody.find('.short-view').addClass('collapse');
    longNode = $();
    // Add loading status
    shortNode
      .before('<div class="loading hidden"><i class="fa fa-spin fa-spinner"></i> '
              + VuFind.translate('loading') + '...</div>')
      .before('<div class="long-view collapse"></div>');
    $(this).addClass('setup');
  }
  // Gather information
  divID = result.find('.hiddenId')[0].value;
  longNode = mediaBody.find('.long-view');
  // Toggle visibility
  if (!longNode.is(':visible')) {
    $(this).addClass('expanded');
    shortNode.collapse('hide');
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
          var $firstTab;
          if (response.status === 'OK') {
            // Insert tabs html
            longNode.html(response.data);
            // Hide loading
            loadingNode.addClass('hidden');
            longNode.collapse('show');
            // Load first tab
            $firstTab = $(longNode).find('.recordTabs li.active a');
            if ($firstTab.length === 0) {
              $firstTab = $($(longNode).find('.recordTabs li a')[0]);
            }
            ajaxFLLoadTab($firstTab.attr('id'));
            // Bind tab clicks
            longNode.find('.search_tabs .recordTabs a').click(function embeddedTabLoad() {
              addToEmbeddedCookie(result.attr('id'), $(this).attr('id'));
              return ajaxFLLoadTab(this.id);
            });
            longNode.find('.panel.noajax .accordion-toggle').click(function accordionNoAjax() {
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
            checkSaveStatuses(shortNode.closest('.result,.record'));
          }
        }
      });
    } else {
      longNode.collapse('show');
    }
    if (!mediaBody.find('.getFull').hasClass('auto')) {
      addToEmbeddedCookie(mediaBody.attr('id'), $(this).attr('id'));
    }
  } else {
    shortNode.collapse('show');
    longNode.collapse('hide');
    $(this).removeClass('expanded');
  }
  return false;
}

$(document).ready(function embeddedRecordReady() {
  $('.getFull').click(toggleDataView);
  loadEmbeddedCookies();
});
