/**
 origin: {vufind/bootstrap3}
 called by view helper/controller:
 usage: {}
 modified for finc:
 * optimize ajax covers, show picture in large size after click within a modal
 * remove 'div' #23111
 * add specific anchor #18287, #1566, #19394
 * add spinner on error fetching cover by ajax #20478
 * deactivated - we load every single cover directly by itself in cover.phtml #18451
 configured in: {}
 */

/* global Finc */
// finc: optimizes ajax covers
// show picture in large size after click within a modal
function registerCoverForModal(anchor, url, size) {
  if (anchor === null || anchor === undefined || url === null || url === undefined) {
    return;
  }

  anchor.click(function(event) {
    event.preventDefault();
    VuFind.modal('show');
    var largeSizeUrl = url + (size ? '&size=' + size : '');
    $.get(largeSizeUrl).done(function() {
      $('#modal').find('.modal-body').html('<img src="' + largeSizeUrl + '">');
    }).fail(function() {
      $('#modal').find('.modal-body').html('<img src="' + url + '">');
    });
  });
}

/* this is a backport from VF 7 core, remove on upgrade */
/*global VuFind */
function loadCoverByElement(data, element) {
  var url = VuFind.path + '/AJAX/JSON?method=' + 'getRecordCover';
  var img = element.find('img');
  // finc: removes 'div' #23111
  var spinner = element.find('.spinner');
  var container = element.find('div.cover-container');
  // finc: adds anchor #18287
  var anchor = container.children('a.coverlink');
  var source = $('<p class="cover-source">' + VuFind.translate('cover_source_label') + ' </p>');
  var context = data.context;
  function coverCallback(response) {
    if (typeof response.data.url !== 'undefined' && response.data.url !== false) {
      img.attr("src", response.data.url);
      var inlink = element.parent().is('a.record-cover-link');
      var medium = img.parents('.media-left, .media-right, .carousel-item');
      if (typeof response.data.backlink_text !== 'undefined') {
        if (typeof response.data.backlink_url !== 'undefined') {
          var link = $('<a href="' + response.data.backlink_url + '" class="cover-backlink" target="_blank">' + response.data.backlink_text + '</a>');
          source.append(link);
        } else {
          var span = $('<span class="cover-source-text"' + response.data.backlink_text + '</span>');
          source.append(span);
        }
        var backlink_locations = response.data.backlink_locations;
        if (backlink_locations.indexOf(context) >= 0) {
          if (inlink === true) {
            medium.append(source);
          } else {
            container.append(source);
          }
        }
      }
      if (inlink === true) {
        img.detach();
        medium.children('a').prepend(img);
        container.parents('.ajaxcover').remove();
      }
      // finc: specific for anchor #1566, #19394
      anchor.show();
      anchor.attr('href', response.data.url);
      registerCoverForModal(anchor, response.data.url, 'large');
      if (data.ariaLabel) {
        anchor.attr('aria-label', data.ariaLabel);
      }
      if (data.alt) {
        img.attr('alt', data.alt);
      }
      anchor.attr('tabindex', 0);
      anchor.removeClass('hidden');
      anchor.removeAttr('aria-hidden');
      // finc: specific for anchor #1566, #19394 - END
    } else {
      img.remove();
      source.remove();
      if (typeof response.data.html !== 'undefined') {
        container.html(VuFind.updateCspNonce(response.data.html));
      } else {
        container.html('');
      }
    }
    spinner.hide();
    container.show();
  }
  $.ajax({
    dataType: "json",
    url: url,
    method: "GET",
    data: data,
    element: element,
    success: coverCallback,
    // finc: adds spinner on error fetching cover by ajax #20478
    error: () => spinner.hide()
  });
}

function loadCovers() {
  $('.ajaxcover').each(function getDataAndLoadCovers() {
    var img = $(this).find('img');
    var data = {
      source: img.data('recordsource'),
      recordId: img.data('recordid'),
      size: img.data('coversize'),
      context: img.data('context'),
    };
    loadCoverByElement(data, $(this));
  });
}

// finc: deactivated - we load every single cover directly by itself in cover.phtml #18451
// $(document).ready(loadCovers);
