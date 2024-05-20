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
    document.querySelectorAll('.nav').forEach((navEl) => {
      // tablist role for tabs:
      if (navEl.classList.contains('nav-tabs')) {
        navEl.setAttribute('role', 'tablist');
      }
      navEl.querySelectorAll('li').forEach((liEl) => {
        const aEl = liEl.querySelector(':scope > a');
        if (liEl.classList.contains('dropdown__item')) {
          if (aEl && !aEl.classList.contains('btn')) {
            aEl.classList.add('dropdown-item');
            if (liEl.classList.contains('active')) {
              aEl.classList.add('active');
            }
          }
        } else if (aEl) {
          if (!aEl.classList.contains('btn')) {
            aEl.classList.add('nav-link');
          }
          if (liEl.classList.contains('active')) {
            aEl.classList.add('active');
            if (null === liEl.closest('.searchForm')) {
              liEl.classList.remove('active');
            }
          }
        }
        // Move tab role from li to a:
        if (aEl && liEl.parentElement.classList.contains('nav-tabs')) {
          liEl.setAttribute('role', 'presentation');
          liEl.classList.add('nav-item');
          aEl.classList.add('nav-link');
          aEl.setAttribute('role', 'tab');
          if (aEl.classList.contains('active')) {
            aEl.setAttribute('aria-selected', 'true');
          }
        }
      });
    });

    // Reverse effects of record.js:
    setTimeout(
      () => {
        $('.record-tabs .nav-tabs a').off('shown.bs.tab');
        document.querySelectorAll('.record-tabs .nav-tabs li').forEach((liEl) => {
          liEl.removeAttribute('aria-selected');
        });
      },
      0
    );
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
    document.querySelectorAll('[data-dismiss],[data-target],[data-toggle],[data-ride],[data-slide],[data-slide-to]').forEach((el) => {
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
    initNavbar();
    initNav();
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
