/* global VuFind */

VuFind.register('pow-captcha', function PoWCaptchas() {
  const READY_CLASS = "js-pow-captcha-ready";
  const CHALLENGE_SELECTOR = '[name="pow-captcha-challenge"]';

  function powCaptchaInit(form) {
    if (form.classList.contains(READY_CLASS)) {
      return;
    }

    form.classList.add(READY_CLASS);

    // Wait for user interaction to start work

    form.addEventListener("input", () => { powPerformWork(form) }, { once: true });

    const statusEl = form.querySelector(".pow-captcha-status");
    statusEl.textContent = "PoW Captcha waiting for interaction.";
  }

  function powPerformWork(form) {
    const challenge = form.querySelector('[name="pow-captcha-challenge"]').value;
    const difficulty = Number(form.querySelector('[name="pow-captcha-difficulty"]').value);
    const hashAlgo = form.querySelector('[name="pow-captcha-hash-algo"]').value;
    const start = Number(form.querySelector('[name="pow-captcha-start"]')?.value ?? 0);

    const statusEl = form.querySelector(".pow-captcha-status");
    statusEl.textContent = `PoW Captcha doing work (${hashAlgo}, difficulty ${difficulty}).`;

    // Do the number crunching in a Web Worker for speed and responsiveness
    const worker = new Worker(`${VuFind.path}/themes/root/js/captcha-pow-worker.js`);

    worker.onmessage = (event) => {
      worker.terminate();
      powResolveWork(form, event.data);
    };

    worker.onerror = (event) => {
      worker.terminate();
      powRejectWork(form, event);
    };

    worker.postMessage({ challenge, difficulty, hashAlgo, start });
  }

  function powResolveWork(form, { nonce, iters, ms }) {
    const nonceInput = form.querySelector('[name="pow-captcha-nonce"]');
    nonceInput.value = nonce;

    const statusEl = form.querySelector(".pow-captcha-status");
    statusEl.textContent = `PoW Captcha: nonce (${nonce}) found after ${iters} iterations (${ms}ms).`;
  }

  function powRejectWork(form, event) {
    // @TODO: click here to request a new challenge
    console.error(event);
  }

  function init() {
    // Find PoW challenges
    for (const pow of document.querySelectorAll(CHALLENGE_SELECTOR)) {
      powCaptchaInit(pow.closest("form"));
    }

    // Listen for future forms
    const observer = new MutationObserver(lookForNewPoWCaptchas);
    observer.observe(document, { childList: true, subtree: true });

    function lookForNewPoWCaptchas(records, observer) {
      for (const record of records) {
        for (const addedNode of record.addedNodes) {
          if (addedNode instanceof Text) {
            continue;
          }
          if (addedNode.matches(CHALLENGE_SELECTOR)) {
            powCaptchaInit(addedNode.closest("form"));
          }
          for (const pow of document.querySelectorAll(CHALLENGE_SELECTOR)) {
            powCaptchaInit(pow.closest("form"));
          }
        }
      }
    }
  }

  return { init };
});
