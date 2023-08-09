/*global VuFind */

// Useful function to delay callbacks, e.g. when using a keyup event
// to detect when the user stops typing.
// See also: https://stackoverflow.com/questions/1909441/how-to-delay-the-keyup-handler-until-the-user-stops-typing
function getFacetListContentKeyupCallback() {
  var timeout = null;
  $('.ajax_param[data-name="contains"]').on('keyup', function onKeyupChangeFacetList() {
    clearTimeout(timeout);
    timeout = setTimeout(function onKeyupTimeout() {
      getFacetListContent();
    }, 500);
  });
}

function getFacetListContent() {
  let url = VuFind.path + "/AJAX/JSON?q=sta&method=getFacetListContent";

  $('.ajax_param').each(function ajaxParamEach() {
    url += '&' + encodeURIComponent($(this).data('name')) + '=' + encodeURIComponent($(this).val());
  });

  $.ajax({
    type: "GET",
    url: url,
    dataType: "json",
    success: function (json) {
      $('#facet-info-result').html(json.data.html);
    }
  });

  // This needs to be registered here as well so it works in a lightbox
  getFacetListContentKeyupCallback();
}

function setupFacetList() {
  $('.ajax_param[data-name="contains"]').on('keyup', function onKeyupChangeFacetList() {
    getFacetListContentKeyupCallback();
  });
}
