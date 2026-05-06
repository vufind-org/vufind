/*global bootstrap, userIsLoggedIn, VuFind */
VuFind.register('account', function Account() {
  // Retrieved statuses
  var LOADING = -1 * Math.PI; // waiting for request
  var MISSING = -2 * Math.PI; // no data available
  var INACTIVE = -3 * Math.PI; // status element missing
  var _statuses = {};
  var _pendingNotifications = {};

  // Account Icons
  var ICON_LEVELS = {
    "NONE": 0,
    "GOOD": 1,
    "WARNING": 2,
    "DANGER": 3
  };
  var _accountIcons = {};
  //                                [icon, additional class]
  _accountIcons[ICON_LEVELS.NONE] = ["my-account", "account-status-none"];
  _accountIcons[ICON_LEVELS.GOOD] = ["my-account-notification", "account-status-good text-success"];
  _accountIcons[ICON_LEVELS.WARNING] = ["my-account-notification", "account-status-warning text-warning"];
  _accountIcons[ICON_LEVELS.DANGER] = ["my-account-warning", "account-status-danger text-danger"];

  var _submodules = {};
  var _clearCaches = false;
  var _sessionDataPrefix = "vf-account-status-";

  /**
   * Get storage key used for saving data into session storage
   * @param {string} module Name of the module to get session storage key for
   * @returns {string} Complete session storage key
   */
  var _getStorageKey = function _getStorageKey(module) {
    return _sessionDataPrefix + module;
  };

  /**
   * Load data from session storage
   * @param {string} module Name of the module to load data for
   * @returns {any|null} Parsed value from storage or null if not defined
   */
  var _loadSessionData = function _loadSessionData(module) {
    var theme = VuFind.getTheme();
    var json = sessionStorage.getItem(_getStorageKey(module));
    if (null !== json) {
      var data = JSON.parse(json);
      if (typeof data[theme] !== 'undefined') {
        return data[theme];
      }
    }
    return null;
  };

  /**
   * Save data for module into session storage
   * @param {string} module Name of the module to save data for
   */
  var _saveSessionData = function _saveSessionData(module) {
    var theme = VuFind.getTheme();
    var json = sessionStorage.getItem(_getStorageKey(module));
    var data = {};
    if (null !== json) {
      data = JSON.parse(json) || {};
    }
    data[theme] = _statuses[module];
    sessionStorage.setItem(
      _getStorageKey(module),
      JSON.stringify(data)
    );
  };

  /**
   * Remove data from session storage for module
   * @param {string} module Name of the module to clear session storage
   */
  var _clearSessionData = function _clearSessionData(module) {
    sessionStorage.removeItem(_getStorageKey(module));
  };

  // Forward declaration for clearAllCaches
  var clearAllCaches = function clearAllCachesForward() {};

  /**
   * Clear the specified client data cache; pass in empty/undefined value to clear
   * all caches. Note that clearing all caches will prevent further data from loading
   * on the current page, and should only be performed when exiting the page via a
   * link, form submission, etc. Cleared data will be reloaded by AJAX on the next
   * page load.
   * @param {string|undefined} name Cache to clear (undefined/empty for all)
   */
  var clearCache = function clearCache(name) {
    if (typeof name === "undefined" || name === '') {
      clearAllCaches();
    } else {
      _clearSessionData(name);
    }
  };

  /**
   * Get status as a number for module from _statuses object.
   * (See constants defined above -- LOADING, MISSING, INACTIVE)
   * @param {string} module Name of the module to get status for
   * @returns {number} Number indicating the current status for the module
   */
  var _getStatus = function _getStatus(module) {
    return (typeof _statuses[module] === "undefined") ? LOADING : _statuses[module];
  };

  /**
   * Render statuses for each module defined in _submodules object. Contains module name
   * and associated data.
   */
  var _render = function _render() {
    var accountStatus = ICON_LEVELS.NONE;
    Object.entries(_submodules).forEach(([moduleName, moduleData]) => {
      const status = _getStatus(moduleName);
      if (status === INACTIVE) {
        return;
      }
      const elements = document.querySelectorAll(moduleData.selector);
      if (elements.length === 0) {
        // This could happen if the DOM is changed dynamically
        _statuses[moduleName] = INACTIVE;
        return;
      }
      if (status === MISSING) {
        elements.forEach((element) => element.classList.add('hidden'));
      } else if (status === LOADING) {
        elements.forEach((element) => {
          element.classList.remove('hidden');
          VuFind.setInnerHtml(element, VuFind.spinner());
        });
      } else if (Object.prototype.hasOwnProperty.call(moduleData, 'render')) {
        // Render using render function (with jQuery for back-compatibility):
        let moduleStatus = moduleData.render($(elements), _statuses[moduleName], ICON_LEVELS);
        if (moduleStatus > accountStatus) {
          accountStatus = moduleStatus;
        }
      } else {
        // Render with default method:
        const subStatus = _statuses[moduleName];
        if (subStatus.level > accountStatus) {
          accountStatus = subStatus.level;
        }
        elements.forEach((element) => {
          if (subStatus.html !== '') {
            element.classList.remove('hidden');
            VuFind.setInnerHtml(element, subStatus.html);
            element.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((subEl) => bootstrap.Tooltip.getOrCreateInstance(subEl));
          } else {
            element.classList.add('hidden');
          }
        });
      }
    });

    const accountIconEl = document.querySelector('#account-icon');
    if (accountIconEl) {
      VuFind.setInnerHtml(accountIconEl, VuFind.icon(..._accountIcons[accountStatus]));
      if (accountStatus > ICON_LEVELS.NONE) {
        accountIconEl.dataset.bsToggle = 'tooltip';
        accountIconEl.dataset.bsPlacement = 'bottom';
        accountIconEl.dataset.bsTitle = VuFind.translate('account_has_alerts');
        bootstrap.Tooltip.getOrCreateInstance(accountIconEl);
      } else {
        bootstrap.Tooltip.getOrCreateInstance(accountIconEl).dispose();
      }
      Object.entries(ICON_LEVELS).forEach(([, level]) => {
        accountIconEl.classList.remove('notification-level-' + level);
      });
      accountIconEl.classList.add('notification-level-' + accountStatus);
    }
  };
  /**
   * Get module status with ajax call.
   * @param {string} module Name of the module to lookup
   */
  var _ajaxLookup = function _ajaxLookup(module) {
    $.ajax({
      url: VuFind.path + '/AJAX/JSON?method=' + _submodules[module].ajaxMethod,
      dataType: 'json'
    })
      .done(function ajaxLookupDone(response) {
        _statuses[module] = response.data;
      })
      .fail(function ajaxLookupFail() {
        _statuses[module] = MISSING;
      })
      .always(function ajaxLookupAlways() {
        _saveSessionData(module);
        _render();
      });
  };

  /**
   * Find module elements and set initial values for them using previously saved session data. If _clearCaches is set
   * to true, will clear session data and return.
   * @param {string} module Name of the module
   * @returns {void}
   */
  var _load = function _load(module) {
    if (_clearCaches) {
      _clearSessionData(module);
      return;
    }
    var $element = $(_submodules[module].selector);
    if (!$element) {
      _statuses[module] = INACTIVE;
    } else {
      var session = _loadSessionData(module);
      if (
        session === null ||
        session === LOADING ||
        session === MISSING
      ) {
        _statuses[module] = LOADING;
        _ajaxLookup(module);
      } else {
        _statuses[module] = session;
      }
      _render();
    }
  };

  /**
   * Set a notification message for a specific module
   * @param {string} module Name of the module
   * @param {object} status Status to update
   */
  var notify = function notify(module, status) {
    if (Object.prototype.hasOwnProperty.call(_submodules, module) && typeof _submodules[module].updateNeeded !== 'undefined') {
      if (_submodules[module].updateNeeded(_getStatus(module), status)) {
        clearCache(module);
        _load(module);
      }
    } else {
      // We currently support only a single pending notification for each module
      _pendingNotifications[module] = status;
    }
  };

  /**
   * Initialize the account AJAX system
   */
  var init = function init() {
    // Update information when certain actions are performed
    $("form[data-clear-account-cache]").on("submit", function dataClearCacheForm() {
      clearCache($(this).attr("data-clear-account-cache"));
    });
    $("a[data-clear-account-cache]").on("click", function dataClearCacheLink() {
      clearCache($(this).attr("data-clear-account-cache"));
    });
    $("select[data-clear-account-cache]").on("change", function dataClearCacheSelect() {
      clearCache($(this).attr("data-clear-account-cache"));
    });
  };

  /**
   * Register a module for account statuses
   * @param {string} name Name of the module to register
   * @param {Function | object} module Function or object to save into _submodules object
   */
  var register = function register(name, module) {
    if (typeof _submodules[name] === "undefined") {
      _submodules[name] = typeof module == 'function' ? module() : module;
    }
    var $el = $(_submodules[name].selector);
    if ($el.length > 0) {
      $el.removeClass("hidden");
      _statuses[name] = LOADING;
      _load(name);
    } else {
      _statuses[name] = INACTIVE;
    }
    if (typeof _pendingNotifications[name] !== 'undefined' && _pendingNotifications[name] !== null) {
      var status = _pendingNotifications[name];
      _pendingNotifications[name] = null;
      notify(name, status);
    }
  };

  /**
   * Clear all account status data cached in the client's browser. This will prevent future data from
   * loading on the current page and should only be called when exiting the page by clicking a link,
   * submitting a form, etc.
   */
  clearAllCaches = function clearAllCachesReal() {
    // Set a flag so that any modules yet to be loaded are cleared as well
    _clearCaches = true;
    for (var sub in _submodules) {
      if (Object.prototype.hasOwnProperty.call(_submodules, sub)) {
        _load(sub);
      }
    }
  };

  return {
    init: init,
    clearCache: clearCache,
    clearAllCaches: clearAllCaches,
    notify: notify,
    // if user is logged out, clear cache instead of register
    register: (typeof userIsLoggedIn !== 'undefined' && userIsLoggedIn) ? register : clearCache
  };
});

$(function registerAccountAjax() {
  VuFind.account.register("fines", {
    selector: ".fines-status",
    ajaxMethod: "getUserFines",
    updateNeeded: function updateNeeded(currentStatus, status) {
      return status.total !== currentStatus.total;
    }
  });

  VuFind.account.register("checkedOut", {
    selector: ".checkedout-status",
    ajaxMethod: "getUserTransactions",
    updateNeeded: function updateNeeded(currentStatus, status) {
      return status.ok !== currentStatus.ok || status.warn !== currentStatus.warn || status.overdue !== currentStatus.overdue;
    }
  });

  VuFind.account.register("holds", {
    selector: ".holds-status",
    ajaxMethod: "getUserHolds",
    updateNeeded: function updateNeeded(currentStatus, status) {
      return status.available !== currentStatus.available || status.in_transit !== currentStatus.in_transit || status.other !== currentStatus.other;
    }
  });

  VuFind.account.register("illRequests", {
    selector: ".illrequests-status",
    ajaxMethod: "getUserILLRequests",
    updateNeeded: function updateNeeded(currentStatus, status) {
      return status.available !== currentStatus.available || status.in_transit !== currentStatus.in_transit || status.other !== currentStatus.other;
    }
  });

  VuFind.account.register("storageRetrievalRequests", {
    selector: ".storageretrievalrequests-status",
    ajaxMethod: "getUserStorageRetrievalRequests",
    updateNeeded: function updateNeeded(currentStatus, status) {
      return status.available !== currentStatus.available || status.in_transit !== currentStatus.in_transit || status.other !== currentStatus.other;
    }
  });
});
