/* global VuFind */

VuFind.register('altcha', function AltchaCaptchas() {
  const READY_CLASS = "js-altcha-ready";
  const CHALLENGE_SELECTOR = ".altcha-challenge";

  let altchaLoaded = null;

  /**
   * @param {HTMLFormElement} form Container form
   * @returns {void}
   */
  function altchaPerformWork(form) {
  	if (altchaLoaded === null) {
  		altchaLoaded = import("./vendor/altcha.js");
  	}

    const statusEl = form.querySelector("altcha-status");
    statusEl.textContent = "Altcha loading...";

  	altchaLoaded.then(({ Altcha }) => {
    	statusEl.textContent = "Altcha starting work.";

    	const challenge = form.querySelector(CHALLENGE_SELECTOR).value;
  	});
  }

  /**
   * @param {HTMLFormElement} form Container form
   * @returns {void}
   */
  function altchaInit(form) {
    if (form.classList.contains(READY_CLASS)) {
      return;
    }

    form.classList.add(READY_CLASS);

    // Wait for user interaction to start work
    form.addEventListener("input", function powStart() {
      altchaPerformWork(form);
    }, { once: true });

	const statusEl = form.querySelector("altcha-status");
    statusEl.textContent = "Altcha waiting for interaction.";
  }

  /**
   * @returns {void}
   */
  function init() {
    // Find PoW challenges
    for (const challenge of document.querySelectorAll(CHALLENGE_SELECTOR)) {
      altchaInit(challenge.closest("form"));
    }

    /**
     * @param {Array<MutationRecord>} records Nodes just added to the document
     */
    function lookForNewPoWCaptchas(records) {
      for (const record of records) {
        for (const addedNode of record.addedNodes) {
          if (addedNode instanceof Text) {
            continue;
          }
          if (addedNode.matches(CHALLENGE_SELECTOR)) {
            altchaInit(addedNode.closest("form"));
          }
          for (const challenge of document.querySelectorAll(CHALLENGE_SELECTOR)) {
            altchaInit(challenge.closest("form"));
          }
        }
      }
    }

    // Listen for future forms
    const observer = new MutationObserver(lookForNewPoWCaptchas);
    observer.observe(document, { childList: true, subtree: true });
  }

  return { init };
});
