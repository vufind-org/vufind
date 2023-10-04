/*global VuFind */

VuFind.register("multiPageSelection", function MultiPageSelection() {

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

  function _writeToForm(form, data) {
    if (data.ids !== undefined) {
      form.querySelector('.non_default_ids').value = JSON.stringify(data.ids);
    }
    if (data.checkedDefault !== undefined) {
      form.querySelector('.checked_default').checked = data.checkedDefault;
    }
    if (data.selectAllChecked !== undefined) {
      form.querySelector('.checkbox-select-all').checked = data.selectAllChecked;
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
    let selectAllChecked = form.querySelector('.checkbox-select-all').checked;
    _sessionSet(form, 'selectAllChecked', selectAllChecked);
    _writeToForm(form, {
      'ids': nonDefaultIds,
      'checkedDefault': checkedDefault
    });
  }

  function _setupForm(form) {
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
    let selectAllChecked = _sessionGet(form, 'selectAllChecked') || false;

    form.querySelectorAll('.checkbox-select-item').forEach(itemCheckbox => {
      itemCheckbox.checked = nonDefaultIds.includes(itemCheckbox.value) ? !checkedDefault : checkedDefault;
      itemCheckbox.addEventListener('change', () => {
        _writeState(form);
      });
    });
    _writeToForm(form, {
      'ids': nonDefaultIds,
      'checkedDefault': checkedDefault,
      'selectAllChecked': selectAllChecked
    });

    form.querySelector('.checkbox-select-all').addEventListener('change', (event) => {
      _changeDefault(form, event.currentTarget.checked);
      _writeState(form);
    });
    window.addEventListener('beforeunload', () => _writeState(form));
  }

  function getAllSelected(form) {
    let selected = [];
    let nonDefaultIdsInput = form.querySelector('.non_default_ids');
    let checkedDefaultInput = form.querySelector('.checked_default');
    let allIdsInput = form.querySelector('.mps-all-ids');
    if (nonDefaultIdsInput !== null && checkedDefaultInput !== null && allIdsInput !== null) {
      let nonDefaultIds = JSON.parse(nonDefaultIdsInput.value);
      let allIds = JSON.parse(allIdsInput.value);
      if (checkedDefaultInput.checked) {
        selected = allIds.filter((id) => !nonDefaultIds.includes(id));
      } else {
        selected = nonDefaultIds;
      }
    } else {
      let addToSelected = function processCartFormValues(input) {
        if (-1 === selected.indexOf(input.value)) {
          selected.push(input.value);
        }
      };
      form.querySelectorAll('input[name="ids[]"]:checked').forEach(addToSelected);
      document.querySelectorAll('input[form="' + form.id + '"][name="ids[]"]:checked').forEach(addToSelected);
    }
    return selected;
  }

  function init() {
    document.querySelectorAll('.multi-page-selection').forEach( multiPageForm => {
      _setupForm(multiPageForm);
    });
  }

  return {
    init: init,
    getAllSelected: getAllSelected
  };
});
