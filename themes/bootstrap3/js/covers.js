/*global VuFind */
function loadCoverByElement(data, element) {
  var url = VuFind.path + '/AJAX/JSON?method=' + 'getRecordCover';
  var img = element.find('img');
  var spinner = element.children('div.spinner');
  var container = element.children('div.cover-container');
  function coverCallback(response) {
    spinner.hide();
    container.show();
    if (typeof response.data.url !== 'undefined' && response.data.url !== false) {
      img.attr("src", response.data.url);
      container.children().not("img").hide();
      if (typeof response.data.backlink_text !== 'undefined' && element.parents().is('.template-dir-record')) {
        var link = element.find('.cover-backlink');
        var span = element.find('.cover-source-text');
        if (typeof response.data.backlink_url !== 'undefined') {
          link.html(response.data.backlink_text);
          link.attr("href", response.data.backlink_url);
          span.remove();
        } else {
          span.html(response.data.backlink_text);
          link.remove();
        }
        element.find('.cover-source').show();
      }
    } else {
      img.remove();
      if (typeof response.data.html !== 'undefined') {
        container.html(response.data.html);
      } else {
        container.html();
      }
    }
  }
  $.ajax({
    dataType: "json",
    url: url,
    method: "GET",
    data: data,
    element: element,
    success: coverCallback
  });
}

function loadCovers() {
  $('.ajaxcover').each(function getDataAndLoadCovers() {
    var img = $(this).find('img');
    var data = {
      source: img.data('recordsource'),
      recordId: img.data('recordid'),
      size: img.data('coversize')
    };
    loadCoverByElement(data, $(this));
  });
}
$(document).ready(loadCovers);
