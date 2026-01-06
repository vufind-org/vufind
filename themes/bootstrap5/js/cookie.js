/*global VuFind, CookieConsent */

VuFind.register('cookie', function cookie() {
  let consentConfig = null;
  var _COOKIE_DOMAIN = false;
  var _COOKIE_PATH = '/';
  var _COOKIE_SAMESITE = 'Lax';
  
  /**
   * Set the domain for the cookie
   * @param {string} domain The domain to set
   */
  function setDomain(domain) {
    _COOKIE_DOMAIN = domain;
  }

  /**
   * Set the path for the cookie
   * @param {string} path The path to set
   */
  function setCookiePath(path) {
    _COOKIE_PATH = path;
  }

  /**
   * Set the samesite attribute for the cookie
   * @param {string} sameSite The samesite attribute to set.
   */
  function setCookieSameSite(sameSite) {
    _COOKIE_SAMESITE = sameSite;
  }

  /**
   * Get all the cookie parameters
   * @returns {object} Return the cookie parameters
   */
  function _getCookieParams() {
    return { path: _COOKIE_PATH, domain: _COOKIE_DOMAIN, SameSite: _COOKIE_SAMESITE };
  }

  /**
   * Get the value of a cookie
   * @param {string} name The name of the cookie
   * @returns {string|undefined} Return the value of the cookie
   */
  function get(name) {
    return window.Cookies.get(name);
  }

  /**
   * Set a cookie
   * @param {string} name  The name of a cookie
   * @param {string} value The value of the cookie
   * @returns {string|undefined} Return the value of the cookie (or undefined if setting failed)
   */
  function set(name, value) {
    return window.Cookies.set(name, value, _getCookieParams());
  }

  /**
   * Remove a cookie.
   * @param {string} name The name of the cookie to remove.
   * @returns {boolean} Return true if the cookie was removed.
   */
  function remove(name) {
    return window.Cookies.remove(name, _getCookieParams());
  }

  /**
   * Update the status of services
   */
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

  /**
   * Set up the cookie consent dialog and its event handlers.
   * @param {object} _config The configuration object for cookie consent.
   */
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

  /**
   * Check if a specific cookie category has been accepted by the user.
   * @param {string} category The category name.
   * @returns {boolean} Return whether the category is accepted or not
   */
  function isCategoryAccepted(category) {
    return CookieConsent.acceptedCategory(category);
  }

  /**
   * Check if a specific service is allowed.
   * @param {string} serviceName The name of the service.
   * @returns {boolean} Return whether the service is allowed.
   */
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

  /**
   * Return the full consent configuration object.
   * @returns {object|null} The consent configuration.
   */
  function getConsentConfig() {
    return consentConfig;
  }

  return {
    setDomain: setDomain,
    setCookiePath: setCookiePath,
    setCookieSameSite: setCookieSameSite,
    get: get,
    set: set,
    remove: remove,
    setupConsent: setupConsent,
    isCategoryAccepted: isCategoryAccepted,
    isServiceAllowed: isServiceAllowed,
    getConsentConfig: getConsentConfig
  };
});
