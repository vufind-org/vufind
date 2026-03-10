/* #17601 - set focus on sidebar back button - HR */

function setupOffcanvas() {
  if ($('.sidebar').length > 0 && $(document.body).hasClass("offcanvas")) {
    $('[data-toggle="offcanvas"]').click(function offcanvasClick(e) {
      e.preventDefault();
      $('body.offcanvas').toggleClass('active');
      $('.close-offcanvas').focus(); // Set focus to the back button of the sidebar
      $('.sidebar .active').focus(); // Special case: account sidebar: set focus on the active element
    });

    // Keep focus within sidebar on mobile
    $('.sidebar').on('keydown', function (e) {
      if (document.body.classList.contains('active') && $(document).width() <= 767) {
        if (e.key === 'Escape') { // esc
          $('.close-offcanvas').click();
        }
        if (e.key === 'Tab') { // tab
          retainFocus(e, this);
        }
      }
    });
  }
  if ($('.sidebar').length > 0) {
    // Handle sidebar in case of config.ini [Site] offcanvas=false configuration
    if (!($(document.body).hasClass("offcanvas"))) {
      $('[data-toggle="offcanvas"]').click(function offcanvasClick(e) {
        e.preventDefault();
        window.location.href = '#myresearch-sidebar';
        $('.close-offcanvas').focus(); // Set focus to the back button of the sidebar
        $('.sidebar .active').focus(); // Special case: account sidebar: set focus on the active element
      });
    }
    // Handle sidebar in case of config.ini [Site] offcanvas=false configuration - END

    $('.close-offcanvas').click(function offcanvasClick(e) {
      // Handle sidebar in case of config.ini [Site] offcanvas=false configuration
      if (!($(document.body).hasClass("offcanvas"))) {
        e.preventDefault();
        window.location.href = '#content';
      }
      $('.search-filter-toggle').focus(); // Set focus on the toggle button
    });
  }
}

/**
 * Keyboard and focus controllers
 * Copied from lightbox.js
 */
var FOCUSABLE_ELEMENTS = ['a[href]', 'area[href]', 'input:not([disabled]):not([type="hidden"]):not([aria-hidden])', 'select:not([disabled]):not([aria-hidden])', 'textarea:not([disabled]):not([aria-hidden])', 'button:not([disabled]):not([aria-hidden])', 'iframe', 'object', 'embed', '[contenteditable]', '[tabindex]:not([tabindex^="-"])'];
function getFocusableNodes (element) {
  let nodes = element.querySelectorAll(FOCUSABLE_ELEMENTS);
  return Array.apply(null, nodes);
}

function retainFocus(event, panel) {
  var focusableNodes = getFocusableNodes(panel);

  // no focusable nodes
  if (focusableNodes.length === 0)
    return;

  if (!panel.contains(document.activeElement)) {
    focusableNodes[0].focus();
  } else {
    var focusedItemIndex = focusableNodes.indexOf(document.activeElement);

    if (event.shiftKey && focusedItemIndex === 0) {
      focusableNodes[focusableNodes.length - 1].focus();
      event.preventDefault();
    }

    if (!event.shiftKey && focusableNodes.length > 0 && focusedItemIndex === focusableNodes.length - 1) {
      focusableNodes[0].focus();
      event.preventDefault();
    }
  }
}

/* use css class 'skip-to' to jump to landmark - RL */
$(document).ready(function () {
  $(document).on('click enter',".skip-to", function (e) {
    if (e.type === 'enter' && e.key !== 'Enter') {
      return;
    }

    if (this.hash) {
      e.stopPropagation();
      e.preventDefault();
      // #23960,#25945 open facet group listing if not shown by default
      $(this.hash).closest('div').collapse('show');
      let offset = $(this.hash).offset().top;
      let clusterContainer = $(this.hash).closest('.facet-group');
      if (clusterContainer.length && clusterContainer.height() < screen.height / 2) {
        // scroll to parent container if possible
        offset = $(this.hash).closest('.facet-group').offset().top;
      }
      let banner = $('.banner');
      if (banner !== undefined && banner.length > 0) {
        offset -= banner.height() + 10;
      }
      $('html, body').animate(
          {scrollTop: offset}, 500, 'swing', $(this.hash).focus()
      );
    }
  });
});