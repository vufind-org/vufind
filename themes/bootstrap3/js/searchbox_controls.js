/*global VuFind */

VuFind.register("searchbox_controls", function SearchboxControls() {
  function _setupSearchResetButton() {
    const queryInput = document.getElementById("searchForm_lookfor");
    const resetButton = document.getElementById("searchForm-reset");
    if (queryInput === null || resetButton === null) {
      // missing controls; nothing to do here (may happen on advanced search page, for example):
      return;
    }

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
      queryInput.setAttribute("value", "");
      // Send an input event to be sure the autocomplete updates correctly
      // (this may become unnecessary after future updates to the autocomplete library)
      queryInput.dispatchEvent(new Event('input'));
      queryInput.focus();
      resetButton.classList.add("hidden");
    });
  }

  function init() {
    _setupSearchResetButton();
  }

  return {
    init: init
  };
});
