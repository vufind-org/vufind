/**
 * Sources:
 * - Menu: https://www.w3.org/WAI/ARIA/apg/patterns/menubar/
 * - Menubuttons: https://www.w3.org/WAI/ARIA/apg/patterns/menu-button/
 */

// MenuButton: When the menu is displayed, the element with role button has aria-expanded set to true.
function ariaExpand(container, toggle, target) {
  container.classList.add("is-open");
  toggle.setAttribute("aria-expanded", true);
}

// MenuButton: When the menu is hidden, it is recommended that aria-expanded is not present.
// MenuButton: If aria-expanded is specified when the menu is hidden, it is set to false.
function ariaCollapse(container, toggle, target) {
  container.classList.remove("is-open");
  toggle.removeAttribute("aria-expanded");
}

function ariaToggle(container, toggle, target) {
  if (toggle.getAttribute("aria-expanded")) {
    ariaCollapse(container, toggle, target);
  } else {
    ariaExpand(container, toggle, target);
  }
}

//
// confirm-menu
//

// Treat like MenuButton
function bindConfirmMenus() {
  document.querySelectorAll(".confirm-menu").forEach((menu) => {
    const isOpen = menu.classList.contains("is-open");

    // MenuButton: The element that opens the menu has role button.
    // MenuButton: The element with role button has aria-haspopup set to either menu or true.
    const toggleEl = menu.querySelector(".confirm__toggle");
    toggleEl.setAttribute("role", "button");
    toggleEl.setAttribute("aria-haspopup", true);
    if (isOpen) {
      toggleEl.setAttribute("aria-expanded", true);
    }

    // MenuButton: The element that contains the menu items displayed by activating the button has role menu.
    //
    const targetEl = menu.querySelector(".confirm__options");
    toggleEl.setAttribute("role", "menu");

    // Confirm action either link or submit, so no event needed
    const confirmEl = document.querySelector(".confirm__confirm");

    const cancelEl = document.querySelector(".confirm__cancel")
    // Close menu on cancel
    cancelEl.addEventListener("click", (event) => {
      ariaCollapse(menu, toggleEl, targetEl);
      toggleEl.focus();
    }, false);

    // Menu: The items contained in a menu are child elements of the containing menu or menubar and have any of the following roles: menuitem, menuitemcheckbox, menuitemradio
    confirmEl.setAttribute("role", "menuitem");
    cancelEl.setAttribute("role", "menuitem");

    // MenuButton: click to toggle
    toggleEl.addEventListener("click", (event) => {
      if (toggleEl.getAttribute("aria-expanded")) {
        ariaCollapse(menu, toggleEl, targetEl);
        toggleEl.focus();
      } else {
        ariaExpand(menu, toggleEl, targetEl);
        confirmEl.focus();
      }
    }, false);

    // MenuButton: Enter: opens the menu and places focus on the first menu item.
    // MenuButton: Space: Opens the menu and places focus on the first menu item.
    // MenuButton: (Optional) Down Arrow: opens the menu and moves focus to the first menu item.
    // MenuButton: (Optional) Up Arrow: opens the menu and moves focus to the last menu item.
    toggleEl.addEventListener("keydown", (event) => {
      switch(event.key) {
        case "ArrowDown":
        case "Down":
          ariaExpand(menu, toggleEl, targetEl);
          confirmEl.focus(); // first element
          break;

        case "Up":
        case "ArrowUp":
          ariaExpand(menu, toggleEl, targetEl);
          cancelEl.focus(); // last element
          break;

        case "Esc":
        case "Escape":
          ariaCollapse(menu, toggleEl, targetEl);
          toggleEl.focus();
          break;

        default:
          return;
      }

      event.stopPropagation();
      event.preventDefault();
    }, false);

    // MenuButton: Optionally, the element with role button has a value specified for aria-controls that refers to the element with role menu.
    // MenuButton: Additional roles, states, and properties needed for the menu element are described in the Menu and Menubar Pattern.
  });
}

$(document).ready(function componentsReady() {
  bindConfirmMenus();
});
