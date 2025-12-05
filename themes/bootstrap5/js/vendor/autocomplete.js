/* https://github.com/vufind-org/autocomplete.js (v2.1.10) (2024-06-06) */
function Autocomplete(_settings) {
  const _DEFAULTS = {
    delay: 250,
    limit: 20,
    loadingString: "Loading...",
    minInputLength: 3,
    rtl: false,
  };

  if (typeof _settings === "undefined") {
    _settings = {};
  }
  const settings = Object.assign({}, _DEFAULTS, _settings);
  let list;
  let _currentItems;
  let _currentListEls;
  let _currentIndex = -1;

  function _debounce(func, delay) {
    let timeout;

    return function debounced() {
      const context = this;
      const args = [].slice.call(arguments);

      clearTimeout(timeout);
      timeout = setTimeout(function () {
        func.apply(context, args);
      }, delay);

      return timeout;
    };
  }

  function randomID() {
    return Math.random().toString(16).slice(-6);
  }

  function _align(input) {
    if (input === false) {
      return;
    }
    const inputBox = input.getBoundingClientRect();
    list.style.minWidth = inputBox.width + "px";
    list.style.top = inputBox.bottom + window.scrollY + "px";
    let anchorRight =
      settings.rtl ||
      (inputBox.left + list.offsetWidth >=
        document.documentElement.offsetWidth &&
        inputBox.right - list.offsetWidth > 0);
    if (anchorRight) {
      const posFromRight =
        document.documentElement.offsetWidth - inputBox.right;
      list.style.left = "auto"; // fixes width estimation
      list.style.right = posFromRight + "px";
    } else {
      list.style.right = "auto";
      list.style.left = inputBox.left + "px";
    }
  }

  let lastInput = false;
  function _show(input) {
    lastInput = input;
    list.style.left = "-100%"; // hide offscreen
    list.classList.add("open");
  }

  let lastCB = null;
  let debounceTimeout;
  function _hide() {
    list.classList.remove("open");
    list.innerHTML = "";

    clearTimeout(debounceTimeout);
    _currentIndex = -1;
    if (lastInput) {
      lastInput.setAttribute('aria-expanded', 'false');
    }
    lastInput = false;
    lastCB = null;
  }

  function _selectItem(item, input) {
    if (typeof item._disabled !== "undefined" && item._disabled) {
      return;
    }
    // Broadcast
    var event = new CustomEvent("ac-select", { bubbles: true, cancelable: true, detail: item });
    input.dispatchEvent(event);
    // Copy value
    if (typeof item === "string" || typeof item === "number") {
      input.value = item;
    } else if (typeof item.value === "undefined") {
      input.value = item.text;
    } else {
      input.value = item.value;
    }
    if (typeof item.href !== "undefined") {
      window.location.assign(item.href);
    }
    _hide();
  }

  function _renderItem(item, input, index = null) {
    let el = document.createElement("div");
    el.setAttribute("role", "option");
    el.setAttribute("aria-selected", false);
    el.classList.add("ac-item");
    el.setAttribute(
      "id",
      input.getAttribute("id") + "__" + (index === null ? randomID() : index),
    );

    if (typeof item === "string" || typeof item === "number") {
      el.innerHTML = item;
    } else if (typeof item._header !== "undefined") {
      el.innerHTML = item._header;
      el.classList.add("ac-header");
      return el;
    } else {
      el.innerHTML = item.text;
      if (typeof item.sub !== "undefined") {
        let sub = document.createElement("small");
        sub.classList.add("ac-sub");
        sub.innerHTML = item.sub;
        el.appendChild(sub);
      }
      if (typeof item._disabled !== "undefined" && item._disabled) {
        el.setAttribute("disabled", true);
        if (typeof item._disabled !== "boolean") {
          el.innerHTML = item._disabled;
        }
      }
    }
    el.addEventListener(
      "mousedown",
      (e) => {
        if (e.which === 1) {
          e.preventDefault();
          _selectItem(item, input);
        } else {
          return true;
        }
      },
      false
    );
    return el;
  }

  function _searchCallback(items, input) {
    // Render
    if (items.length > settings.limit) {
      items = items.slice(0, settings.limit);
    }
    const listEls = items.map((item, index) => _renderItem(item, input, index));
    list.innerHTML = "";
    listEls.map((el) => list.appendChild(el));

    // Setup keyboard information
    _currentItems = items.slice().filter((item) => {
      return (
        typeof item._header === "undefined" &&
        typeof item._disabled === "undefined"
      );
    });
    _currentListEls = listEls.filter(
      (el) =>
        !el.classList.contains("ac-header") &&
        !el.classList.contains("ac-disabled")
    );
    _currentIndex = -1;
  }

  function _search(handler, input) {
    if (input.value.length < settings.minInputLength) {
      _hide();
      return;
    }

    let loadingEl = _renderItem({ _header: settings.loadingString }, input);
    list.innerHTML = loadingEl.outerHTML;

    let thisCB = new Date().getTime();
    lastCB = thisCB;

    handler(input.value, function callback(items) {
      const outdatedHandler = thisCB !== lastCB;
      if (outdatedHandler) {
        // We should just ignore outdated handler callbacks; newer code will do
        // the right thing, and taking action based on an old request will only
        // cause problems.
        return;
      }
      if (!items || items.length === 0) {
        _hide();
        return;
      }
      _searchCallback(items, input);
      _show(input);
      _align(input);
      // Set aria-expanded here so that the load indicator isn't marked expanded
      input.setAttribute('aria-expanded', 'true');
    });
  }

  function _keydown(handler, input, event) {
    // - Ignore control functions
    if (event.ctrlKey || event.which === 17) {
      return;
    }
    switch (event.which) {
      // arrow keys through items
      case 38: // UP key
        event.preventDefault();
        if (_currentIndex > -1) {
          _currentListEls[_currentIndex].classList.remove("is-selected");
          _currentListEls[_currentIndex].setAttribute("aria-selected", false);
        }
        _currentIndex -= 1;
        if (_currentIndex <= -2) {
          _currentIndex = _currentItems.length - 1;
        }
        break;
      case 40: // DOWN key
        event.preventDefault();
        if (lastInput === false) {
          _search(handler, input);
          return;
        }
        if (_currentIndex > -1) {
          _currentListEls[_currentIndex].classList.remove("is-selected");
          _currentListEls[_currentIndex].setAttribute("aria-selected", false);
        }
        _currentIndex += 1;
        if (_currentIndex >= _currentItems.length) {
          _currentIndex = -1;
        }
        break;
      // ENTER to nav or populate
      case 13:
        if (_currentIndex > -1) {
          event.preventDefault();
          _selectItem(_currentItems[_currentIndex], input);
        }
        break;
      // hide on ESCAPE
      case 27:
        _hide();
        break;
    }

    if (_currentIndex > -1) {
      input.setAttribute(
        "aria-activedescendant",
        _currentListEls[_currentIndex].getAttribute("id"),
      );

      _currentListEls[_currentIndex].classList.add("is-selected");
      _currentListEls[_currentIndex].setAttribute("aria-selected", true);
    } else {
      input.removeAttribute("aria-activedescendant");
    }
  }

  return function Factory(input, handler) {
    if (!input) {
      return false;
    }

    if (typeof handler === "undefined") {
      throw new Error(
        "Autocomplete needs handler to return items based on a query: function(query, callback) {}"
      );
    }

    // Create list element
    if (typeof list === "undefined") {
      list = document.querySelector(".autocomplete-results");
      if (!list) {
        list = document.createElement("div");
        list.setAttribute("id", "ac-" + randomID());
        list.classList.add("autocomplete-results");
        document.body.appendChild(list);
        window.addEventListener(
          "resize",
          () => _align(lastInput),
          false
        );
        window.addEventListener(
          "scroll",
          () => _align(lastInput),
          false
        );
      }
    }

    // Aria
    list.setAttribute("role", "listbox");
    input.setAttribute("role", "combobox");
    input.setAttribute("aria-autocomplete", "both");
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute("aria-controls", list.getAttribute("id"));
    input.setAttribute("enterkeyhint", "search"); // phone keyboard hint
    input.setAttribute("autocapitalize", "off");  // disable browser tinkering
    input.setAttribute("autocomplete", "off");    // ^
    input.setAttribute("spellcheck", "false");    // ^
    if (typeof input.autocorrect !== 'undefined') {
      input.setAttribute("autocorrect", "off");     // ^ only with Safari
    }

    // Activation / De-activation
    if (input.getAttribute("autofocus") !== null) {
      // ignore the first autofocus
      input.addEventListener("focus", () => {
        input.addEventListener("focus", () => _search(handler, input));
      }, { once: true });
    } else {
      input.addEventListener("focus", () => _search(handler, input));
    }
    input.addEventListener("blur", _hide, false);

    // Input typing
    const debounceSearch = _debounce(_search, settings.delay);
    input.addEventListener(
      "input",
      (event) => {
        _show(input);
        _align(input);

        if (
          event.inputType === "insertFromPaste" ||
          event.inputType === "insertFromDrop"
        ) {
          _search(handler, input);
        } else {
          debounceTimeout = debounceSearch(handler, input);
        }
      },
      false
    );

    // Checking control characters
    input.addEventListener(
      "keydown",
      (event) => _keydown(handler, input, event),
      false
    );

    input.ac = {
      show: _show,
      hide: _hide,
    }

    return input;
  };
}

if (typeof module !== "undefined") {
  module.exports = Autocomplete;
}
