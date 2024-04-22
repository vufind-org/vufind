/*global VuFind, CookieConsent */

VuFind.register('cookie', function cookie() {
  let consentConfig = null;

  function updateServiceStatus() {
    Object.entries(consentConfig.controlledVuFindServices).forEach(([category, services]) => {
      // Matomo:
      if (window._paq && services.indexOf('matomo') !== -1) {
        if (CookieConsent.acceptedCategory(category)) {
          window._paq.push(['setCookieConsentGiven']);
        }
      }
    });
  }

  function setupConsent(_config) {
    consentConfig = _config;
    consentConfig.consentDialog.onFirstConsent = function onFirstConsent() {
      VuFind.emit('cookie-consent-first-done');
    };
    consentConfig.consentDialog.onConsent = function onConsent() {
      updateServiceStatus();
      VuFind.emit('cookie-consent-done');
    };
    consentConfig.consentDialog.onChange = function onChange() {
      updateServiceStatus();
      VuFind.emit('cookie-consent-changed');
    };
    CookieConsent.run(consentConfig.consentDialog);
    VuFind.emit('cookie-consent-initialized');
  }

  function isCategoryAccepted(category) {
    return CookieConsent.acceptedCategory(category);
  }

  function isServiceAllowed(serviceName) {
    for (const [category, services] of Object.entries(consentConfig.controlledVuFindServices)) {
      if (services.indexOf(serviceName) !== -1
        && CookieConsent.acceptedCategory(category)
      ) {
        return true;
      }
    }
    return false;
  }

  function getConsentConfig() {
    return consentConfig;
  }

  return {
    setupConsent: setupConsent,
    isCategoryAccepted: isCategoryAccepted,
    isServiceAllowed: isServiceAllowed,
    getConsentConfig: getConsentConfig
  };
});
