/**
 origin: {finc}
 called by view helper/controller:
 usage: {}
 modified for finc:
 * finc-specific file for browser-cached lightbox forms #15903
 configured in: {}
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  if (typeof sessionStorage === 'undefined' || typeof MutationObserver !== 'function') {
    return;
  }

  const cache = sessionStorage;
  const cacheKeyPrefix = 'lightbox_form_';

  function clearCache() {
    (new Array(cache.length)).fill().map((_, index) => cache.key(index))
      .forEach(key => cache.removeItem(key));
  }

  const modal = document.querySelector('#modal .modal-body');
  const config = {attributes: false, childList: true, subtree: false};

  const observer = new MutationObserver(function () {
    const form = modal.querySelector('form:not([data-non-cached])');
    if (!form) {
      return;
    }

    form.addEventListener('submit', clearCache);
    const cacheKeyBase = Array.from(form.attributes).map(attr => attr.value);
    const cacheKey = cacheKeyPrefix + btoa(cacheKeyBase.join());

    const inputs = Array.from(form.querySelectorAll('input:not([type=password])'))
        .concat(Array.from(form.querySelectorAll('textarea')));
    JSON.parse(cache.getItem(cacheKey) || '[]').forEach(function (value, index) {
      if (inputs[index]) {
        inputs[index].value = value;
      }
    });

    modal.querySelectorAll('a:not([data-reset-forms]):not(#cart-confirm-empty):not(#cart-confirm-delete)').forEach(function (link) {
      link.onclick = function () {
        var cachedItems = JSON.stringify(inputs.map(input => input.value));
        cache.setItem(cacheKey, cachedItems);
      };
    });
  });

  clearCache();
  document.addEventListener('VuFind.lightbox.closed', clearCache);
  observer.observe(modal, config);
});
