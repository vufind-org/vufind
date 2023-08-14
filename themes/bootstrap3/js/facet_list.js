/*global VuFind */
/*exported getFacetListContent */
/*exported setupFacetList */

// Useful function to delay callbacks, e.g. when using a keyup event
// to detect when the user stops typing.
// See also: https://stackoverflow.com/questions/1909441/how-to-delay-the-keyup-handler-until-the-user-stops-typing
var keyupCallbackTimeout = null;
function registerFacetListContentKeyupCallback() {
  $('.ajax_param[data-name="contains"]').on('keyup', function onKeyupChangeFacetList() {
    clearTimeout(keyupCallbackTimeout);
    keyupCallbackTimeout = setTimeout(function onKeyupTimeout() {
      updateFacetListContent();
      updateHrefContains();
    }, 500);
  });
}

function overrideHref(selector, overrideParams = {}) {
  $(selector).each(function overrideHrefEach() {
    let dummyDomain = 'https://www.example.org'; // we need this since the URL class cannot parse relative URLs
    let url = new URL(dummyDomain + VuFind.path + $(this).attr('href'));
    Object.entries(overrideParams).forEach(([key, value]) => {
      url.searchParams.set(key, value);
    });
    url = url.href;
    url = url.replaceAll(dummyDomain, '');
    $(this).attr('href', url);
  });
}

function updateHrefContains() {
  let overrideParams = { contains: $('.ajax_param[data-name="contains"]').val() };
  overrideHref('.js-facet-sort', overrideParams);
  overrideHref('.js-facet-next-page', overrideParams);
  overrideHref('.js-facet-prev-page', overrideParams);
}

function getFacetListContent(overrideParams = {}) {
  let url = $('.ajax_param[data-name="urlBase"]').val();

  $('.ajax_param').each(function ajaxParamEach() {
    let key = $(this).data('name');
    let val = $(this).val();
    if (key in overrideParams) {
      val = overrideParams[key];
    }
    url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(val);
  });
  url += '&ajax=1';

  return Promise.resolve($.ajax({
    url: url
  }));
}

function updateFacetListContent() {
  getFacetListContent().then(html => {
    let htmlList = '';
    $(html).find('.full-facet-list').each(function itemEach() {
      htmlList += $(this).prop('outerHTML');
    });
    $('#facet-info-result').html(htmlList);
    // This needs to be registered here as well so it works in a lightbox
    registerFacetListContentKeyupCallback();
  });
}

function setupFacetList() {
  $('.ajax_param[data-name="contains"]').on('keyup', function onKeyupChangeFacetList() {
    registerFacetListContentKeyupCallback();
  });
}
