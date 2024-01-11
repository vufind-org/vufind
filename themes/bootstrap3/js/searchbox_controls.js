/*global VuFind */
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

  function _handleInputChange(input, triggerInputEvent = true) {
    _textInput.value = input;
    _textInput.setAttribute('value', input);
    if (_resetButton) {
      _resetButton.classList.toggle('hidden', _textInput.value === '');
    }
    if ( typeof _keyboard !== 'undefined') {
      _keyboard.setInput(input);
    }
    if (triggerInputEvent) {
      _textInput.dispatchEvent(new Event('input'));
    }
    if ( typeof _keyboard !== 'undefined' && triggerInputEvent) {
      let caretPos = _keyboard.getCaretPosition();
      if (caretPos) {
        _textInput.setSelectionRange(caretPos, caretPos);
      }
    }
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

  function _initKeyboard(){
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
      } else if (event.target.parentNode == null || (
        !hasId(event.target, 'keyboard-selection-button')
        && !hasId(event.target.parentNode, 'keyboard-selection-button')
      )
      ) {
        _textInput.focus();
      }
    });

    _keyboard = new _KeyboardClass(
      {
        onChange: input => _onChange(input),
        onKeyPress: button => _onKeyPress(button),
        display: _display,
        syncInstanceInputs: true,
        mergeDisplay: true,
        physicalKeyboardHighlight: true,
        preventMouseDownDefault: true
      });

    let layout = window.Cookies.get("keyboard");
    if (layout == null) {
      layout = "none";
    }
    _updateKeyboardLayout(layout);
    _hideKeyboard();
  }

  function init(){
    _textInput = document.getElementById('searchForm_lookfor');

    if (!_textInput) {
      return;
    }

    _resetButton = document.getElementById('searchForm-reset');

    _textInput.addEventListener("input", function resetOnInput(event) {
      _handleInputChange(event.target.value, false);
    });

    if (_resetButton) {
      _resetButton.addEventListener('click', function resetOnClick() {
        _handleInputChange('');
        _textInput.focus();
      });
    }

    if (typeof window.SimpleKeyboard !== 'undefined') {
      _initKeyboard();
    }

    _handleInputChange(_textInput.value);
  }

  return {
    init: init
  };
});
