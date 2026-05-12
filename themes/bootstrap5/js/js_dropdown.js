/* global VuFind */

const KEY_CODE = {
  DOWN_ARROW: 40,
  UP_ARROW: 38,
};
VuFind.register('jsDropdown', function jsDropdown() {
  /**
   * Whether the dropdown is open
   * @param {Element} dropdown The dropdown we check
   * @returns {boolean} Whether the dropdown is open
   */
  function isDropdownOpen(dropdown) {
    const dropdownContent = dropdown.querySelector(".dropdown");
    return dropdownContent ? dropdownContent.classList.contains("active") : false;
  }

  /**
   * Whether the link for the current holding is the element being clicked
   * @param {Event} click Event triggered by the user
   * @returns {boolean} Whether the dropdown is targeted by the event
   */
  function isDropdownHeaderTargeted(click) {
    return click.target.matches("*[data-js-dropdown-header], *[data-js-dropdown-header] *");
  }

  /**
   * Whether to show the arrow pointing up or down for the dropdown
   * @param {Element} dropdown The dropdown we work on
   * @param {boolean} open True to show the arrow pointing up, False pointing down
   */
  function toggleDropdownArrow(dropdown, open) {
    dropdown.querySelector(".fa-close-dropdown").style.display = open ? 'block' : 'none';
    dropdown.querySelector(".fa-open-dropdown").style.display = open ? 'none' : 'block';
  }

  /**
   * Open / close the dropdown
   * @param {Element} dropdown The dropdown we work on
   */
  function toggleDropdown(dropdown) {
    const a = dropdown.querySelector("*[data-js-dropdown-header] a");
    const siblingDiv = dropdown.querySelector(".dropdown");

    if (siblingDiv) {
      siblingDiv.classList.toggle("active");
    }

    const ariaExpanded = a.getAttribute("aria-expanded");
    a.setAttribute("aria-expanded", ariaExpanded === "true" ? "false" : "true");

    const isOpen = isDropdownOpen(dropdown);
    if (isOpen) {
      const firstOption = dropdown.querySelector(".get-this-dropdown .dropdown a");
      if (firstOption) firstOption.focus();
    }
    toggleDropdownArrow(dropdown, isOpen);
  }

  /**
   * Listener on events to open the dropdown
   * @param {Event} e Event triggered by the user
   */
  function openDropdownListener(e) {
    if (isDropdownHeaderTargeted(e)) {
      toggleDropdown(this);
      e.preventDefault();
    }
  }

  /**
   * Listener on events to close the dropdown
   */
  function closeDropdownListener() {
    if (isDropdownOpen(this)) {
      // Need delay to let new focus to happen
      setTimeout(() => {
        if (this.contains(document.activeElement)) return;
        toggleDropdown(this);
      }, 1);
    }
  }

  /**
   * Listener on events to open the dropdown if bottom arrow is pressed
   * @param {Event} e Event triggered by the user
   */
  function openDropdownOnKeyDown(e) {
    if (isDropdownHeaderTargeted(e) && !isDropdownOpen(this) && e.keyCode === KEY_CODE.DOWN_ARROW) {
      toggleDropdown(this);
      e.preventDefault();
    }
  }

  /**
   * Switch the focus to a sibling element in the dropdown
   * @param {HTMLElement} elem Element to focus
   * @returns {boolean} Focus was successful
   */
  function focusSiblingElement(elem) {
    if (elem) {
      const link = elem.querySelector("a");
      if (link) link.focus();
      return true;
    }
    return false;
  }

  /**
   * Listener on keys pressed (up/down) to change the element focused in the dropdown
   * @param {Event} e Event triggered by the user
   */
  function arrowKeysListener(e) {
    const dropdown = this.querySelector(".dropdown");
    // If dropdown is not defined or the focus is on an element not in the container
    if (!dropdown || !dropdown.contains(document.activeElement)) return;

    const li = document.activeElement.closest("li");
    if (!li) return;

    if (e.keyCode === KEY_CODE.DOWN_ARROW) {
      if (focusSiblingElement(li.nextElementSibling)) {
        e.preventDefault();
      }
    } else if (e.keyCode === KEY_CODE.UP_ARROW) {
      if (focusSiblingElement(li.previousElementSibling)) {
        e.preventDefault();
      }
    }
  }

  /**
   * Initialize the js dropdown feature.
   * @param {HTMLElement} [_context] The container element to initialize. Defaults to all element having a data-js-dropdown attribute.
   */
  function init(_context) {
    if (typeof _context === "undefined") {
      document.querySelectorAll('*[data-js-dropdown]').forEach(dropdown => {
        VuFind.jsDropdown.init(dropdown);
      });
    } else {
      let context = _context;
      if (!(context instanceof Element)) {
        if ('container' in context) {
          context = context.container;
        } else {
          console.warn('No HTML element found to apply the js dropdown');
          return;
        }
      }
      if (!context.hasAttribute("data-js-dropdown")) {
        context.querySelectorAll('*[data-js-dropdown]').forEach(dropdown => {
          VuFind.jsDropdown.init(dropdown);
        });
        return;
      }
      context.addEventListener("click", openDropdownListener);
      context.addEventListener("focusout", closeDropdownListener);
      context.addEventListener("keydown", openDropdownOnKeyDown);
      context.addEventListener("keydown", arrowKeysListener);
    }
  }

  return {
    init: init,
  };
});

VuFind.listen('lightbox.rendered', VuFind.jsDropdown.init);
