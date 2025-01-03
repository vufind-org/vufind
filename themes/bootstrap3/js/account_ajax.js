/*global userIsLoggedIn, VuFind */
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

  var _submodules = [];
  var _clearCaches = false;
  var _sessionDataPrefix = "vf-account-status-";

  var _getStorageKey = function _getStorageKey(module) {
    return _sessionDataPrefix + module;
  };

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

  var _clearSessionData = function _clearSessionData(module) {
    sessionStorage.removeItem(_getStorageKey(module));
  };

  // Forward declaration for clearAllCaches
  var clearAllCaches = function clearAllCachesForward() {};

  // Clearing save forces AJAX update next page load
  var clearCache = function clearCache(name) {
    if (typeof name === "undefined" || name === '') {
      clearAllCaches();
    } else {
      _clearSessionData(name);
    }
  };

  var _getStatus = function _getStatus(module) {
    return (typeof _statuses[module] === "undefined") ? LOADING : _statuses[module];
  };

  var _render = function _render() {
    var accountStatus = ICON_LEVELS.NONE;
    for (var sub in _submodules) {
      if (Object.prototype.hasOwnProperty.call(_submodules, sub)) {
        var status = _getStatus(sub);
        if (status === INACTIVE) {
          continue;
        }
        var $element = $(_submodules[sub].selector);
        if ($element.length === 0) {
          // This could happen if the DOM is changed dynamically
          _statuses[sub] = INACTIVE;
          continue;
        }
        if (status === MISSING) {
          $element.addClass('hidden');
        } else {
          $element.removeClass('hidden');
          if (status === LOADING) {
            $element.html(VuFind.spinner());
          } else if (Object.prototype.hasOwnProperty.call(_submodules[sub], 'render')) {
            // Render using render function:
            let moduleStatus = _submodules[sub].render($element, _statuses[sub], ICON_LEVELS);
            if (moduleStatus > accountStatus) {
              accountStatus = moduleStatus;
            }
          } else {
            // Render with default method:
            const subStatus = _statuses[sub];
            if (subStatus.html !== '') {
              $element.html(subStatus.html);
            } else {
              $element.addClass("hidden");
            }
            $('[data-toggle="tooltip"],[data-bs-toggle="tooltip"]', $element).tooltip();
            if (subStatus.level > accountStatus) {
              accountStatus = subStatus.level;
            }
          }
        }
      }
    }
    const accountIconEl = document.querySelector('#account-icon');
    if (accountIconEl) {
      VuFind.setInnerHtml(accountIconEl, VuFind.icon(..._accountIcons[accountStatus]));
      if (accountStatus > ICON_LEVELS.NONE) {
        accountIconEl.dataset.toggle = 'tooltip';
        accountIconEl.dataset.placement = 'bottom';
        accountIconEl.title = VuFind.translate('account_has_alerts');
        $(accountIconEl).tooltip();
      } else {
        $(accountIconEl).tooltip('destroy');
      }
      Object.entries(ICON_LEVELS).forEach(([, level]) => {
        accountIconEl.classList.remove('notification-level-' + level);
      });
      accountIconEl.classList.add('notification-level-' + accountStatus);
    }
  };
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

  var _load = function _load(module) {
    if (_clearCaches) {
      _clearSessionData(module);
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
    register: userIsLoggedIn ? register : clearCache
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
