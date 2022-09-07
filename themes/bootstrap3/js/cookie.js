/*global VuFind, iframemanager, CookieConsent */

VuFind.register('cookie', function cookie() {
  let config;
  let iframeManager = null;

  function updateServiceStatus() {
    // Update iframemanager:
    if (null !== iframeManager) {
      Object.entries(config.controlledIframeServices).forEach(([category, services]) => {
        if (CookieConsent.acceptedCategory(category)) {
          services.forEach(service => iframeManager.acceptService(service));
        } else {
          services.forEach(service => iframeManager.rejectService(service));
        }
      });
    }

    // Update other services:
    Object.entries(config.controlledVuFindServices).forEach(([category, services]) => {
      // Matomo:
      if (window._paq && services.indexOf('matomo') !== -1) {
        if (CookieConsent.acceptedCategory(category)) {
          window._paq.push(['setCookieConsentGiven']);
        }
      }
    });
  }

  function setupConsent(_config) {
    config = _config;
    if (null !== config.iframemanager) {
      iframeManager = iframemanager();
      iframeManager.run(config.iframemanager);
    }
    config.consentDialog.onConsent = function onConsent() {
      updateServiceStatus();
      VuFind.emit('cookie-consent-done');
    };
    config.consentDialog.onChange = function onChange() {
      updateServiceStatus();
      VuFind.emit('cookie-consent-change');
    };
    CookieConsent.run(config.consentDialog);
    VuFind.emit('cookie-consent-initialized');
  }

  function isServiceAllowed(serviceName)
  {
    for (const [category, services] of Object.entries(config.controlledVuFindServices)) {
      if (services.indexOf(serviceName) !== -1
        && CookieConsent.acceptedCategory(category)
      ) {
        return true;
      }
    }
    return false;
  }

  return {
    setupConsent: setupConsent,
    isServiceAllowed: isServiceAllowed
  };
});
