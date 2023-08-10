/*global VuFind */

// Useful function to delay callbacks, e.g. when using a keyup event
// to detect when the user stops typing.
// See also: https://stackoverflow.com/questions/1909441/how-to-delay-the-keyup-handler-until-the-user-stops-typing
var keyupCallbackTimeout = null;
function registerFacetListContentKeyupCallback() {
  $('.ajax_param[data-name="contains"]').on('keyup', function onKeyupChangeFacetList() {
    clearTimeout(keyupCallbackTimeout);
    keyupCallbackTimeout = setTimeout(function onKeyupTimeout() {
      updateFacetListContent();
    }, 500);
  });
}

function getFacetListContent(overrideParams={}) {
  let url = VuFind.path + "/AJAX/JSON?q=sta&method=getFacetListContent";

  $('.ajax_param').each(function ajaxParamEach() {
    let key = $(this).data('name');
    let val = $(this).val();
    if (key in overrideParams) {
      val = overrideParams[key];
    }
    url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(val);
  });

  return Promise.resolve($.ajax({
    type: "GET",
    url: url,
    dataType: "json"
  }));
}

function updateFacetListContent() {
  getFacetListContent().then(json => {
    $('#facet-info-result').html(json.data.html);
    // This needs to be registered here as well so it works in a lightbox
    registerFacetListContentKeyupCallback();
  });
}

function setupFacetList() {
  $('.ajax_param[data-name="contains"]').on('keyup', function onKeyupChangeFacetList() {
    registerFacetListContentKeyupCallback();
  });
}
