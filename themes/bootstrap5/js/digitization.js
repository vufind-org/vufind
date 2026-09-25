/*global VuFind */
/*exported setUpDigitizationRequestForm */

/**
 * Set up the digitization request form by toggling the page number fields
 * based on the partial digitization checkbox.
 *
 * @param {string} recordId The ID of the record for the digitization request
 */
function setUpDigitizationRequestForm(recordId) {
  function togglePartialFields() {
    var $digitizationTypeRadios = document.querySelectorAll('input[name="gatheredDetails[digitizationType]"]');
    var $partialDigitizationContainer = document.querySelector('#partialFields');

    let checkedRadio = null;
    $digitizationTypeRadios.forEach(function(radio) {
      if (radio.checked) {
        checkedRadio = radio;
      }
    });

    if (checkedRadio) {
      if (checkedRadio.value === 'partial') {
        $partialDigitizationContainer.removeAttribute('disabled');
      } else {
        $partialDigitizationContainer.setAttribute('disabled', 'disabled');
      }
    }
  }

  function togglePageFields() {
    var $partialDigitizationTypeRadios = document.querySelectorAll('input[name="gatheredDetails[partialDigitizationType]"]');
    var $pageRangeContainer = document.querySelector('#pageRangeFields');

    let checkedRadio = null;
    $partialDigitizationTypeRadios.forEach(function(radio) {
      if (radio.checked) {
        checkedRadio = radio;
      }
    });

    if (checkedRadio) {
      if (checkedRadio.value === 'full') {
        $pageRangeContainer.setAttribute('disabled', 'disabled');
      } else {
        $pageRangeContainer.removeAttribute('disabled');
      }
    }
  }

  document.querySelectorAll('input[name="gatheredDetails[digitizationType]"]').forEach(
    function(radio) {
      radio.addEventListener('change', togglePartialFields);
    }
  );
  document.querySelectorAll('input[name="gatheredDetails[partialDigitizationType]"]').forEach(
    function(radio) {
      radio.addEventListener('change', togglePageFields);
    }
  );
  
  togglePartialFields();
  togglePageFields();

}
