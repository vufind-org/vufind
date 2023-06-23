/*global VuFind */
VuFind.register('keyboard', function Keyboard() {
  let _KeyboardClass;
  let _KeyboardLayoutClass;

  let _textInput;

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
    _textInput.value = input;
    _keyboard.setInput(input);
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

  function _updateKeyboardLayout(layoutName){
    $('.keyboard-selection-item').each(function deactivateItems() {
      $(this).parent().removeClass("active");
    });
    $(".keyboard-selection-item[data-value='" + layoutName + "']").parent().addClass("active");
    window.Cookies.set("keyboard", layoutName);
    if (layoutName === "none") {
      _enabled = false;
      _hideKeyboard();
    } else {
      _enabled = true;
      const keyboardLayout = new _KeyboardLayoutClass().get(layoutName);
      _keyboard.setOptions({layout: keyboardLayout.layout});
    }
  }

  function _initKeyboard(){
    _keyboard = new _KeyboardClass(
      {
        onChange: input => _onChange(input),
        onKeyPress: button => _onKeyPress(button),
        display: _display,
        syncInstanceInputs: true,
        mergeDisplay: true,
        physicalKeyboardHighlight: true
      });

    _keyboard.setInput(_textInput.value);

    let layout = window.Cookies.get("keyboard");
    if (layout == null) {
      layout = "none";
    }
    _updateKeyboardLayout(layout);
  }

  function init(){
    _KeyboardClass = window.SimpleKeyboard.default;
    _KeyboardLayoutClass = window.SimpleKeyboardLayouts.default;
    _textInput = document.querySelector(".searchForm_lookfor");

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
        !event.target.className.includes("hg-button") &&
        !event.target.className.includes("hg-row") &&
        !event.target.className.includes("simple-keyboard")
      ) {
        _hideKeyboard();
      }
    });
    _textInput.addEventListener("input", (event) => {
      _keyboard.setInput(event.target.value);
    });

    _initKeyboard();
  }

  return {
    init: init
  };
});
