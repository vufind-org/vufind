let KEY_CODE = {
  DOWN_ARROW: 40,
  UP_ARROW: 38,
};
$(function pageLoad() {
  let modal = document.querySelector('#modal');

  /**
   * Whether the dropdown is open
   * @returns {boolean}
   */
  function isDropdownOpen() {
    return modal.querySelector(".get-this-dropdown > ul > li > div").classList.contains("active");
  }

  /**
   * Whether the link for the current holding is the element being clicked
   * @param e
   * @returns {*}
   */
  function isDropdownLinkTargeted(e) {
    return e.target.matches(".get-this-dropdown > ul > li > a");
  }

  /**
   * Open / close the dropdown
   */
  function toggleDropdown() {
    const a = modal.querySelector(".get-this-dropdown > ul > li > a");
    const siblingDiv = modal.querySelector(".get-this-dropdown > ul > li > div");

    if (siblingDiv) {
      siblingDiv.classList.toggle("active");
    }

    const ariaExpanded = a.getAttribute("aria-expanded");
    a.setAttribute("aria-expanded", ariaExpanded === "true" ? "false" : "true");

    if (isDropdownOpen()) {
      const firstOption = document.querySelector(".get-this-dropdown div a");
      if (firstOption) firstOption.focus();
    }
  }

  /**
   * Listener on events to open the dropdown
   * @param e
   */
  function openDropdownListener(e) {
    if (isDropdownLinkTargeted(e)) {
      toggleDropdown();
      e.preventDefault();
    }
  }

  /**
   * Listener on events to close the dropdown
   */
  function closeDropdownListener() {
    if (isDropdownOpen()) {
      // Need delay to let new focus to happen
      setTimeout(() => {
        if (modal.querySelector(".get-this-dropdown").contains(document.activeElement)) return;
        toggleDropdown();
      }, 1);
    }
  }

  /**
   * Listener on events to open the dropdown if bottom arrow is pressed
   * @param e
   */
  function openDropdownOnKeyDown(e) {
    if (isDropdownLinkTargeted(e) && !isDropdownOpen() && e.keyCode === KEY_CODE.DOWN_ARROW) {
      toggleDropdown();
      e.preventDefault();
    }
  }

  /**
   * Switch the focus to a sibling element in the dropdown
   * @param elem
   * @returns {boolean}
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
   * @param e
   */
  function arrowKeysListener(e) {
    const dropdown = document.querySelector(".get-this-dropdown div");
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

  modal.addEventListener("click", openDropdownListener);
  modal.addEventListener("focusout", closeDropdownListener);
  modal.addEventListener("keydown", openDropdownOnKeyDown);
  modal.addEventListener("keydown", arrowKeysListener);
});
