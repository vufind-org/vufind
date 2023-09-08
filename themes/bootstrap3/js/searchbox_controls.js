/*global VuFind */

VuFind.register("searchbox_controls", function SearchboxControls() {
  function _setupSearchResetButton() {
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
      // We need to blur before we focus to be sure the input change registers
      // with the autocomplete control.
      queryInput.blur().focus();
    });
  }

  function init() {
    _setupSearchResetButton();
  }

  return {
    init: init
  };
});
