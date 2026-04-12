/*global VuFind */

VuFind.register("listItemSelection", function ListItemSelection() {

  /**
   * Store data in the session storage for a specific form
   * @param {HTMLElement} form The form element
   * @param {string}      key  The key to store the data under
   * @param {*}           data The data to be stored.
   */
  function _sessionSet(form, key, data) {
    let formId = form.id;
    let formStorage = JSON.parse(window.sessionStorage.getItem(formId) || "{}");
    formStorage[key] = data;
    window.sessionStorage.setItem(formId, JSON.stringify(formStorage));
  }

  /**
   * Retrieve data from session storage for a specific form.
   * @param {HTMLElement} form The form element.
   * @param {string}      key  The key of the data to retrieve.
   * @returns {*} The retrieved data (or undefined if not found).
   */
  function _sessionGet(form, key) {
    let formId = form.id;
    let formStorage = JSON.parse(window.sessionStorage.getItem(formId) || "{}");
    return formStorage[key];
  }

  /**
   * Write the current multi-page selection state to session storage.
   * @param {HTMLElement} form The form element.
   */
  function _writeState(form) {
    if (form.classList.contains('multi-page-selection')) {
      let nonDefaultIdsInput = form.querySelector('.non_default_ids');
      let checkedDefaultInput = form.querySelector('.checked_default');
      if (nonDefaultIdsInput !== null && checkedDefaultInput !== null) {
        _sessionSet(form, 'checkedDefault', checkedDefaultInput.checked);
        _sessionSet(form, 'nonDefaultIds', JSON.parse(nonDefaultIdsInput.value));
      }
    }
  }

  /**
   * Find all elements matching the provided class name inside the provided form.
   * @param {HTMLElement} form      The form element.
   * @param {string}      className The class name to select from the form.
   * @returns {Array<HTMLElement>} Matching elements.
   */
  function queryClassInForm(form, className) {
    // If the form has an ID, we can select for contents and external elements with a form attribute.
    // If the form has no ID, we can only select for contents using its name.
    const selector = form.id.length === 0
      ? `form[name="${form.name}"] .${className}`
      : `#${form.id} .${className}, .${className}[form="${form.id}"]`;
    return document.querySelectorAll(selector);
  }

  /**
   * Get all item checkboxes associated with a form.
   * @param {HTMLElement} form The form element.
   * @returns {NodeList} A list of item checkboxes.
   */
  function getItemCheckboxes(form) {
    return queryClassInForm(form, 'checkbox-select-item');
  }

  /**
   * Check or uncheck a checkbox or a list of checkboxes.
   * @param {NodeList|HTMLElement} checkbox The checkbox or checkboxes to update.
   * @param {boolean}              checked  The desired checked state.
   */
  function _check(checkbox, checked) {
    if (checkbox instanceof NodeList) {
      checkbox.forEach((cb) => _check(cb, checked));
    } else if (checkbox !== null) {
      checkbox.checked = checked;
    }
  }

  /**
   * Update the text and visibility of the clear selection button.
   * @param {HTMLElement} button The clear selection button element.
   * @param {number}      count  The number of selected items.
   */
  function _updateSelectionCount(button, count) {
    if (count < 1) {
      button.classList.add('hidden');
    } else {
      button.textContent = VuFind.translate('clear_selection', { '%%count%%': count});
      button.classList.remove('hidden');
    }
  }

  /**
   * Get all selected item IDs, handling both simple and multi-page selection modes.
   * @param {HTMLElement} form The form element.
   * @returns {Array<string>} An array of selected item IDs.
   */
  function getAllSelected(form) {
    let selected = [];
    let nonDefaultIdsInput = form.querySelector('.non_default_ids');
    let checkedDefaultInput = form.querySelector('.checked_default');
    let allIdsInput = form.querySelector('.all-ids-global');
    if (nonDefaultIdsInput !== null && checkedDefaultInput !== null && allIdsInput !== null) {
      let nonDefaultIds = JSON.parse(nonDefaultIdsInput.value);
      if (checkedDefaultInput.checked) {
        let allIds = JSON.parse(allIdsInput.value);
        selected = allIds.filter((id) => !nonDefaultIds.includes(id));
      } else {
        selected = nonDefaultIds;
      }
    } else {
      let addToSelected = (input)=> {
        if (-1 === selected.indexOf(input.value)) {
          selected.push(input.value);
        }
      };
      form.querySelectorAll('input[name="ids[]"]:checked').forEach(addToSelected);
      if (form.id.length > 0) {
        document.querySelectorAll('input[form="' + form.id + '"][name="ids[]"]:checked').forEach(addToSelected);
      }
    }
    return selected;
  }

  /**
   * Check if all items on the current page are selected.
   * @param {HTMLElement} form The form element.
   * @returns {boolean} Return true if all items are selected
   */
  function _allOnPageAreSelected(form) {
    return form.querySelectorAll('.checkbox-select-item:not(:checked)').length === 0
      && (form.id.length === 0
      || document.querySelectorAll('.checkbox-select-item[form="' + form.id + '"]:not(:checked)').length === 0);
  }

  /**
   * Check if all items across all pages are selected.
   * @param {HTMLElement} form The form element.
   * @returns {boolean} Return true if all items are selected across all pages.
   */
  function _allGlobalAreSelected(form) {
    let allIdsInput = form.querySelector('.all-ids-global');
    if (allIdsInput == null) return false;
    let allIds = JSON.parse(allIdsInput.value);
    let selectedIds = getAllSelected(form);
    return selectedIds.length === allIds.length;
  }

  /**
   * Updates the form inputs based on the input data. "data" can contain the values for "non_default_ids",
   * "checked_default" and if all single item checkboxes should be checked.
   * @param {HTMLElement} form   The form element.
   * @param {object}      [data] An object containing state data to apply (default = {}).
   */
  function _writeToForm(form, data = {}) {
    if (data.nonDefaultIds !== undefined) {
      form.querySelector('.non_default_ids').value = JSON.stringify(data.nonDefaultIds);
    }
    if (data.checkedDefault !== undefined) {
      _check(form.querySelector('.checked_default'), data.checkedDefault);
    }
    if (data.selectAllOnPage !== undefined) {
      _check(getItemCheckboxes(form), data.selectAllOnPage);
    }
  }

  /**
   * Updates the state of the hidden input "checked_default" and "non_default_ids" and the checkboxes
   * "checkbox-select-all" and "checkbox-select-all-global" to match the current selection.
   * @param {HTMLElement} form The form element.
   */
  function _updateSelectionState(form) {
    let nonDefaultIdsInput = form.querySelector('.non_default_ids');
    let checkedDefaultInput = form.querySelector('.checked_default');

    if (nonDefaultIdsInput !== null && checkedDefaultInput !== null) {
      let nonDefaultIds = JSON.parse(nonDefaultIdsInput.value);
      let checkedDefault = checkedDefaultInput.checked;
      form.querySelectorAll('.checkbox-select-item').forEach(itemCheckbox => {
        let id = itemCheckbox.value;
        if (checkedDefault ^ itemCheckbox.checked) {
          if (!nonDefaultIds.includes(id)) {
            nonDefaultIds.push(id);
          }
        } else if (nonDefaultIds.includes(id)) {
          delete nonDefaultIds[nonDefaultIds.indexOf(id)];
          nonDefaultIds = nonDefaultIds.filter(n => n);
        }
      });
      _writeToForm(form, {
        'nonDefaultIds': nonDefaultIds,
        'checkedDefault': checkedDefault,
      });
    }
    queryClassInForm(form, 'checkbox-select-all')
      .forEach((checkbox) => _check(checkbox, _allOnPageAreSelected(form)));
    queryClassInForm(form, 'checkbox-select-all-global')
      .forEach((checkbox) => _check(checkbox, _allGlobalAreSelected(form)));
    queryClassInForm(form, 'clear-selection')
      .forEach((button) => _updateSelectionCount(button, getAllSelected(form).length));
  }

  /**
   * Handle the 'change' event for the 'select all on page' checkbox.
   * @param {HTMLElement} checkbox The 'select all on page' checkbox.
   */
  function _selectAllCheckbox(checkbox) {
    let form = checkbox.form ? checkbox.form : checkbox.closest('form');
    if (form == null) {
      return;
    }
    if (checkbox.checked || _allOnPageAreSelected(form)) {
      _writeToForm(form, {
        'selectAllOnPage': checkbox.checked
      });
      _updateSelectionState(form);
    }
    _writeState(form);
  }

  /**
   * Handle the 'change' event for the 'select all global' checkbox.
   * @param {HTMLElement} checkbox The 'select all global' checkbox.
   */
  function _selectAllGlobalCheckbox(checkbox) {
    let form = checkbox.form ? checkbox.form : checkbox.closest('form');
    if (form == null) {
      return;
    }
    if (checkbox.checked || _allGlobalAreSelected(form)) {
      _writeToForm(form, {
        'nonDefaultIds': [],
        'checkedDefault': checkbox.checked,
        'selectAllOnPage': checkbox.checked
      });
      _updateSelectionState(form);
    }
    _writeState(form);
  }

  /**
   * Clear all selected items, both on the current page and globally.
   * @param {HTMLElement} button The 'clear selection' button.
   */
  function _clearAllSelected(button) {
    let form = button.form ? button.form : button.closest('form');
    if (form == null) {
      return;
    }
    _writeToForm(form, {
      'nonDefaultIds': [],
      'checkedDefault': false,
      'selectAllOnPage': false
    });
    _updateSelectionState(form);
    _writeState(form);
  }

  /**
   * Set up event listeners for all selection controls on the page.
   */
  function _setupControls() {
    document.querySelectorAll('.checkbox-select-all').forEach((checkbox) => {
      checkbox.addEventListener('change', () => _selectAllCheckbox(checkbox));
    });
    document.querySelectorAll('.checkbox-select-all-global').forEach((checkbox) => {
      checkbox.addEventListener('change', () => _selectAllGlobalCheckbox(checkbox));
    });
    document.querySelectorAll('.clear-selection').forEach((button) => {
      button.addEventListener('click', () => _clearAllSelected(button));
    });
    document.querySelectorAll('.checkbox-select-item').forEach((checkbox) => {
      checkbox.addEventListener('change', () => {
        let form = checkbox.form ? checkbox.form : checkbox.closest('form');
        if (form == null) {
          return;
        }
        _updateSelectionState(form);
        _writeState(form);
      });
    });
  }

  /**
   * Initialize a form for multi-page selection, loading state from session storage.
   * @param {HTMLElement} form The form to initialize.
   */
  function _setupMultiPageSelectionForm(form) {
    let nonDefaultIdsInput = document.createElement('input');
    nonDefaultIdsInput.setAttribute('class', 'non_default_ids hidden');
    nonDefaultIdsInput.setAttribute('type', 'text');
    nonDefaultIdsInput.setAttribute('name', 'non_default_ids');
    nonDefaultIdsInput.setAttribute('value', '');
    form.appendChild(nonDefaultIdsInput);

    let checkedDefaultInput = document.createElement('input');
    checkedDefaultInput.setAttribute('class', 'checked_default hidden');
    checkedDefaultInput.setAttribute('type', 'checkbox');
    checkedDefaultInput.setAttribute('name', 'checked_default');
    form.appendChild(checkedDefaultInput);

    let nonDefaultIds = _sessionGet(form, 'nonDefaultIds') || [];
    let checkedDefault = _sessionGet(form, 'checkedDefault') || false;
    // Check if the form contains all the ids in the nonDefaultIds
    const allIds = JSON.parse(form.querySelector('.all-ids-global').value || '[]');
    if (allIds) {
      nonDefaultIds = nonDefaultIds.filter(item => allIds.includes(item));
    }
    form.querySelectorAll('.checkbox-select-item').forEach(itemCheckbox => {
      itemCheckbox.checked = nonDefaultIds.includes(itemCheckbox.value) ? !checkedDefault : checkedDefault;
    });

    _writeToForm(form, {
      'nonDefaultIds': nonDefaultIds,
      'checkedDefault': checkedDefault,
    });
    _updateSelectionState(form);

    window.addEventListener('beforeunload', () => _writeState(form));
  }

  /**
   * Initialize the list item selection module.
   */
  function init() {
    document.querySelectorAll('.select-all-global').forEach((checkbox) => {
      checkbox.classList.remove("hidden");
    });
    _setupControls();
    document.querySelectorAll('.multi-page-selection').forEach( _setupMultiPageSelectionForm);
  }

  return {
    init: init,
    getAllSelected: getAllSelected
  };
});
