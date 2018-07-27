/*global Hunt, VuFind */
VuFind.register('doi', function Doi() {
  function embedDoiLinks(el) {
    var element = $(el);
    // Extract the OpenURL associated with the clicked element:
    var doi = element.children('.doiLink');
    alert('DOI!');
  }

  // Assign actions to the OpenURL links. This can be called with a container e.g. when
  // combined results fetched with AJAX are loaded.
  function init(_container) {
    var container = _container || $('body');
    // assign action to the openUrlWindow link class
    if (typeof Hunt === 'undefined') {
      embedDoiLinks(container)
    } else {
      new Hunt(
        container.find('.doiLink').toArray(),
        { enter: embedDoiLinks }
      );
    }
  }
  return {
    init: init,
    embedDoiLinks: embedDoiLinks
  };
});
