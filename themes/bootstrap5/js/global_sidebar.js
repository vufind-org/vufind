/* global bootstrap, VuFind */

VuFind.register('globalSidebar', function GlobalSidebar() {
  var _COOKIE = 'vufind_global_sidebar_collapsed';

  /**
   * Initialize the global sidebar module.
   */
  function init() {
    const globalSidebar = document.getElementById('global-sidebar');
    const globalSidebarToggle = document.getElementById('global-sidebar-toggle');
    const globalSidebarToggleLabel = globalSidebarToggle.querySelector('.icon-link__label');
    const mediaQuery = window.matchMedia('(max-width: 768px)');

    /**
     * Collapse or expand the sidebar based on media query and possible cookie.
     * @param {MediaQueryListEvent} e Event
     */
    function onMediaQueryChange(e) {
      if (e.matches) {
        // Always collapse when screen size is small.
        globalSidebar.classList.add('collapsed');
      } else {
        const cookieValue = VuFind.cookie.get(_COOKIE);
        if (!cookieValue || cookieValue === 'true') {
          globalSidebar.classList.add('collapsed');
        } else {
          globalSidebar.classList.remove('collapsed');
        }
      }
    }

    /**
     * React to global sidebar toggle being clicked.
     */
    function onGlobalSidebarToggleClick() {
      // Set class and cookie.
      globalSidebar.classList.toggle('collapsed');
      VuFind.cookie.set(_COOKIE, globalSidebar.classList.contains('collapsed'));

      // Update label and tooltip text.
      const collapsed = globalSidebar.classList.contains('collapsed');
      const labelText = VuFind.translate(collapsed ? 'sidebar_expand' : 'sidebar_close');
      globalSidebarToggle.setAttribute('data-bs-title', labelText);
      globalSidebarToggleLabel.textContent = labelText;
      const instance = bootstrap.Tooltip.getInstance(globalSidebarToggle);
      instance.setContent({ '.tooltip-inner': labelText });

      // Hide tooltip when collapsing.
      if (instance) {
        instance.hide();
      }
      globalSidebarToggle.blur();
    }

    /**
     * Prevent showing Bootstrap tooltips if the sidebar is not collapsed.
     * @param {Event} e Event
     */
    function onGlobalSidebarShowBsTooltip(e) {
      if (!globalSidebar.classList.contains('collapsed')) {
        e.preventDefault();
      }
    }

    globalSidebar.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((subEl) => bootstrap.Tooltip.getOrCreateInstance(subEl));
    globalSidebar.addEventListener('show.bs.tooltip', onGlobalSidebarShowBsTooltip);
    globalSidebarToggle.addEventListener('click', onGlobalSidebarToggleClick);
    mediaQuery.addEventListener('change', onMediaQueryChange);
    onMediaQueryChange(mediaQuery);
  }

  return {
    init: init,
  };
});
