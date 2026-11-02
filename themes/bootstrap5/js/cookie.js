/*global VuFind */

VuFind.register('cookie', function cookie() {
  var _COOKIE_DOMAIN = false;
  var _COOKIE_PATH = '/';
  var _COOKIE_SAMESITE = 'Lax';

  let consentConfig = null;
  let consentElement = null;
  const millisecondsInDay = 86400000;

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
   * Get current consent data (back-compatible with previous releases).
   * @returns {object|null} Consent object, or null if not available
   */
  function getCurrentConsent() {
    const cookieData = get(consentConfig.cookieName);
    if (!cookieData) {
      return null;
    }
    try {
      return JSON.parse(cookieData);
    } catch (e) {
      console.warn(e);
      return null;
    }
  }

  /**
   * Check if a specific cookie category has been accepted by the user.
   * @param {string} category The category name.
   * @returns {boolean} Return whether the category is accepted or not
   */
  function isCategoryAccepted(category) {
    const consent = getCurrentConsent() || {};
    const categories = consent.categories || [];
    return categories.indexOf(category) !== -1;
  }

  /**
   * Check if two arrays contain different values. Order is not important.
   * @param {Array} a1 First array
   * @param {Array} a2 Second array
   * @returns {boolean} True if arrays contain different values.
   */
  function arraysDiffer(a1, a2) {
    if (a1.length !== a2.length) {
      return true;
    }
    if (a1.filter((value) => { return a2.indexOf(value) === -1; }).length > 0) {
      return true;
    }
    if (a2.filter((value) => { return a1.indexOf(value) === -1; }).length > 0) {
      return true;
    }
    return false;
  }

  /**
   * Create a new consent ID.
   * @returns {string} ID
   */
  function generateConsentId() {
    return '00000000-0000-4000-8000-000000000000'.replace(/0/g, () => ((Math.random() * 16) | 0).toString(16));
  }

  /**
   * Store consent data (back-compatible with previous releases).
   * @param {Array} acceptedCategories List of accepted categories
   */
  function saveConsent(acceptedCategories) {
    let categoriesChanged = true;
    let revisionChanged = true;
    let newConsent = true;

    let consent = getCurrentConsent() || {};
    if (consent.categories) {
      categoriesChanged = arraysDiffer(acceptedCategories, consent.categories);
      revisionChanged = consentConfig.revision !== consent.revision;
      newConsent = false;
    }

    const date = new Date();
    consent.categories = acceptedCategories;
    consent.revision = consentConfig.revision;
    consent.lastConsentTimestamp = date.toISOString();
    if (!consent.consentTimestamp) {
      consent.consentTimestamp = date.toISOString();
    }
    if (!consent.consentId) {
      consent.consentId = generateConsentId();
    }
    if (!consent.expirationTime || categoriesChanged || revisionChanged) {
      // Expiration time is expressed in milliseconds in the cookie:
      consent.expirationTime = date.getTime() + consentConfig.cookieExpirationDays * millisecondsInDay;
    }
    consent.languageCode = document.documentElement.lang;
    const cookieParams = _getCookieParams();

    // Cookies.set wants expiration in days from now:
    cookieParams.expires = (consent.expirationTime - date.getTime()) / millisecondsInDay;
    window.Cookies.set(consentConfig.cookieName, JSON.stringify(consent), cookieParams);

    if (newConsent) {
      VuFind.emit('cookie-consent-first-done');
    }

    VuFind.emit('cookie-consent-done');
    if (categoriesChanged || revisionChanged) {
      VuFind.emit('cookie-consent-changed');
    }
    VuFind.refreshPage();
  }

  /**
   * Save settings and close the overlay.
   */
  function saveSettings() {
    const categories = [];
    consentElement.querySelectorAll('.js-category-checkbox:checked').forEach(el => {
      const categoryId = el.dataset.category;
      if (categoryId) {
        categories.push(categoryId);
      }
    });

    // Ensure that essential categories are included no matter what:
    Object.entries(consentConfig.categoryConfig).forEach(([categoryId, category]) => {
      let accepted = categories.indexOf(categoryId) !== -1;
      if (category.Essential && !accepted) {
        categories.push(categoryId);
        accepted = true;
      }
      // Clear cookies from any inactive categories as necessary:
      if (consentConfig.autoClearCookies && !accepted && category.AutoClearCookies) {
        category.AutoClearCookies.forEach(autoClearData => {
          Object.entries(window.Cookies.get()).forEach(([existingCookieName]) => {
            let match;
            if (autoClearData.Name.substring(0, 1) === '/' && autoClearData.Name.slice(-1) === '/') {
              const re = new RegExp(autoClearData.Name.slice(1, -1));
              match = re.test(existingCookieName);
            } else {
              match = autoClearData.Name === existingCookieName;
            }
            if (match) {
              window.Cookies.remove(existingCookieName);
            }
          });
        });
      }
    });

    saveConsent(categories);

    // Hide the overlay:
    consentElement.classList.add('hidden');
  }

  /**
   * Set event handlers for cookie settings.
   */
  function setEventHandlers() {
    const settingsCollapseEl = consentElement.querySelector('.js-settings-collapse');
    const saveBtn = consentElement.querySelector('.js-save-settings');
    const acceptAllBtn = consentElement.querySelector('.js-accept-all');
    const acceptEssentialBtn = consentElement.querySelector('.js-accept-essential');

    if (settingsCollapseEl && saveBtn && acceptAllBtn) {
      settingsCollapseEl.addEventListener('show.bs.collapse', (e) => {
        if (e.target === settingsCollapseEl) {
          saveBtn.classList.remove('hidden');
          acceptAllBtn.classList.add('hidden');
        }
      });
      settingsCollapseEl.addEventListener('hide.bs.collapse', (e) => {
        if (e.target === settingsCollapseEl) {
          saveBtn.classList.add('hidden');
          acceptAllBtn.classList.remove('hidden');
        }
      });
    }

    if (saveBtn) {
      saveBtn.addEventListener('click', saveSettings);
    }
    if (acceptAllBtn) {
      acceptAllBtn.addEventListener('click', () => {
        consentElement.querySelectorAll('.js-category-checkbox')
          .forEach(checkbox => checkbox.checked = true);
        saveSettings();
      });
    }
    if (acceptEssentialBtn) {
      acceptEssentialBtn.addEventListener('click', () => {
        consentElement.querySelectorAll('.js-category-checkbox')
          .forEach(checkbox => checkbox.checked = 'essential' in checkbox.dataset);
        saveSettings();
      });
    }
  }

  /**
   * Load consent settings HTML.
   */
  function loadConsentSettings() {
    if (consentElement.classList.contains('js-loaded')) {
      return;
    }
    consentElement.classList.add('js-loaded');
    fetch(VuFind.path + '/AJAX/JSON?method=getCookieConsent')
      .then(rawResponse => rawResponse.json())
      .then(response => {
        VuFind.setInnerHtml(consentElement, response.data.html);
        setEventHandlers();
      })
      .catch(() => {
        consentElement.textContent = VuFind.translate('error_occurred');
      });
  }

  /**
   * Show cookie settings.
   */
  function showSettings() {
    if (!consentElement) {
      return;
    }
    loadConsentSettings();
    consentElement.classList.remove('hidden');
  }

  /**
   * Set up the cookie consent dialog and its event handlers.
   * @param {object} _config The configuration object for cookie consent.
   */
  function setupConsent(_config) {
    consentConfig = _config;
    consentElement = document.querySelector('.js-cookie-consent');
    if (!consentElement) {
      return;
    }
    if (consentElement.classList.contains('js-loaded')) {
      setEventHandlers();
    } else {
      // Load cookie settings on demand:
      VuFind.observerManager.createIntersectionObserver(
        'cookieConsent',
        () => loadConsentSettings(false),
        [consentElement]
      );
    }

    // Add a click handler for links that open cookie settings:
    document.addEventListener('click', (e) => {
      if (e.target instanceof HTMLAnchorElement) {
        const cc = e.target.dataset.cc;
        if (cc === 'show-preferencesModal' || cc === 'show-consentModal') {
          showSettings();
          consentElement.focus();
          e.preventDefault();
        }
      }
    });

    // cookie-consent-done is triggered on every page load when a consent is available:
    if (null !== consentConfig.acceptedCategories) {
      VuFind.emit('cookie-consent-done');
    }
    VuFind.emit('cookie-consent-initialized');
  }

  /**
   * Check if a specific service is allowed.
   * @param {string} serviceName The name of the service.
   * @returns {boolean} Return whether the service is allowed.
   */
  function isServiceAllowed(serviceName) {
    for (const [category, services] of Object.entries(consentConfig.controlledVuFindServices)) {
      if (services.indexOf(serviceName) !== -1 && isCategoryAccepted(category)) {
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
