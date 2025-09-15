/*global VuFind */
VuFind.register('fines', function fines() {
  const CHECKBOX_ITEM_SELECTOR = 'form#online_payment_form .js-select-item';
  const CHECKBOX_ALL_SELECTOR = 'form#online_payment_form .js-select-all';

  let paySelectedDefaultText;

  /**
   * Get the whole part from currency in cents
   * @param {number} currency Number to divide by 100
   * @returns {number} Currency without digits
   */
  function getWhole(currency)
  {
    return Math.trunc(currency / 100);
  }

  /**
   * Get the fraction part from currency in cents padded to two characters
   * @param {number} currency Currency the get fraction from
   * @returns {string} Fraction of the currency
   */
  function getFraction(currency)
  {
    var fraction = String(currency % 100);
    while (fraction.length < 2) {
      fraction += '0';
    }
    return fraction;
  }

  /**
   * Format currency according to a template where 11 is whole and 22 is fraction
   * @param {number} currency Currency to use in template
   * @param {string} template Template string to insert into
   * @returns {string} Formatted amount
   */
  function formatAmount(currency, template)
  {
    // Prevent cases where whole with 22 or 122 gets overwritten with the fraction
    return template.replace('11', '{whole}')
      .replace('22', '{fraction}')
      .replace('{whole}', getWhole(currency))
      .replace('{fraction}', getFraction(currency));
  }

  /**
   * Set the aria-live attribute for an element
   * @param {Element} element Element to set aria live into
   * @param {string} politeness Aria live value (empty string to remove attribute)
   * @returns {void}
   */
  function ariaLive(element, politeness)
  {
    if (politeness) {
      element.setAttribute('aria-live', politeness);
    } else {
      element.removeAttribute('aria-live');
    }
  }

  /**
   * Update the information on fines selected for payment
   */
  function updateSelectedInfo() {
    const payButton = document.querySelector('.js-pay-selected');
    const minimumElem = document.querySelector('.js-minimum-payment');
    const srInfoElem = document.querySelector('.js-selected-to-pay-sr');
    const totalPaymentElem = document.querySelector('.js-payment-total-due');
    const remainingElem = document.querySelector('.js-payment-remaining-amount');
    if (!payButton || !minimumElem || !srInfoElem || !totalPaymentElem || !remainingElem) {
      console.warn('Online payment page element(s) missing');
      return;
    }

    // Count the balance for selected fees:
    var selectedAmount = 0;
    document.querySelectorAll(CHECKBOX_ITEM_SELECTOR + ':checked').forEach((cb) => {
      selectedAmount += parseInt(cb.dataset.amount, 10);
    });

    // If something is selected, include any service fee:
    var serviceFee = 0;
    if (selectedAmount) {
      const serviceFeeElem = document.querySelector('.js-service-fee');
      if (serviceFeeElem) {
        serviceFee = parseInt(serviceFeeElem.dataset.raw, 10);
      }
    }

    const minimumAmount = parseInt(minimumElem.dataset.raw, 10);
    if (selectedAmount + serviceFee >= minimumAmount) {
      payButton.removeAttribute('disabled');
      payButton.value = formatAmount(selectedAmount + serviceFee, payButton.dataset.template);
      minimumElem.classList.add('hidden');
      ariaLive(minimumElem, '');
    } else {
      payButton.setAttribute('disabled', 'disabled');
      payButton.value = paySelectedDefaultText;
      // Show minimum payable amount if it's non-zero:
      if (minimumAmount) {
        minimumElem.classList.remove('hidden');
        ariaLive(minimumElem, 'polite');
      } else {
        minimumElem.classList.add('hidden');
        ariaLive(minimumElem, '');
      }
    }

    // Update SR info:
    srInfoElem.textContent = formatAmount(selectedAmount + serviceFee, srInfoElem.dataset.template);
    ariaLive(srInfoElem, 'polite');

    // Update summary for remaining after payment:
    const remainingAmount = parseInt(totalPaymentElem.dataset.raw, 10) - selectedAmount;
    remainingElem.textContent = formatAmount(remainingAmount, remainingElem.dataset.template);
  }

  /**
   * Initialize payment
   * @returns {void}
   */
  function init()
  {
    const payButton = document.querySelector('.js-pay-selected');
    if (null === payButton) {
      // No button, no need to do anything
      return;
    }

    paySelectedDefaultText = payButton.value;
    updateSelectedInfo();
    document.querySelectorAll(CHECKBOX_ITEM_SELECTOR + ',' + CHECKBOX_ALL_SELECTOR).forEach((checkbox) => {
      checkbox.addEventListener('change', updateSelectedInfo);
    });
  }

  /**
   * Register a payment
   * @param {string} localIdentifier Local payment identifier
   */
  function registerPayment(localIdentifier) {
    fetch(
      VuFind.path + '/AJAX/JSON?method=onlinePaymentRegister',
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({ localIdentifier: localIdentifier }),
      }
    )
      .then(() => {
        // Clear account notification cache and reload current page without parameters
        VuFind.account.clearCache();
      }).catch(() => {
        // Any actual error message will be displayed on reload (see below)
      }).finally(() => {
        // Reload current page without parameters
        location.href = window.location.href.split('?')[0];
      });
  }

  var my = {
    init: init,
    registerPayment: registerPayment
  };

  return my;
});
