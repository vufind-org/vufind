/*global VuFind, iframemanager, CookieConsent */

VuFind.register('cookie', function cookie() {
  let consentConfig = null;
  let iframeManager = null;

  function updateServiceStatus() {
    // Update iframemanager:
    if (null !== iframeManager) {
      Object.entries(consentConfig.controlledIframeServices).forEach(([category, services]) => {
        if (CookieConsent.acceptedCategory(category)) {
          services.forEach(service => iframeManager.acceptService(service));
        } else {
          services.forEach(service => iframeManager.rejectService(service));
        }
      });
    }

    // Update other services:
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
    if (null !== consentConfig.iframemanager) {
      iframeManager = iframemanager();
      iframeManager.run(consentConfig.iframemanager);
    }
    consentConfig.consentDialog.onConsent = function onConsent() {
      updateServiceStatus();
      VuFind.emit('cookie-consent-done');
    };
    consentConfig.consentDialog.onChange = function onChange() {
      updateServiceStatus();
      VuFind.emit('cookie-consent-change');
    };
    CookieConsent.run(consentConfig.consentDialog);
    VuFind.emit('cookie-consent-initialized');
  }

  function isCategoryAccepted(category)
  {
    return CookieConsent.acceptedCategory(category);
  }

  function isServiceAllowed(serviceName)
  {
    for (const [category, services] of Object.entries(consentConfig.controlledVuFindServices)) {
      if (services.indexOf(serviceName) !== -1
        && CookieConsent.acceptedCategory(category)
      ) {
        return true;
      }
    }
    return false;
  }

  function getConsentConfig()
  {
    return consentConfig;
  }

  return {
    setupConsent: setupConsent,
    isCategoryAccepted: isCategoryAccepted,
    isServiceAllowed: isServiceAllowed,
    getConsentConfig: getConsentConfig
  };
});
