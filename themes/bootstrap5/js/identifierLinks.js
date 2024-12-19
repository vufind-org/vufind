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
              identifierEl.textContent = "";
              for (var i = 0; i < response.data[currentInstance].length; i++) {
                var newLink = document.createElement('a');
                newLink.classList.add('icon-link');
                newLink.setAttribute('href', response.data[currentInstance][i].link);
                if (typeof response.data[currentInstance][i].icon !== 'undefined') {
                  var icon = document.createElement('img');
                  icon.setAttribute('src', response.data[currentInstance][i].icon);
                  icon.classList.add("identifier-link-icon");
                  icon.classList.add("icon-link__icon");
                  newLink.appendChild(icon);
                } else if (typeof response.data[currentInstance][i].localIcon !== 'undefined') {
                  var localIconWrapper = document.createElement('span');
                  VuFind.setInnerHtml(localIconWrapper, response.data[currentInstance][i].localIcon);
                  var localIcon = localIconWrapper.firstChild;
                  if (localIcon) {
                    localIcon.classList.add('icon-link__icon');
                    newLink.appendChild(localIcon);
                  }
                }
                var newSpan = document.createElement('span');
                newSpan.setAttribute("rel", "noreferrer");
                if (response.data[currentInstance][i].newWindow) {
                  newSpan.setAttribute("target", '_blank');
                }
                newSpan.classList.add('icon-link__label');
                newSpan.appendChild(document.createTextNode(response.data[currentInstance][i].label));
                newLink.appendChild(newSpan);
                identifierEl.appendChild(newLink);
                identifierEl.appendChild(document.createElement('br'));
              }
            }
          });
        });
      });
  }

  /**
   * Event handler to embed identifier links in a container.
   * @param {Event} params
   */
  function updateContainer(params) {
    embedIdentifierLinks(params.container);
  }

  /**
   * Apply identifier-based links. This can be called with a container e.g. when
   * combined results fetched with AJAX are loaded.
   * @param {object} _container Container to apply links to
   * @returns {object} Container exposing public methods
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
