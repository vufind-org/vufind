/*global VuFind, unwrapJQuery */
VuFind.register('doi', function Doi() {
  function embedDoiLinks(el) {
    var doi = [];
    var elements = el.classList.contains('doiLink') ? [el] : el.querySelectorAll('.doiLink');
    elements.forEach(function extractDoiData(doiLinkEl) {
      var currentDoi = doiLinkEl.dataset.doi;
      if (doi.indexOf(currentDoi) === -1) {
        doi[doi.length] = currentDoi;
      }
    });
    if (doi.length === 0) {
      return;
    }
    var url = VuFind.path + '/AJAX/JSON?' + $.param({
      method: 'doiLookup',
      doi: doi,
    });
    fetch(url, { method: "GET" })
      .then(function embedDoiLinksDone(response) {
        elements.forEach(function populateDoiLinks(doiEl) {
          var currentDoi = doiEl.dataset.doi;
          response.json().then(response => {
            if ("undefined" !== typeof response.data[currentDoi]) {
              doiEl.innerHTML = "";
              for (var i = 0; i < response.data[currentDoi].length; i++) {
                var newLink = document.createElement('a');
                newLink.classList.add('icon-link');
                newLink.setAttribute('href', response.data[currentDoi][i].link);
                if (typeof response.data[currentDoi][i].icon !== 'undefined') {
                  var icon = document.createElement('img');
                  icon.setAttribute('src', response.data[currentDoi][i].icon);
                  icon.classList.add("doi-icon");
                  icon.classList.add("icon-link__icon");
                  newLink.appendChild(icon);
                } else if (typeof response.data[currentDoi][i].localIcon !== 'undefined') {
                  var localIconWrapper = document.createElement('span');
                  localIconWrapper.innerHTML = response.data[currentDoi][i].localIcon;
                  var localIcon = localIconWrapper.firstChild;
                  if (localIcon) {
                    localIcon.classList.add('icon-link__icon');
                    newLink.appendChild(localIcon);
                  }
                }
                var newSpan = document.createElement('span');
                newSpan.setAttribute("rel", "noreferrer");
                if (response.data[currentDoi][i].newWindow) {
                  newSpan.setAttribute("target", '_blank');
                }
                newSpan.classList.add('icon-link__label');
                newSpan.appendChild(document.createTextNode(response.data[currentDoi][i].label))
                newLink.appendChild(newSpan);
                doiEl.appendChild(newLink);
                doiEl.appendChild(document.createElement('br'));
              }
            }
          });
        });
      });
  }

  function updateContainer(params) {
    embedDoiLinks(params.container);
  }

  // Assign actions to the OpenURL links. This can be called with a container e.g. when
  // combined results fetched with AJAX are loaded.
  function init(_container) {
    var container = unwrapJQuery(_container || document.body);
    // assign action to the openUrlWindow link class
    if (VuFind.isPrinting()) {
      embedDoiLinks(container);
    } else {
      VuFind.observerManager.createIntersectionObserver(
        'doiLinks',
        embedDoiLinks,
        Array.from(container.querySelectorAll('.doiLink'))
      );
    }
    VuFind.listen('results-init', updateContainer);
  }
  return {
    init: init,
    embedDoiLinks: embedDoiLinks
  };
});
