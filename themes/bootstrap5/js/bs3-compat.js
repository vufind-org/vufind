/*global VuFind*/
VuFind.register('bootstrap3CompatibilityLayer', function bootstrap3CompatibilityLayer() {
  const data_attribute_selector = '[data-dismiss],[data-target],[data-toggle],[data-ride],[data-slide],[data-slide-to]';

  function initPagination() {
    document.querySelectorAll('.pagination li').forEach((el) => {
      el.classList.add('page-item');
      const linkEl = el.querySelector('a');
      if (linkEl) {
        linkEl.classList.add('page-link');
      } else {
        el.innerHTML = '<a href="#" class="page-link">' + el.innerHTML + '</a>';
      }
    });
  }

  function convertDataAttributes(el) {
    if (typeof el.getAttribute === 'undefined') {
      return;
    }
    const attrs = ['dismiss', 'target', 'toggle'];
    attrs.forEach((attr) => {
      const val = el.getAttribute('data-' + attr);
      if (null !== val) {
        el.setAttribute('data-bs-' + attr, val);
      }
    });
  }

  function initDataAttributeMappings() {
    document.querySelectorAll(data_attribute_selector).forEach((el) => convertDataAttributes(el));
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((el) => {
          convertDataAttributes(el);
          if (typeof el.querySelectorAll !== 'undefined') {
            el.querySelectorAll(data_attribute_selector).forEach((subEl) => convertDataAttributes(subEl));
          }
        });
      });
    });
    observer.observe(document, { subtree: true, childList: true });
  }

  function init() {
    initPagination();
    initDataAttributeMappings();
  }

  return {
    init: init
  };
});
