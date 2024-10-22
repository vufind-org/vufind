/*global Autocomplete, VuFind, extractClassParams */

VuFind.register('searchbox_controls', function SearchboxControls() {
  let _KeyboardClass;
  let _KeyboardLayoutClass;

  let _textInput;
  let _resetButton;

  let _enabled = false;
  let _keyboard;
  const _defaultTheme = "hg-theme-default";
  const _display = {
    "{bksp}": "&#10229;",
    "{enter}": "&#8626;",
    "{shift}": "&#8679;",
    "{tab}": "&#8633;",
    "{lock}": "&#8681;",
  };

  function _showKeyboard() {
    if (_enabled) {
      _keyboard.setOptions({
        theme: `${_defaultTheme} show-keyboard`
      });
    }
  }

  function _hideKeyboard() {
    _keyboard.setOptions({
      theme: _defaultTheme
    });
  }

  function _onChange(input) {
    _textInput.value = input;
    _textInput.dispatchEvent(new Event("input"));
  }

  function _onKeyPress(button) {
    if (button === "{shift}" || button === "{lock}") {
      let currentLayoutType = _keyboard.options.layoutName;
      _keyboard.setOptions({
        layoutName: currentLayoutType === "default" ? "shift" : "default"
      });
    }

    if (button === "{enter}") {
      document.getElementById("searchForm").submit();
    }

    requestAnimationFrame(() => {
      let caretPos = _keyboard.getCaretPosition();
      if (caretPos) {
        _textInput.setSelectionRange(caretPos, caretPos);
      }
    });
  }

  function _updateKeyboardLayout(layoutName) {
    if (VuFind.getBootstrapMajorVersion() === 3) {
      $('.keyboard-selection-item').each(function deactivateItems() {
        $(this).parent().removeClass("active");
      });
      $(".keyboard-selection-item[data-value='" + layoutName + "']").parent().addClass("active");
    } else {
      $('.keyboard-selection-item').each(function deactivateItems() {
        $(this).removeClass("active");
        $(this).addClass("dropdown-item");
      });
      $(".keyboard-selection-item[data-value='" + layoutName + "']").addClass("active");
    }
    window.Cookies.set("keyboard", layoutName);
    if (layoutName === "none") {
      $("#keyboard-selection-button").removeClass("activated");
      _enabled = false;
      _hideKeyboard();
    } else {
      $("#keyboard-selection-button").addClass("activated");
      _enabled = true;
      const keyboardLayout = new _KeyboardLayoutClass().get(layoutName);
      _keyboard.setOptions({layout: keyboardLayout.layout});
      _showKeyboard();
    }
  }

  function setupKeyboard() {
    if (!_textInput) {
      return;
    }

    _KeyboardClass = window.SimpleKeyboard.default;
    _KeyboardLayoutClass = window.SimpleKeyboardLayouts.default;

    $('.keyboard-selection-item').on("click", function updateLayoutOnClick(ev) {
      _updateKeyboardLayout($(this).data("value"));
      ev.preventDefault();
    });

    _textInput.addEventListener("focus", () => {
      _showKeyboard();
    });
    _textInput.addEventListener("click", () => {
      _showKeyboard();
    });
    _textInput.addEventListener("input", (event) => {
      _keyboard.setInput(event.target.value);
    });
    _textInput.addEventListener("keydown", (event) => {
      if (event.shiftKey) {
        _keyboard.setOptions({
          layoutName: "shift"
        });
      }
    });
    _textInput.addEventListener("keyup", (event) => {
      if (!event.shiftKey) {
        _keyboard.setOptions({
          layoutName: "default"
        });
      }
    });

    document.addEventListener("click", (event) => {
      if (!_keyboard.options.theme.includes('show-keyboard')) {
        return;
      }
      function hasClass(el, className) {
        return el.className !== undefined && el.className.includes(className);
      }
      function hasId(el, id) {
        return el.id === id;
      }
      if (
        event.target.parentNode == null ||
        event.target.parentNode.parentNode == null || (
          !hasClass(event.target, 'searchForm_lookfor')
          && !hasClass(event.target, 'keyboard-selection')
          && !hasClass(event.target, 'hg-button')
          && !hasClass(event.target, 'hg-row')
          && !hasClass(event.target, 'simple-keyboard')
          && !hasClass(event.target, 'searchForm-reset')
          && !hasClass(event.target.parentNode, 'keyboard-selection')
          && !hasClass(event.target.parentNode, 'searchForm-reset')
          && !hasClass(event.target.parentNode.parentNode, 'keyboard-selection')
        )
      ) {
        _hideKeyboard();
      } else if (
        event.target.parentNode == null || (
          !hasId(event.target, 'keyboard-selection-button')
          && !hasId(event.target.parentNode, 'keyboard-selection-button')
        )
      ) {
        _textInput.focus();
      }
    });

    _keyboard = new _KeyboardClass({
      onChange: input => _onChange(input),
      onKeyPress: button => _onKeyPress(button),
      display: _display,
      syncInstanceInputs: true,
      mergeDisplay: true,
      physicalKeyboardHighlight: true,
      preventMouseDownDefault: true,
    });

    _keyboard.setInput(_textInput.value);

    let layout = window.Cookies.get("keyboard");
    if (layout == null) {
      layout = "none";
    }
    _updateKeyboardLayout(layout);
    _hideKeyboard();
  }

  function setupAutocomplete() {
    // If .autocomplete class is missing, autocomplete is disabled and we should bail out.
    var $searchboxes = $('input.autocomplete');
    $searchboxes.each(function processAutocompleteForSearchbox(i, searchboxElement) {
      const $searchbox = $(searchboxElement);
      const formattingRules = $searchbox.data('autocompleteFormattingRules');
      const typeFieldSelector = $searchbox.data('autocompleteTypeFieldSelector');
      const typePrefix = $searchbox.data('autocompleteTypePrefix');
      const getFormattingRule = function getAutocompleteFormattingRule(type) {
        if (typeof(formattingRules) !== "undefined") {
          if (typeof(formattingRules[type]) !== "undefined") {
            return formattingRules[type];
          }
          // If we're using combined handlers, we may need to use a backend-specific wildcard:
          const typeParts = type.split("|");
          if (typeParts.length > 1) {
            const backendWildcard = typeParts[0] + "|*";
            if (typeof(formattingRules[backendWildcard]) !== "undefined") {
              return formattingRules[backendWildcard];
            }
          }
          // Special case: alphabrowse options in combined handlers:
          const alphabrowseRegex = /^External:.*\/Alphabrowse.*\?source=([^&]*)/;
          const alphabrowseMatches = alphabrowseRegex.exec(type);
          if (alphabrowseMatches && alphabrowseMatches.length > 1) {
            const alphabrowseKey = "VuFind:Solr|alphabrowse_" + alphabrowseMatches[1];
            if (typeof(formattingRules[alphabrowseKey]) !== "undefined") {
              return formattingRules[alphabrowseKey];
            }
          }
          // Global wildcard fallback:
          if (typeof(formattingRules["*"]) !== "undefined") {
            return formattingRules["*"];
          }
        }
        return "none";
      };
      const typeahead = new Autocomplete({
        rtl: $(document.body).hasClass("rtl"),
        maxResults: 10,
        loadingString: VuFind.translate('loading_ellipsis'),
      });

      let cache = {};
      const input = $searchbox[0];
      typeahead(input, function vufindACHandler(query, callback) {
        const classParams = extractClassParams(input);
        const searcher = classParams.searcher;
        const selectedType = classParams.type
          ? classParams.type
          : $(typeFieldSelector ? typeFieldSelector : '#searchForm_type').val();
        const type = (typePrefix ? typePrefix : "") + selectedType;
        const formattingRule = getFormattingRule(type);

        const cacheKey = searcher + "|" + type;
        if (typeof cache[cacheKey] === "undefined") {
          cache[cacheKey] = {};
        }

        if (typeof cache[cacheKey][query] !== "undefined") {
          callback(cache[cacheKey][query]);
          return;
        }

        var hiddenFilters = [];
        $('#searchForm').find('input[name="hiddenFilters[]"]').each(function hiddenFiltersEach() {
          hiddenFilters.push($(this).val());
        });

        $.ajax({
          url: VuFind.path + '/AJAX/JSON',
          data: {
            q: query,
            method: 'getACSuggestions',
            searcher: searcher,
            type: type,
            hiddenFilters,
          },
          dataType: 'json',
          success: function autocompleteJSON(json) {
            const highlighted = json.data.suggestions.map(
              (item) => ({
                text: item.replaceAll("&", "&amp;")
                  .replaceAll("<", "&lt;")
                  .replaceAll(">", "&gt;")
                  .replaceAll(query, `<b>${query}</b>`),
                value: formattingRule === 'phrase'
                  ? '"' + item.replaceAll('"', '\\"') + '"'
                  : item,
              })
            );
            cache[cacheKey][query] = highlighted;
            callback(highlighted);
          }
        });
      });

      // Bind autocomplete auto submit
      if ($searchbox.hasClass("ac-auto-submit")) {
        input.addEventListener("ac-select", (event) => {
          const value = typeof event.detail === "string"
            ? event.detail
            : event.detail.value;
          input.value = value;
          input.form.submit();
        });
      }
    });
  }

  function setupSearchResetButton() {
    _resetButton = document.getElementById("searchForm-reset");

    if (!_resetButton || !_textInput) {
      return;
    }

    if (_textInput.value !== "") {
      _resetButton.classList.remove("hidden");
    }

    _textInput.addEventListener("input", function resetOnInput() {
      _resetButton.classList.toggle("hidden", _textInput.value === "");
    });

    _resetButton.addEventListener("click", function resetOnClick() {
      requestAnimationFrame(() => {
        _textInput.value = "";
        _textInput.dispatchEvent(new Event("input"));
        _textInput.focus();
      });
    });
  }

  function init() {
    _textInput = document.getElementById("searchForm_lookfor");

    setupAutocomplete();
    setupSearchResetButton();

    // Setup keyboard
    if (typeof window.SimpleKeyboard !== 'undefined') {
      setupKeyboard();
    }
  }

  return {
    init: init
  };
});
