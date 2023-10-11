/*global VuFind */

VuFind.register("listItemSelection", function ListItemSelection() {

  // clicks checkboxes and triggers click event
  function _click(checkbox, checked) {
    if (checkbox instanceof NodeList) {
      checkbox.forEach((cb) => _click(cb, checked));
    } else if (checkbox !== null && checkbox.checked !== checked) {
      checkbox.click();
    }
  }

  // changes check state of checkboxes but does not trigger click event
  function _check(checkbox, checked) {
    if (checkbox instanceof NodeList) {
      checkbox.forEach((cb) => _check(cb, checked));
    } else if (checkbox !== null) {
      checkbox.checked = checked;
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

  function _isMultiPageSelectionForm(form) {
    return form.classList.contains('multi-page-selection');
  }

  function _allOnPageAreSelected(form) {
    return form.querySelectorAll('.checkbox-select-item:not(:checked)').length === 0;
  }

  function _allGlobalAreSelected(form) {
    let compareArrays = (a, b) =>
      a.length === b.length && a.every((element, index) => element === b[index]);
    let allIdsInput = form.querySelector('.all-ids-global');
    return allIdsInput !== null && compareArrays(getAllSelected(form), JSON.parse(allIdsInput.value));
  }

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

  function _changeDefault(form, defaultValue) {
    _sessionSet(form, 'checkedDefault', defaultValue);
    _sessionSet(form, 'nonDefaultIds', []);
  }

  function _writeToForm(form, data = {}) {
    if (data.ids !== undefined) {
      form.querySelector('.non_default_ids').value = JSON.stringify(data.ids);
    }
    if (data.checkedDefault !== undefined) {
      form.querySelector('.checked_default').checked = data.checkedDefault;
    }
    if (_allOnPageAreSelected(form)) {
      data.selectAllOnPageChecked = true;
    }
    if (data.selectAllOnPageChecked !== undefined) {
      _check(document.querySelectorAll('[form="' + form.id + '"][type="checkbox"]'), data.selectAllOnPageChecked);
      _check(form.querySelectorAll('.checkbox-select-all'), data.selectAllOnPageChecked);
      _check(form.querySelectorAll('.checkbox-select-all[form="' + form.id + '"]'), data.selectAllOnPageChecked);
    }
    if (_allGlobalAreSelected(form)) {
      data.selectAllGlobalChecked = true;
    }
    if (data.selectAllGlobalChecked !== undefined) {
      _check(document.querySelectorAll('[form="' + form.id + '"][type="checkbox"]'), data.selectAllGlobalChecked);
      _check(form.querySelectorAll('.checkbox-select-all-global'), data.selectAllGlobalChecked);
      _check(form.querySelectorAll('.checkbox-select-all-global[form="' + form.id + '"]'), data.selectAllGlobalChecked);
    }
  }

  function _writeState(form) {
    let checkedDefault = _sessionGet(form, 'checkedDefault') || false;
    let nonDefaultIds = _sessionGet(form, 'nonDefaultIds') || [];
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
    _sessionSet(form, 'nonDefaultIds', nonDefaultIds);
    _writeToForm(form, {
      'ids': nonDefaultIds,
      'checkedDefault': checkedDefault,
    });
  }

  function _selectAllCheckbox(checkbox) {
    let form = checkbox.form ? checkbox.form : checkbox.closest('form');
    if (form == null) {
      return;
    }
    _click(form.querySelectorAll('.checkbox-select-item'), checkbox.checked);
    if (_isMultiPageSelectionForm(form)) {
      _writeState(form);
    }
    _writeToForm(form, {
      'selectAllOnPageChecked': checkbox.checked
    });
  }

  function _setupCheckboxes() {
    document.querySelectorAll('.checkbox-select-all').forEach((checkbox) => {
      checkbox.addEventListener('change', () => _selectAllCheckbox(checkbox));
    });
    document.querySelectorAll('.checkbox-select-all-global').forEach((checkbox) => {
      checkbox.addEventListener('change', () => _selectAllCheckbox(checkbox));
    });
    document.querySelectorAll('.checkbox-select-item').forEach((checkbox) => {
      checkbox.addEventListener('change', () => {
        let form = checkbox.form ? checkbox.form : checkbox.closest('form');
        if (form == null) {
          return;
        }
        if (_isMultiPageSelectionForm(form)) {
          _writeState(form);
        }
        if (!checkbox.checked) {
          _writeToForm(form, {
            'selectAllOnPageChecked': false,
            'selectAllGlobalChecked': false
          });
        }
        _writeToForm(form);
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

    form.querySelectorAll('.checkbox-select-item').forEach(itemCheckbox => {
      itemCheckbox.checked = nonDefaultIds.includes(itemCheckbox.value) ? !checkedDefault : checkedDefault;
    });

    _writeToForm(form, {
      'ids': nonDefaultIds,
      'checkedDefault': checkedDefault,
    });

    let checkboxSelectAllGlobal = form.querySelector('.checkbox-select-all-global');
    if (checkboxSelectAllGlobal != null) {
      checkboxSelectAllGlobal.addEventListener('change', (event) => {
        _changeDefault(form, event.currentTarget.checked);
        _writeState(form);
      });
    }

    window.addEventListener('beforeunload', () => _writeState(form));
  }

  function init() {
    _setupCheckboxes();
    document.querySelectorAll('.multi-page-selection').forEach( _setupMultiPageSelectionForm);
  }

  return {
    init: init,
    getAllSelected: getAllSelected
  };
});
