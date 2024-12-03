/*global VuFind */
VuFind.register('covers', function covers() {
  function loadCoverByElement(data, element) {
    var img = element.querySelector('img');
    var spinner = element.querySelector('div.spinner');
    var container = element.querySelector('div.cover-container');
    var source = document.createElement('p');
    source.classList.add('cover-source');
    source.innerText = VuFind.translate('cover_source_label');
    var context = data.context;

    function coverCallback(response) {
      if (typeof response.data.url !== 'undefined' && response.data.url !== false) {
        img.src = response.data.url;
        var inlink = element.parentElement.matches('a.record-cover-link');
        var medium = img.closest('.media-left, .media-right, .carousel-item');
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

  function checkLoaded(container) {
    container.querySelectorAll('.recordcover').forEach(
      (img) => {
        if (img.dataset.loaded === undefined) {
          img.addEventListener('load', () => {
            if (img.getBoundingClientRect().width < 2) {
              img.classList.add('hidden');
            }
            img.dataset.loaded = 'true';
          });
        }
      }
    );
  }

  function updateContainer(params) {
    let container = params.container;
    loadCovers(container);
    checkLoaded(container);
  }

  function init() {
    updateContainer({container: document});
    VuFind.listen('results-init', updateContainer);
  }

  return { init: init };
});


