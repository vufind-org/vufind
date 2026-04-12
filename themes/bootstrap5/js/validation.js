/* global VuFind */

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
   * Is the provided phone number valid?
   * @param {string} number Phone number to validate
   * @param {string} region Region for validation
   * @returns {string|boolean} True if valid, error string or false if not valid
   */
  function isPhoneNumberValid(number, region) {
    const result = window.libphonenumber.isValidPhoneNumber(number, region);
    // If the result is negative, see if we can map it to a standard error message:
    if (!result) {
      const lengthMessage = window.libphonenumber.validatePhoneNumberLength(number, region);
      if (lengthMessage === 'NOT_A_NUMBER') {
        return 'libphonenumber_notanumber';
      }
      if (lengthMessage === 'TOO_LONG') {
        return 'libphonenumber_toolong';
      }
      if (lengthMessage === 'TOO_SHORT') {
        return 'libphonenumber_tooshort';
      }
    }
    return result;
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
        field.setCustomValidity(VuFind.translate(typeof valid === 'string' ? valid : 'libphonenumber_invalid'));
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
