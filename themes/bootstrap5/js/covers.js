/*global VuFind */
VuFind.register('covers', function covers() {
  /**
   * Load a cover image for a single element by making an AJAX call.
   * @param {object}      data    An object containing the cover data.
   * @param {HTMLElement} element The element to update with the cover image.
   */
  function loadCoverByElement(data, element) {
    var img = element.querySelector('img');
    var spinner = element.querySelector('div.spinner');
    var container = element.querySelector('div.cover-container');
    var source = document.createElement('p');
    source.classList.add('cover-source');
    source.innerText = VuFind.translate('cover_source_label');
    var context = data.context;
    /**
     * Callback function for the AJAX cover request.
     * @param {object} response The AJAX response object.
     */
    function coverCallback(response) {
      if (typeof response.data.url !== 'undefined' && response.data.url !== false) {
        img.src = response.data.url;
        var inlink = element.parentElement.matches('a.record-cover-link');
        var medium = img.closest('.media-left, .media-right, .carousel-item, .grid-body');
        if (typeof response.data.backlink_text !== 'undefined') {
          if (typeof response.data.backlink_url !== 'undefined') {
            var link = document.createElement('a');
            link.href = response.data.backlink_url;
            link.classList.add('cover-backlink');
            link.target = "_blank";
            link.innerText = response.data.backlink_text;
            source.appendChild(link);
          } else {
            var span = document.createElement('span');
            span.classList.add('cover-source-text');
            span.innerText = response.data.backlink_text;
            source.appendChild(span);
          }
          var backlink_locations = response.data.backlink_locations;
          if (backlink_locations.indexOf(context) >= 0) {
            if (inlink === true) {
              medium.appendChild(source);
            } else {
              container.appendChild(source);
            }
          }
        }
        if (inlink === true) {
          img.parentNode.removeChild(img);
          var mediumLink = medium.querySelector('a');
          mediumLink.parentNode.insertBefore(img, mediumLink);
          container.closest('.ajaxcover').remove();
        }
      } else {
        img.remove();
        source.remove();
        if (typeof response.data.html !== 'undefined') {
          VuFind.setInnerHtml(container, VuFind.updateCspNonce(response.data.html));
        } else {
          VuFind.setInnerHtml(container, '');
        }
      }
      spinner.style.display = 'none';
      container.style.display = 'block';
    }

    const queryParams = new URLSearchParams(data);
    queryParams.set('method', 'getRecordCover');
    fetch(VuFind.path + '/AJAX/JSON?' + queryParams.toString())
      .then(response => response.json())
      .then(coverCallback);
  }
  /**
   * Find and load cover images for all `.ajaxcover` elements within a container.
   * @param {HTMLElement} container The container to search for `.ajaxcover` elements.
   */
  function loadCovers(container) {
    container.querySelectorAll('.ajaxcover').forEach(
      (cover) => {
        if (cover.dataset.loaded) {
          return;
        }
        cover.dataset.loaded = true;
        var img = cover.querySelector('img');
        var data = {
          source: img.dataset.recordsource,
          recordId: img.dataset.recordid,
          size: img.dataset.coversize,
          context: img.dataset.context,
        };
        loadCoverByElement(data, cover);
      }
    );
  }
  /**
   * Check if a cover image is too small to be displayed. Unavailable images may be represented by a 1x1
   * image, and this prevents them from cluttering the interface.
   * @param {HTMLImageElement} img The image element to check.
   */
  function checkImgSize(img) {
    if (img.getBoundingClientRect().width < 2) {
      img.classList.add('hidden');
    }
    img.dataset.loaded = 'true';
  }
  /**
   * Check the loaded state of cover images within a container.
   * @param {HTMLElement} container The container to check for images.
   */
  function checkLoaded(container) {
    container.querySelectorAll('.recordcover').forEach(
      (img) => {
        if (img.dataset.loaded === undefined) {
          img.addEventListener('load', () => {
            checkImgSize(img);
          });
          if (img.complete && img.src) {
            checkImgSize(img);
          }
        }
      }
    );
  }

  /**
   * Update a container by loading covers and checking the loaded state.
   * @param {object} params An object containing the container element.
   */
  function updateContainer(params) {
    let container = params.container;
    loadCovers(container);
    checkLoaded(container);
  }

  /**
   * Initialize the covers module by loading covers on page load
   */
  function init() {
    updateContainer({container: document});
    VuFind.listen('results-init', updateContainer);
  }

  return { init: init };
});


