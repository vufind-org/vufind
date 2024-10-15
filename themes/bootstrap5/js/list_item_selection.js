/*global VuFind */

VuFind.register("listItemSelection", function ListItemSelection() {

  function _sessionSet(form, key, data) {
    let formId = form.id;
    let formStorage = JSON.parse(window.sessionStorage.getItem(formId) || "{}");
    formStorage[key] = data;
    window.sessionStorage.setItem(formId, JSON.stringify(formStorage));
  }

  function _sessionGet(form, key) {
    let formId = form.id;
    let formStorage = JSON.parse(window.sessionStorage.getItem(formId) || "{}");
    return formStorage[key];
  }

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

  function getItemCheckboxes(form) {
    return document.querySelectorAll('#' + form.id + ' .checkbox-select-item, .checkbox-select-item[form="' + form.id + '"]');
  }

  function _check(checkbox, checked) {
    if (checkbox instanceof NodeList) {
      checkbox.forEach((cb) => _check(cb, checked));
    } else if (checkbox !== null) {
      checkbox.checked = checked;
    }
  }

  function _updateSelectionCount(button, count) {
    if (count < 1) {
      button.classList.add('hidden');
    } else {
      button.innerHTML = VuFind.translate('clear_selection', { '%%count%%': count});
      button.classList.remove('hidden');
    }
  }

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
      document.querySelectorAll('input[form="' + form.id + '"][name="ids[]"]:checked').forEach(addToSelected);
    }
    return selected;
  }

  function _allOnPageAreSelected(form) {
    return form.querySelectorAll('.checkbox-select-item:not(:checked)').length === 0
      && document.querySelectorAll('.checkbox-select-item[form="' + form.id + '"]:not(:checked)').length === 0;
  }

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
   *
   * @private
   * @param form
   * @param data
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
   *
   * @private
   * @param form
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
    document.querySelectorAll('#' + form.id + ' .checkbox-select-all, .checkbox-select-all[form="' + form.id + '"]')
      .forEach((checkbox) => _check(checkbox, _allOnPageAreSelected(form)));
    document.querySelectorAll('#' + form.id + ' .checkbox-select-all-global, .checkbox-select-all-global[form="' + form.id + '"]')
      .forEach((checkbox) => _check(checkbox, _allGlobalAreSelected(form)));
    document.querySelectorAll('#' + form.id + ' .clear-selection, .clear-selection[form="' + form.id + '"]')
      .forEach((button) => _updateSelectionCount(button, getAllSelected(form).length));
  }

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
