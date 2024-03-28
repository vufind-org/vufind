/*global VuFind*/
VuFind.register('bs3-compat', function cookie() {

  function initNavbar() {
    document.querySelectorAll('.navbar').forEach((el) => {
      el.classList.add('navbar-expand-md');
    });
    document.querySelectorAll('.navbar-toggle').forEach((el) => {
      el.classList.add('navbar-toggler');
    });
  }

  function initNav() {
    document.querySelectorAll('.nav li').forEach((el) => {
      el.classList.add('nav-item');
      const aEl = el.querySelector('a');
      if (aEl) {
        if (!aEl.classList.contains('btn')) {
          aEl.classList.add('nav-link');
        }
        if (el.classList.contains('active')) {
          aEl.classList.add('active');
        }
      }
    });
  }

  function initBreadcrumbs() {
    document.querySelectorAll('.breadcrumb li').forEach((el) => {
      el.classList.add('breadcrumb-item');
    });
  }

  function initFormElements() {
    document.querySelectorAll('select.form-control').forEach((el) => {
      el.classList.add('form-select');
    });
  }

  function initCollapse() {
    document.querySelectorAll('.collapse.in').forEach((el) => {
      el.classList.add('show');
    });
  }

  function initPagination() {
    document.querySelectorAll('.pagination li').forEach((el) => {
      el.classList.add('page-item');
      const linkEl = el.querySelector('a');
      if (linkEl) {
        linkEl.classList.add('page-link')
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
    document.querySelectorAll('[data-dismiss],[data-target],[data-toggle]').forEach((el) => {
      convertDataAttributes(el);
    });
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((el) => {
          convertDataAttributes(el);
        });
      });
    });
    observer.observe(document, { subtree: true, childList: true });
  }

  function init() {
    initNav();
    initNavbar();
    initFormElements();
    initBreadcrumbs();
    initCollapse();
    initPagination();
    initDataAttributeMappings();
  }

  return {
    init: init
  };
});
