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
    if (data.selectAllGlobalChecked !== undefined) {
      let checkboxSelectAllGlobal = form.querySelector('.checkbox-select-all-global');
      if (checkboxSelectAllGlobal != null) {
        form.querySelector('.checkbox-select-all-global').checked = data.selectAllGlobalChecked;
      }
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
    let checkboxSelectAllGlobal = form.querySelector('.checkbox-select-all-global');
    if (checkboxSelectAllGlobal != null) {
      _sessionSet(form, 'selectAllGlobalChecked', checkboxSelectAllGlobal.checked);
    }
    _writeToForm(form, {
      'ids': nonDefaultIds,
      'checkedDefault': checkedDefault
    });
  }

  function _selectAllCheckboxes(checkbox) {
    var $form = checkbox.form ? $(checkbox.form) : $(checkbox).closest('form');
    if (checkbox.checked) {
      $form.find('.checkbox-select-item:not(:checked)').trigger('click');
    } else {
      $form.find('.checkbox-select-item:checked').trigger('click');
      $form.find('.checkbox-select-all:checked').trigger('click');
      $form.find('.checkbox-select-all-global:checked').trigger('click');
    }
    $('[form="' + $form.attr('id') + '"]').prop('checked', checkbox.checked);
    $form.find('.checkbox-select-all').prop('checked', checkbox.checked);
    $('.checkbox-select-all[form="' + $form.attr('id') + '"]').prop('checked', checkbox.checked);
  }

  function _setupCheckboxes() {
    $('.checkbox-select-all').on('change', function selectAll() {
      _selectAllCheckboxes(this);
    });
    $('.checkbox-select-all-global').on('change', function selectAllGlobal() {
      _selectAllCheckboxes(this);
      var $form = this.form ? $(this.form) : $(this).closest('form');
      if (this.checked) {
        $form.find('.checkbox-select-all-global:not(:checked)').trigger('click');
      }
      $form.find('.checkbox-select-all-global').prop('checked', this.checked);
      $('.checkbox-select-all-global[form="' + $form.attr('id') + '"]').prop('checked', this.checked);
    });
    $('.checkbox-select-item').on('change', function selectAllDisable() {
      var $form = this.form ? $(this.form) : $(this).closest('form');
      if ($form.length === 0) {
        return;
      }
      if (!$(this).prop('checked')) {
        $form.find('.checkbox-select-all').prop('checked', false);
        $form.find('.checkbox-select-all-global').prop('checked', false);
        $('.checkbox-select-all[form="' + $form.attr('id') + '"]').prop('checked', false);
        $('.checkbox-select-all-global[form="' + $form.attr('id') + '"]').prop('checked', false);
      }
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
    let selectAllGlobalChecked = _sessionGet(form, 'selectAllGlobalChecked') || false;

    form.querySelectorAll('.checkbox-select-item').forEach(itemCheckbox => {
      itemCheckbox.checked = nonDefaultIds.includes(itemCheckbox.value) ? !checkedDefault : checkedDefault;
      itemCheckbox.addEventListener('change', () => {
        _writeState(form);
      });
    });
    _writeToForm(form, {
      'ids': nonDefaultIds,
      'checkedDefault': checkedDefault,
      'selectAllGlobalChecked': selectAllGlobalChecked
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
    _setupCheckboxes();
    document.querySelectorAll('.multi-page-selection').forEach( multiPageForm => {
      _setupForm(multiPageForm);
    });
  }

  return {
    init: init,
    getAllSelected: getAllSelected
  };
});
