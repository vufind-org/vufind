/*global VuFind */
function loadCover(data, selector) {
  var url = VuFind.path + '/AJAX/JSON?method=' + 'getRecordCover';
  function coverCallback(response) {
    if (response.data.url !== false) {
      $(this.elementSelector).attr("src", response.data.url);
    }
  }
  $.ajax({
    dataType: "json",
    url: url,
    method: "GET",
    data: data,
    elementSelector: selector,
    success: coverCallback
  });
}

function loadCoversForResults(size) { // eslint-disable-line no-unused-vars
  var results = $('div.result');
  var ids = [];
  results.each(function addId(index, element) {
    ids.push({
      elementId: $(element).attr("id"),
      data: {
        source: $(this).find(".hiddenSource").val(),
        recordId: $(this).find(".hiddenId").val(),
        size: (typeof size !== 'undefined') ? size : 'small'
      }
    });
  });
  ids.forEach(function batchLoadCovers(value) {
    loadCover(value.data, "#" + value.elementId + " .recordcover");
  });
}

function loadCoverForDetail(size) { // eslint-disable-line no-unused-vars
  var data = {
    source: $(".record .hiddenSource").val(),
    recordId: $(".record .hiddenId").val(),
    size: (typeof size !== 'undefined') ? size : 'small'
  };
  loadCover(data, ".record .recordcover");
}
