/* global VuFind */
VuFind.register('contentForm', function contentForm() {
  /**
   * Set visibility of elements based on inputs in fieldset.
   * @param {HTMLElement} fieldset Fieldset
   */
  function _setVisibility(fieldset) {
    if (fieldset.name) {
      fieldset.querySelectorAll('input').forEach(
        (input) => document.querySelectorAll('.content-form [class*="' + fieldset.name + '-' + input.value + '"]').forEach(
          (element) => {
            if (input.checked) {
              element.classList.remove('hidden');
            } else {
              element.classList.add('hidden');
            }
          })
      )
    }
  }

  /**
   * Initialize the content form.
   */
  function init() {
    // Allows toggling the visibility of elements based on selected input.
    // Elements with class "<fieldset name>-<input value>" are shown.
    document.querySelectorAll('.content-form fieldset').forEach((fieldset) => {
      fieldset.querySelectorAll('input').forEach(
        (input) => {
          _setVisibility(fieldset);
          input.addEventListener('change', () => {
            _setVisibility(fieldset);
          })
        })
    });
  }

  return {
    init: init
  };
});
