/*global registerAjaxCommentRecord, registerTabEvents, setupRecordToolbar, VuFind */

function showhideTabs(tabid) {
  $('#'+tabid).parents('.search_tabs').find('.tab-pane.active').removeClass('active');
  $('#'+tabid+'-tab').addClass('active');
  $('#'+tabid).tab('show');
}

function ajaxFLLoadTab(tabid, reload) {
  if(typeof reload === "undefined") {
    reload = false;
  }
  if($('#'+tabid).parent().hasClass('noajax')) {
    window.location.href = $('#'+tabid).attr('href');
    return true;
  }
  var $record = $('#'+tabid).closest('.record,.result');
  var id = $record.find(".hiddenId")[0].value;
  var source = $record.find(".hiddenSource")[0].value;
  var urlroot;
  if (source == VuFind.defaultSearchBackend) {
    urlroot = 'Record';
  } else {
    urlroot = source + 'record';
  }
  var tab = tabid.split('_');
  tab = tab[0];
  if(reload || $('#'+tabid+'-tab').is(':empty')) {
    showhideTabs(tabid);
    $('#'+tabid+'-tab').html('<i class="fa fa-spinner fa-spin"></i> '+VuFind.translate('loading')+'...');
    $.ajax({
      url: VuFind.path + '/' + urlroot + '/' + id + '/AjaxTab',
      type: 'POST',
      data: {tab: tab},
      success: function(data) {
        data = data.trim();
        if (data.length > 0) {
          $('#'+tabid+'-tab').html(data);
          registerTabEvents();
        } else {
          $('#'+tabid+'-tab').html(VuFind.translate('collection_empty'));
        }
        if(typeof syn_get_widget === "function") {
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
  var viewType = $(this).attr("data-view");
  if (viewType == 'full') {
    return true;
  }
  // Insert new elements
  var mainNode = $(this).closest('.result');
  if (!$(this).hasClass('toggle') && !$(this).hasClass('setup')) {
    // Add classes to view and result container
    $(this)
      .closest('.row').addClass('short-view')
      .parent().addClass('data-view');
    // Copy the title
    var dataView = mainNode.find('.data-view');
    var pos = $(this).position();
    var clone = $(this).clone()
      .addClass('toggle').click(toggleDataView)
      .css('padding-left', $(this).closest('.middle').position().left-1);
    dataView.prepend(clone);
    // Add loading status
    dataView.append('<div class="loading hidden">\
      <i class="fa fa-spin fa-spinner"></i> '+VuFind.translate('loading')+'...\
    </div><div class="long-view row hidden"></div>');
    $(this).addClass('setup');
  }
  // Gather information
  var toggle = mainNode.find('.toggle');
  var div_id = mainNode.find(".hiddenId")[0].value;
  var shortNode = mainNode.find('.short-view');
  var loadingNode = mainNode.find('.loading');
  var longNode = mainNode.find('.long-view');
  // Toggle visibility
  if (!longNode.is(":visible")) {
    shortNode.addClass("hidden");
    longNode.removeClass("hidden");
    toggle.removeClass("hidden");
    // AJAX for information
    if (longNode.is(':empty')) {
      loadingNode.removeClass("hidden");
      var url = VuFind.path + '/AJAX/JSON?' + $.param({
        method:'getRecordDetails',
        id:div_id,
        type:viewType,
        source:mainNode.find(".hiddenSource")[0].value
      });
      $.ajax({
        dataType: 'json',
        url: url,
        success: function(response) {
          if (response.status == 'OK') {
            // Insert tabs html
            longNode.html(response.data);
            // Hide loading
            loadingNode.addClass("hidden");
            // Load first tab
            var $firstTab = $(longNode).find('.recordTabs li.active a');
            if ($firstTab.length > 0) {
              ajaxFLLoadTab($firstTab.attr('id'));
            }
            // Add events to record toolbar
            VuFind.lightbox.bind(longNode);
            checkSaveStatuses(shortNode.closest('.result,.record'));
          }
        }
      });
    }
  } else {
    toggle.addClass("hidden");
    longNode.addClass("hidden");
    loadingNode.addClass("hidden");
    shortNode.removeClass("hidden");
  }
  return false;
}

$(document).ready(function() {
  $('.getFull').click(toggleDataView);
});
