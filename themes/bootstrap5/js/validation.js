/* global VuFind, isPhoneNumberValid */

VuFind.register('validation', function Validation() {
  /**
   * Check field match validity
   * @param {Event} ev Event
   * @returns {boolean} Validity
   */
  function checkMatchValidity(ev) {
    const field = ev.target;
    const matchSelector = field.dataset.match;
    if (!matchSelector) {
      return true;
    }
    const matchEl = field.form.querySelector(matchSelector);
    if (matchEl.value !== field.value) {
      field.setCustomValidity(field.dataset.matchError || '');
      return false;
    }
    field.setCustomValidity('');
    return true;
  }

  /**
   * Check field phone number validity
   * @param {Event} ev Event
   * @returns {boolean} Validity
   */
  function checkPhoneNumberValidity(ev) {
    const field = ev.target;
    if (field.id && field.type === 'tel' && field.dataset.validatorRegion) {
      const valid = isPhoneNumberValid(field.value, field.dataset.validatorRegion);
      if (true !== valid) {
        field.setCustomValidity(typeof valid === 'string' ? valid : VuFind.translate('libphonenumber_invalid'));
        return false;
      }
    }
    field.setCustomValidity('');
    return true;
  }

  /**
   * Check field validity
   * @param {Event} ev Event
   */
  function checkValidity(ev) {
    const checks = [checkMatchValidity, checkPhoneNumberValidity];
    for (const check of checks) {
      if (!check(ev)) {
        return;
      }
    }
  }

  /**
   * Init custom form validation
   */
  function init() {
    document.addEventListener('input', checkValidity);
  }

  return {
    init: init
  };
});
