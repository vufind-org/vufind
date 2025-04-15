/*global VuFind, unwrapJQuery */
VuFind.register('identifierLinks', function identifierLinks() {
  /**
   * Embed identifier links in a container.
   * @param {Element} el Container for links
   */
  function embedIdentifierLinks(el) {
    var queryParams = new URLSearchParams();
    var elements = el.classList.contains('identifierLink') ? [el] : el.querySelectorAll('.identifierLink');
    var postBody = {};
    elements.forEach(function extractIdentifierData(identifierLinkEl) {
      var currentInstance = identifierLinkEl.dataset.instance;
      if (typeof postBody[currentInstance] === "undefined") {
        let currentIdentifiers = {};
        ["doi", "issn", "isbn"].forEach(identifier => {
          if (typeof identifierLinkEl.dataset[identifier] !== "undefined") {
            currentIdentifiers[identifier] = identifierLinkEl.dataset[identifier];
          }
        });
        if (Object.keys(currentIdentifiers).length > 0) {
          postBody[currentInstance] = currentIdentifiers;
        }
      }
    });
    if (Object.keys(postBody).length === 0) {
      return;
    }
    queryParams.set("method", "identifierLinksLookup");
    var url = VuFind.path + '/AJAX/JSON?' + queryParams.toString();
    fetch(url, { method: "POST", body: JSON.stringify(postBody) })
      .then(function embedIdentifierLinksDone(rawResponse) {
        elements.forEach(function populateIdentifierLinks(identifierEl) {
          var currentInstance = identifierEl.dataset.instance;
          rawResponse.json().then(response => {
            if ("undefined" !== typeof response.data[currentInstance]) {
              VuFind.setInnerHtml(identifierEl, response.data[currentInstance]);
            }
          });
        });
      });
  }

  /**
   * Event handler to embed identifier links in a container.
   * @param {Event} params Event triggering handler
   */
  function updateContainer(params) {
    embedIdentifierLinks(params.container);
  }

  /**
   * Apply identifier-based links. This can be called with a container e.g. when
   * combined results fetched with AJAX are loaded.
   * @param {object} _container Container to apply links to
   */
  function init(_container) {
    var container = unwrapJQuery(_container || document.body);
    // assign action to the openUrlWindow link class
    if (VuFind.isPrinting()) {
      embedIdentifierLinks(container);
    } else {
      VuFind.observerManager.createIntersectionObserver(
        'identifierLinks',
        embedIdentifierLinks,
        Array.from(container.querySelectorAll('.identifierLink'))
      );
    }
    VuFind.listen('results-init', updateContainer);
  }
  return {
    init: init,
    embedIdentifierLinks: embedIdentifierLinks
  };
});
