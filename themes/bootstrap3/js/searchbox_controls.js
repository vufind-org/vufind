/*global Autocomplete, VuFind */

function extractClassParams(el) {
  const str = el.className;

  if (typeof str === "undefined") {
    return [];
  }

  let params = {};
  const classes = str.split(/\s+/);
  for (let i = 0; i < classes.length; i++) {
    if (classes[i].indexOf(':') > 0) {
      const pair = classes[i].split(':');
      params[pair[0]] = pair[1];
    }
  }

  return params;
}

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

  function _initKeyboard(){
    _KeyboardClass = window.SimpleKeyboard.default;
    _KeyboardLayoutClass = window.SimpleKeyboardLayouts.default;

    $('.keyboard-selection-item').on("click", function updateLayoutOnClick(){
      _updateKeyboardLayout($(this).data("value"));
    });

    _textInput.addEventListener("focus", () => {
      _showKeyboard();
    });
    _textInput.addEventListener("click", () => {
      _showKeyboard();
    });
    document.addEventListener("click", (event) => {
      function hasClass(el, className) {
        return el.className !== undefined && el.className.includes(className);
      }
      if (
        event.target.parentNode == null ||
        event.target.parentNode.parentNode == null || (
          _keyboard.options.theme.includes('show-keyboard')
          && !hasClass(event.target, 'searchForm_lookfor')
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
      }
    });

    _keyboard = new _KeyboardClass(
      {
        onChange: input => _onChange(input),
        onKeyPress: button => _onKeyPress(button),
        display: _display,
        syncInstanceInputs: true,
        mergeDisplay: true,
        physicalKeyboardHighlight: true
      });

    let layout = window.Cookies.get("keyboard");
    if (layout == null) {
      layout = "none";
    }
    _updateKeyboardLayout(layout);
    _hideKeyboard();
  }

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

  function _updateKeyboardLayout(layoutName) {
    $('.keyboard-selection-item').each(function deactivateItems() {
      $(this).parent().removeClass("active");
    });
    $(".keyboard-selection-item[data-value='" + layoutName + "']").parent().addClass("active");
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

  function _handleInputChange(input, triggerInputEvent = true) {
    _textInput.value = input;
    _textInput.setAttribute('value', input);
    if (_textInput.value === '') {
      _resetButton.classList.add('hidden');
    } else {
      _resetButton.classList.remove('hidden');
    }
    if ( typeof _keyboard !== 'undefined') {
      _keyboard.setInput(input);
    }
    if (triggerInputEvent) {
      _textInput.dispatchEvent(new Event('input'));
    }
    _textInput.focus();
  }

  function _onChange(input){
    _handleInputChange(input);
  }

  function _onKeyPress(button){
    if (button === "{shift}" || button === "{lock}") {
      let currentLayoutType = _keyboard.options.layoutName;
      _keyboard.setOptions({
        layoutName: currentLayoutType === "default" ? "shift" : "default"
      });
    }

    if (button === "{enter}") {
      document.getElementById("searchForm").submit();
    }
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
    const queryInput = document.getElementById("searchForm_lookfor");
    const resetButton = document.getElementById("searchForm-reset");

    if (queryInput.value !== "") {
      resetButton.classList.remove("hidden");
    }

    queryInput.addEventListener("input", function resetOnInput() {
      if (queryInput.value === "") {
        resetButton.classList.add("hidden");
      } else {
        resetButton.classList.remove("hidden");
      }
    });

    resetButton.addEventListener("click", function resetOnClick() {
      resetButton.classList.add("hidden");
      queryInput.setAttribute("value", "");
      queryInput.focus();
      queryInput.ac.hide();
    });
  }

  function init() {
    setupAutocomplete();
    setupSearchResetButton();

    // Setup reset button

    _textInput = document.getElementById('searchForm_lookfor');
    _resetButton = document.getElementById('searchForm-reset');

    _textInput.addEventListener("input", function resetOnInput(event) {
      _handleInputChange(event.target.value, false);
    });

    _resetButton.addEventListener('click', function resetOnClick() {
      _handleInputChange('');
    });

    // Setup keyboard

    if (typeof window.SimpleKeyboard !== 'undefined') {
      _initKeyboard();
    }

    _handleInputChange(_textInput.value);
  }

  return {
    init: init
  };
});
