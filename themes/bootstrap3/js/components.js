/**
 * Sources:
 * - Menu: https://www.w3.org/WAI/ARIA/apg/patterns/menubar/
 * - Menubuttons: https://www.w3.org/WAI/ARIA/apg/patterns/menu-button/
 */

// MenuButton: When the menu is displayed, the element with role button has aria-expanded set to true.
function ariaExpand(container, toggle) {
  container.classList.add("is-open");
  toggle.setAttribute("aria-expanded", true);
}

// MenuButton: When the menu is hidden, it is recommended that aria-expanded is not present.
// MenuButton: If aria-expanded is specified when the menu is hidden, it is set to false.
function ariaCollapse(container, toggle) {
  container.classList.remove("is-open");
  toggle.removeAttribute("aria-expanded");
}

// Menu: https://www.w3.org/WAI/ARIA/apg/patterns/menu
function bindAriaMenu(menuList, controller = null) {
  const menuitems = menuList.querySelectorAll(`[role="menuitem"]`);
  let currentIndex = 0;

  function escape(event) {
    if (controller) {
      controller(event);
    }
  }

  function prev() {
    currentIndex = (currentIndex + menuitems.length - 1) % menuitems.length;
    menuitems[currentIndex].focus();
  }
  function next() {
    currentIndex = (currentIndex + 1) % menuitems.length;
    menuitems[currentIndex].focus();
  }

  function focusFirst() {
    currentIndex = 0;
    menuitems[currentIndex].focus();
  }

  function focusLast() {
    currentIndex = menuitems.length - 1;
    menuitems[currentIndex].focus();
  }

  menuList.addEventListener("keydown", (event) => {
    switch (event.key) {
    case "Esc":
    case "Escape":
      escape(event);
      break;

    case "ArrowUp":
      prev();
      break;

    case "ArrowDown":
      next();
      break;

    case "Home":
      focusFirst();
      break;

    case "End":
      focusLast();
      break;

    case " ":
    case "Enter":
      menuitems[currentIndex].click();
      break;

    default:
      return;
    }

    event.preventDefault();
    if (controller === null) {
      event.stopPropagation();
    }
  });

  return {
    focusFirst,
    focusLast,
  };
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

    // MenuButton: The element that contains the menu menuitems displayed by activating the button has role menu.
    const targetEl = menu.querySelector(".confirm__options");
    toggleEl.setAttribute("role", "menu");

    // Confirm action either link or submit, so no event needed
    const confirmEl = document.querySelector(".confirm__confirm");

    const cancelEl = document.querySelector(".confirm__cancel");
    // Close menu on cancel
    cancelEl.addEventListener("click", () => {
      ariaCollapse(menu, toggleEl, targetEl);
      toggleEl.focus();
    });

    // Menu: The menuitems contained in a menu are child elements of the containing menu or menubar and have any of the following roles: menuitem, menuitemcheckbox, menuitemradio
    confirmEl.setAttribute("role", "menuitem");
    cancelEl.setAttribute("role", "menuitem");

    const ariaMenu = bindAriaMenu(
      document.querySelector(".confirm__menu"),
      // MenuButton: Escape: close menu, focus trigger
      function menuListBubble(event) {
        if (
          event.key === "Esc" ||
          event.key === "Escape"
        ) {
          ariaCollapse(menu, toggleEl, targetEl);
          toggleEl.focus();

          event.stopPropagation();
          event.preventDefault();
        }
      }
    );

    // MenuButton: click to toggle
    toggleEl.addEventListener("click", () => {
      if (toggleEl.getAttribute("aria-expanded")) {
        ariaCollapse(menu, toggleEl, targetEl);
        toggleEl.focus();
      } else {
        ariaExpand(menu, toggleEl, targetEl);
        ariaMenu.focusFirst();
      }
    });

    // MenuButton: Enter: opens the menu and places focus on the first menu item.
    // MenuButton: Space: Opens the menu and places focus on the first menu item.
    // MenuButton: (Optional) Down Arrow: opens the menu and moves focus to the first menu item.
    // MenuButton: (Optional) Up Arrow: opens the menu and moves focus to the last menu item.
    toggleEl.addEventListener("keydown", (event) => {
      switch (event.key) {
      case "ArrowDown":
      case "Down":
        ariaExpand(menu, toggleEl, targetEl);
        ariaMenu.focusFirst();
        break;

      case "Up":
      case "ArrowUp":
        ariaExpand(menu, toggleEl, targetEl);
        ariaMenu.focusLast();
        break;

      default:
        return;
      }

      event.stopPropagation();
      event.preventDefault();
    });

    // MenuButton: Optionally, the element with role button has a value specified for aria-controls that refers to the element with role menu.
    // MenuButton: Additional roles, states, and properties needed for the menu element are described in the Menu and Menubar Pattern.
  });
}

document.addEventListener("DOMContentLoaded", function componentsReady() {
  bindConfirmMenus();
});
