/* global localStorage, registerAjaxCommentRecord, registerTabEvents, setupRecordToolbar, VuFind */
var _EMBEDDED_COOKIE = 'vufind_search_open';
var _EMBEDDED_DELIM = ',';
var _EMBEDDED_STATUS = {};

function saveEmbeddedStatusToCookie() {
  var storage = [];
  for (var i in _EMBEDDED_STATUS) {
    var str = i;
    if (_EMBEDDED_STATUS[i]) {
      str += ':::' + _EMBEDDED_STATUS[i];
    }
    storage.push(str);
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
  if (!cookies) return;
  var items = cookies.split(_EMBEDDED_DELIM);
  var hiddenIds = $('.hiddenId');
  var doomed = [];
  for (var i = 0; i < items.length; i++) {
    var parts = items[i].split(':::');
    _EMBEDDED_STATUS[parts[0]] = parts[1] || null;
    var mainNode = null;
    for (var j = 0; j < hiddenIds.length; j++) {
      if (hiddenIds[j].value == parts[0]) {
        mainNode = $(hiddenIds[j]).closest('.result');
        break;
      }
    }
    if (mainNode == null) {
      doomed.push(parts[0]);
      continue;
    }
    mainNode.find('.getFull').addClass('auto').click();
  }
  for (var i = 0; i < doomed.length; i++) {
    console.log('doomed', doomed[i]);
    removeFromEmbeddedCookie(doomed[i]);
  }
}

function showhideTabs(tabid) {
  $('#' + tabid).parents('.search_tabs').find('.tab-pane.active').removeClass('active');
  $('#' + tabid + '-tab').addClass('active');
  $('#' + tabid).tab('show');
}
function ajaxFLLoadTab(tabid, reload) {
  if (typeof reload === 'undefined') {
    reload = false;
  }
  if ($('#' + tabid).parent().hasClass('noajax')) {
    window.location.href = $('#' + tabid).attr('href');
    return true;
  }
  var $record = $('#' + tabid).closest('.result');
  if ($record.length == 0) {
    $record = $('#' + tabid).closest('.record');
  }
  if ($record.length == 0) {
    return true;
  }
  var id = $record.find('.hiddenId')[0].value;
  var source = $record.find('.hiddenSource')[0].value;
  var urlroot;
  if (source == VuFind.defaultSearchBackend) {
    urlroot = 'Record';
  } else {
    urlroot = source + 'record';
  }
  var tab = tabid.split('_');
  tab = tab[0];
  if (reload || $('#' + tabid + '-tab').is(':empty')) {
    showhideTabs(tabid);
    $('#' + tabid + '-tab').html('<i class="fa fa-spinner fa-spin"></i> ' + VuFind.translate('loading') + '...');
    $.ajax({
      url: VuFind.path + '/' + urlroot + '/' + encodeURIComponent(id) + '/AjaxTab',
      type: 'POST',
      data: { tab: tab },
      success: function (data) {
        data = data.trim();
        if (data.length > 0) {
          $('#' + tabid + '-tab').html(data);
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
  if (viewType == 'full') {
    return true;
  }
  // Insert new elements
  var result = $(this).closest('.result');
  var mediaBody = result.find('.media-body');
  var shortNode = mediaBody.find('.short-view');
  if (!$(this).hasClass('setup')) {
    $(this).prependTo(mediaBody);
    result.addClass('embedded');
    mediaBody.find('.short-view').addClass('collapse');
    var longNode = $('<div class="long-view collapse"></div>');
    // Add loading status
    shortNode
      .before('<div class="loading hidden"><i class="fa fa-spin fa-spinner"></i> ' + VuFind.translate('loading') + '...</div>')
      .before(longNode);
    $(this).addClass('setup');
  }
  // Gather information
  var div_id = result.find('.hiddenId')[0].value;
  var longNode = mediaBody.find('.long-view');
  // Toggle visibility
  if (!longNode.is(':visible')) {
    $(this).addClass('expanded');
    shortNode.collapse('hide');
    // AJAX for information
    if (longNode.is(':empty')) {
      var loadingNode = mediaBody.find('.loading');
      loadingNode.removeClass('hidden');
      var url = VuFind.path + '/AJAX/JSON?' + $.param({
        method:'getRecordDetails',
        id:div_id,
        type:viewType,
        source:result.find('.hiddenSource')[0].value
      });
      $.ajax({
        dataType: 'json',
        url: url,
        success: function (response) {
          if (response.status == 'OK') {
            // Insert tabs html
            longNode.html(response.data);
            // Hide loading
            loadingNode.addClass('hidden');
            longNode.collapse('show');
            // Load first tab
            var $firstTab = $(longNode).find('.recordTabs li.active a');
            if ($firstTab.length == 0) {
              $firstTab = $($(longNode).find('.recordTabs li a')[0]);
            }
            ajaxFLLoadTab($firstTab.attr('id'));
            // Bind tab clicks
            longNode.find('.search_tabs .recordTabs a').click(function () {
              addToEmbeddedCookie(result.attr('id'), $(this).attr('id'));
              return ajaxFLLoadTab(this.id);
            });
            longNode.find('.panel.noajax .accordion-toggle').click(function () {
              window.location.href = $(this).attr('data-href');
            });
            longNode.find('[id^=usercomment]').find('input[type=submit]').unbind('click').click(function () {
              return registerAjaxCommentRecord(
                longNode.find('[id^=usercomment]').find('input[type=submit]').closest('form')
              );
            });
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

$(document).ready(function () {
  $('.getFull').click(toggleDataView);
  loadEmbeddedCookies();
});
