/*global VuFind*/
VuFind.register('finnaBootstrap3CompatibilityLayer', function finnaBootstrap3CompatibilityLayer() {
  /**
   * Initialize nav support
   */
  function initNav() {
    document.querySelectorAll('.nav').forEach((navEl) => {
      if (navEl.closest('.mobile-toolbar')) {
        return;
      }
      if (navEl.classList.contains('nav-tabs')) {
        // Apply active class to tab li for back-compatibility:
        const observer = new MutationObserver((mutations) => {
          mutations.forEach((mutation) => {
            if ('attributes' !== mutation.type || mutation.attributeName !== 'class') {
              return;
            }
            const target = mutation.target;
            if (target.nodeName === 'A' && target.parentNode && target.classList.contains('active')) {
              target.parentNode.classList.add('active');
            } else {
              target.parentNode.classList.remove('active');
            }
          });
        });
        observer.observe(navEl, { attributes: true, attributeFilter: ['class'], subtree: true });
      }
      navEl.querySelectorAll(':scope > li').forEach((liEl) => {
        const aEl = liEl.querySelector(':scope > a');
        if (liEl.classList.contains('dropdown__item')) {
          if (aEl && !aEl.classList.contains('btn')) {
            aEl.classList.add('dropdown-item');
            if (liEl.classList.contains('active')) {
              aEl.classList.add('active');
            }
          }
        } else if (aEl) {
          const oldStateA = VuFind.disableTransitions(aEl);
          if (!aEl.classList.contains('btn') && !aEl.closest('.browsebar')) {
            aEl.classList.add('nav-link');
          }
          if (liEl.classList.contains('active')) {
            aEl.classList.add('active');
            if (null === liEl.closest('.searchForm')) {
              liEl.classList.remove('active');
            }
          }
          // Use a timeout to allow the transition to complete before restoring the state:
          setTimeout(() => { VuFind.restoreTransitions(aEl, oldStateA); }, 0);
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

  /**
   * Initialize breadcrumb support
   */
  function initBreadcrumbs() {
    document.querySelectorAll('.breadcrumb li').forEach((el) => {
      el.classList.add('breadcrumb-item');
    });
  }

  /**
   * Initialize form element support
   */
  function initFormElements() {
    document.querySelectorAll('select.form-control').forEach((el) => {
      el.classList.add('form-select');
    });
  }

  /**
   * Initialize pagination support
   */
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

  /**
   * Initialize dropdown support
   */
  function initDropdownStyles() {
    document.querySelectorAll('ul.dropdown-menu > li > a').forEach((el) => {
      el.classList.add('dropdown-item');
    });
    document.querySelectorAll('.dropdown-menu-right').forEach((el) => {
      el.classList.add('dropdown-menu-end');
    });
  }

  /**
   * Initialize all back-compatibility support functions
   */
  function init() {
    initNav();
    initFormElements();
    initBreadcrumbs();
    initPagination();

    initDropdownStyles();
  }

  return {
    init: init
  };
});
