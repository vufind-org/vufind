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
        aEl.classList.add('nav-link');
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

  function init() {
    initNav();
    initNavbar();
    initFormElements();
    initBreadcrumbs();
    initCollapse();
    initPagination();
  }

  return {
    init: init
  };
});
