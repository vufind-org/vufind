/*global finna */
finna.a11y = (function a11y() {
  /**
   * Initialize event listeners for bootstrap accessibility
   */
  function initA11y() {
    // Focus first dropdown item when a dropdown is opened
    document.addEventListener('shown.bs.dropdown', (e) => {
      const dropdownEl = e.target.closest('.dropdown');
      if (dropdownEl) {
        const firstItemEl = dropdownEl.querySelector(':scope > .dropdown-menu > li > .dropdown-item');
        if (firstItemEl) {
          firstItemEl.focus();
        }
      }
    });

    // Restore focus back to trigger element after lightbox is closed
    $(document).on('show.bs.modal', function triggerFocusShift() {
      let triggerElement = document.activeElement;
      $(document).one('hidden.bs.modal', function restoreFocus() {
        if (triggerElement) {
          triggerElement.focus();
        }
      });
    });

    // Add aria-expanded attribute to priority nav menu button
    const priorityNavToggle = document.querySelector('.priority-nav__dropdown-toggle');

    if (priorityNavToggle) {
      // Initialize the aria-expanded attribute
      priorityNavToggle.setAttribute('aria-expanded', 'false');
      priorityNavToggle.addEventListener('click', function togglePriorityNavMenu() {
        // Toggle the aria-expanded attribute
        const isExpanded = priorityNavToggle.getAttribute('aria-expanded') === 'true';
        priorityNavToggle.setAttribute('aria-expanded', !isExpanded);
      });
    }
  }
  var my = {
    init: function init() {
      initA11y();
    },
  };

  return my;
})();
