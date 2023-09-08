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
      if (
        _keyboard.options.theme.includes("show-keyboard") &&
        !event.target.className.includes("searchForm_lookfor") &&
        !event.target.className.includes('keyboard-selection') &&
        !event.target.parentNode.className.includes('keyboard-selection') &&
        !event.target.parentNode.parentNode.className.includes('keyboard-selection') &&
        !event.target.className.includes("hg-button") &&
        !event.target.className.includes("hg-row") &&
        !event.target.className.includes("simple-keyboard") &&
        !event.target.className.includes("searchForm-reset") &&
        !event.target.parentNode.className.includes("searchForm-reset")
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

  function _handleInputChange(input, triggerInputEvent = true) {
    _textInput.value = input;
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
  }

  function init(){
    _textInput = document.getElementById('searchForm_lookfor');
    _resetButton = document.getElementById('searchForm-reset');

    _textInput.addEventListener("input", (event) => {
      _handleInputChange(event.target.value, false);
    });

    _resetButton.addEventListener('click', () => {
      _handleInputChange('');
    });

    if (typeof window.SimpleKeyboard !== 'undefined') {
      _initKeyboard();
    }

    _handleInputChange(_textInput.value);
  }

  return {
    init: init
  };
});
