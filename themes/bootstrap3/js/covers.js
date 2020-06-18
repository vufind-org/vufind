/*global VuFind */
function loadCoverByElement(data, element) {
  var url = VuFind.path + '/AJAX/JSON?method=' + 'getRecordCover';
  function coverCallback(response) {
    if (response.data.url !== false) {
      element.attr("src", response.data.url);
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
  $('.recordcover').each(function getDataAndLoadCovers() {
    var data = {
      source: $(this).data('recordsource'),
      recordId: $(this).data('recordid'),
      size: $(this).data('coversize')
    };
    loadCoverByElement(data, $(this));
  });
}
$(document).ready(loadCovers);
