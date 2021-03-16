/*global VuFind */
function loadCoverByElement(data, element) {
  var url = VuFind.path + '/AJAX/JSON?method=' + 'getRecordCover';
  var img = element.find('img');
  var spinner = element.find('div.spinner');
  var container = element.find('div.cover-container');
  var source = element.find('.cover-source');
  function coverCallback(response) {
    if (typeof response.data.url !== 'undefined' && response.data.url !== false) {
      img.attr("src", response.data.url);
      container.find('.cover-source').hide();
      var inlink = element.parent().children().first().is('a');
      if (typeof response.data.backlink_text !== 'undefined' && inlink === false) {
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
      } else {
        var medium = img.parents('.media-left, .media-right, .carousel-item');
        img.detach();
        medium.children('a').append(img);
        container.parents('.ajaxcover').remove();
      }
    } else {
      img.remove();
      source.remove();
      if (typeof response.data.html !== 'undefined') {
        container.html(response.data.html);
      } else {
        container.html();
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
