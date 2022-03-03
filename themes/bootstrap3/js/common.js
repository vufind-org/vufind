/*global grecaptcha, isPhoneNumberValid */
/*exported VuFind, htmlEncode, deparam, getUrlRoot, phoneNumberFormHandler, recaptchaOnLoad, resetCaptcha, bulkFormHandler, setupMultiILSLoginFields */

// IE 9< console polyfill
window.console = window.console || { log: function polyfillLog() {} };

var VuFind = (function VuFind() {
  var defaultSearchBackend = null;
  var path = null;
  var _initialized = false;
  var _submodules = [];
  var _cspNonce = '';

  var _icons = {};
  var _translations = {};

  // Emit a custom event
  // Recommendation: prefix with vf-
  var emit = function emit(name, detail) {
    if (typeof detail === 'undefined') {
      document.dispatchEvent(new Event(name));
    } else {
      var event = document.createEvent('CustomEvent');
      event.initCustomEvent(name, true, true, detail); // name, canBubble, cancelable, detail
      document.dispatchEvent(event);
    }
  };
  // Listen shortcut to put everyone on the same element
  var listen = function listen(name, func) {
    document.addEventListener(name, func, false);
  };

  var register = function register(name, module) {
    if (_submodules.indexOf(name) === -1) {
      _submodules.push(name);
      this[name] = typeof module == 'function' ? module() : module;

      // If the object has already initialized, we should auto-init on register:
      if (_initialized && this[name].init) {
        this[name].init();
      }
    }
  };

  var initDisableSubmitOnClick = function initDisableSubmitOnClick() {
    $('[data-disable-on-submit]').on('submit', function handleOnClickDisable() {
      var $form = $(this);
      // Disable submit elements via setTimeout so that the submit button value gets
      // included in the submitted data before being disabled:
      setTimeout(
        function disableSubmit() {
          $form.find('[type=submit]').prop('disabled', true);
        },
        0
      );
    });
  };

  var init = function init() {
    for (var i = 0; i < _submodules.length; i++) {
      if (this[_submodules[i]].init) {
        this[_submodules[i]].init();
      }
    }
    _initialized = true;

    initDisableSubmitOnClick();
  };

  var addTranslations = function addTranslations(s) {
    for (var i in s) {
      if (Object.prototype.hasOwnProperty.call(s, i)) {
        _translations[i] = s[i];
      }
    }
  };
  var translate = function translate(op, _replacements) {
    var replacements = _replacements || {};
    var translation = _translations[op] || op;
    if (replacements) {
      for (var key in replacements) {
        if (Object.prototype.hasOwnProperty.call(replacements, key)) {
          translation = translation.replace(key, replacements[key]);
        }
      }
    }
    return translation;
  };

  var addIcons = function addIcons(s) {
    for (var i in s) {
      if (Object.prototype.hasOwnProperty.call(s, i)) {
        _icons[i] = s[i];
      }
    }
  };
  var icon = function icon(name) {
    if (typeof _icons[name] == "undefined") {
      console.error("JS icon missing: " + name);
      return name;
    }

    var html = _icons[name];

    return html;
  };
  // Icon shortcut methods
  var spinner = function spinner(extraClass = "") {
    let className = ("loading-spinner " + extraClass).trim();
    return '<span class="' + className + '">' + icon('spinner') + '</span>';
  };
  var loading = function loading(text = null, extraClass = "") {
    let className = ("loading-spinner " + extraClass).trim();
    let string = translate(text === null ? "loading" : text);
    return '<span class="' + className + '">' + icon('spinner') + string + '...</span>';
  };

  /**
   * Reload the page without causing trouble with POST parameters while keeping hash
   */
  var refreshPage = function refreshPage() {
    var parts = window.location.href.split('#');
    if (typeof parts[1] === 'undefined') {
      window.location.reload();
    } else {
      var href = parts[0];
      // Force reload with a timestamp
      href += href.indexOf('?') === -1 ? '?_=' : '&_=';
      href += new Date().getTime() + '#' + parts[1];
      window.location.href = href;
    }
  };

  var getCspNonce = function getCspNonce() {
    return _cspNonce;
  };

  var setCspNonce = function setCspNonce(nonce) {
    _cspNonce = nonce;
  };

  var updateCspNonce = function updateCspNonce(html) {
    // Fix any inline script nonces
    return html.replace(/(<script[^>]*) nonce=["'].*?["']/ig, '$1 nonce="' + getCspNonce() + '"');
  };

  var loadHtml = function loadHtml(_element, url, data, success) {
    var $elem = $(_element);
    if ($elem.length === 0) {
      return;
    }
    $.get(url, typeof data !== 'undefined' ? data : {}, function onComplete(responseText, textStatus, jqXhr) {
      if ('success' === textStatus || 'notmodified' === textStatus) {
        $elem.html(updateCspNonce(responseText));
      }
      if (typeof success !== 'undefined') {
        success(responseText, textStatus, jqXhr);
      }
    });
  };

  //Reveal
  return {
    defaultSearchBackend: defaultSearchBackend,
    path: path,

    addIcons: addIcons,
    addTranslations: addTranslations,
    init: init,
    emit: emit,
    getCspNonce: getCspNonce,
    icon: icon,
    listen: listen,
    refreshPage: refreshPage,
    register: register,
    setCspNonce: setCspNonce,
    spinner: spinner,
    loadHtml: loadHtml,
    loading: loading,
    translate: translate,
    updateCspNonce: updateCspNonce
  };
})();

/* --- GLOBAL FUNCTIONS --- */
function htmlEncode(value) {
  if (value) {
    return $('<div />').text(value).html();
  } else {
    return '';
  }
}
function extractClassParams(selector) {
  var str = $(selector).attr('class');
  if (typeof str === "undefined") {
    return [];
  }
  var params = {};
  var classes = str.split(/\s+/);
  for (var i = 0; i < classes.length; i++) {
    if (classes[i].indexOf(':') > 0) {
      var pair = classes[i].split(':');
      params[pair[0]] = pair[1];
    }
  }
  return params;
}
// Turn GET string into array
function deparam(url) {
  if (!url.match(/\?|&/)) {
    return [];
  }
  var request = {};
  var pairs = url.substring(url.indexOf('?') + 1).split('&');
  for (var i = 0; i < pairs.length; i++) {
    var pair = pairs[i].split('=');
    var name = decodeURIComponent(pair[0].replace(/\+/g, ' '));
    if (name.length === 0) {
      continue;
    }
    if (name.substring(name.length - 2) === '[]') {
      name = name.substring(0, name.length - 2);
      if (!request[name]) {
        request[name] = [];
      }
      request[name].push(decodeURIComponent(pair[1].replace(/\+/g, ' ')));
    } else {
      request[name] = decodeURIComponent(pair[1].replace(/\+/g, ' '));
    }
  }
  return request;
}

function getUrlRoot(url) {
  // Parse out the base URL for the current record:
  var urlroot = null;
  var urlParts = url.split(/[?#]/);
  var urlWithoutFragment = urlParts[0];
  var slashSlash = urlWithoutFragment.indexOf('//');
  if (VuFind.path === '' || VuFind.path === '/') {
    // special case -- VuFind installed at site root:
    var chunks = urlWithoutFragment.split('/');
    // We need to extract different offsets if this is a full vs. relative URL:
    urlroot = slashSlash > -1
      ? ('/' + chunks[3] + '/' + chunks[4])
      : ('/' + chunks[1] + '/' + chunks[2]);
  } else {
    // standard case -- VuFind has its own path under site:
    var pathInUrl = slashSlash > -1
      ? urlWithoutFragment.indexOf(VuFind.path, slashSlash + 2)
      : urlWithoutFragment.indexOf(VuFind.path);
    var parts = urlWithoutFragment.substring(pathInUrl + VuFind.path.length + 1).split('/');
    urlroot = '/' + parts[0] + '/' + parts[1];
  }
  return urlroot;
}

// Phone number validation
function phoneNumberFormHandler(numID, regionCode) {
  var phoneInput = document.getElementById(numID);
  var number = phoneInput.value;
  var valid = isPhoneNumberValid(number, regionCode);
  if (valid !== true) {
    if (typeof valid === 'string') {
      valid = VuFind.translate(valid);
    } else {
      valid = VuFind.translate('libphonenumber_invalid');
    }
    $(phoneInput).siblings('.help-block.with-errors').html(valid);
    $(phoneInput).closest('.form-group').addClass('sms-error');
    return false;
  } else {
    $(phoneInput).closest('.form-group').removeClass('sms-error');
    $(phoneInput).siblings('.help-block.with-errors').html('');
  }
}

// Setup captchas after Google script loads
function recaptchaOnLoad() {
  if (typeof grecaptcha !== 'undefined') {
    var captchas = $('.g-recaptcha:empty');
    for (var i = 0; i < captchas.length; i++) {
      $(captchas[i]).data('captchaId', grecaptcha.render(captchas[i], $(captchas[i]).data()));
    }
  }
}
function resetCaptcha($form) {
  if (typeof grecaptcha !== 'undefined') {
    var captcha = $form.find('.g-recaptcha');
    if (captcha.length > 0) {
      grecaptcha.reset(captcha.data('captchaId'));
    }
  }
}

function bulkFormHandler(event, data) {
  if ($('.checkbox-select-item:checked,checkbox-select-all:checked').length === 0) {
    VuFind.lightbox.alert(VuFind.translate('bulk_noitems_advice'), 'danger');
    return false;
  }
  for (var i in data) {
    if ('print' === data[i].name) {
      return true;
    }
  }
}

// Ready functions
function setupOffcanvas() {
  if ($('.sidebar').length > 0 && $(document.body).hasClass("offcanvas")) {
    $('[data-toggle="offcanvas"]').click(function offcanvasClick(e) {
      e.preventDefault();
      $('body.offcanvas').toggleClass('active');
    });
  }
}

function setupAutocomplete() {
  // If .autocomplete class is missing, autocomplete is disabled and we should bail out.
  var searchbox = $('#searchForm_lookfor.autocomplete');
  if (searchbox.length < 1) {
    return;
  }
  // Auto-submit based on config
  var acCallback = function ac_cb_noop() {};
  if (searchbox.hasClass("ac-auto-submit")) {
    acCallback = function autoSubmitAC(item, input) {
      input.val(item.value);
      $("#searchForm").submit();
      return false;
    };
  }
  // Search autocomplete
  searchbox.autocomplete({
    rtl: $(document.body).hasClass("rtl"),
    maxResults: 10,
    loadingString: VuFind.translate('loading') + '...',
    // Auto-submit selected item
    callback: acCallback,
    // AJAX call for autocomplete results
    handler: function vufindACHandler(input, cb) {
      var query = input.val();
      var searcher = extractClassParams(input);
      var hiddenFilters = [];
      $('#searchForm').find('input[name="hiddenFilters[]"]').each(function hiddenFiltersEach() {
        hiddenFilters.push($(this).val());
      });
      $.fn.autocomplete.ajax({
        url: VuFind.path + '/AJAX/JSON',
        data: {
          q: query,
          method: 'getACSuggestions',
          searcher: searcher.searcher,
          type: searcher.type ? searcher.type : $('#searchForm_type').val(),
          hiddenFilters: hiddenFilters
        },
        dataType: 'json',
        success: function autocompleteJSON(json) {
          if (json.data.suggestions.length > 0) {
            var datums = [];
            for (var j = 0; j < json.data.suggestions.length; j++) {
              datums.push(json.data.suggestions[j]);
            }
            cb(datums);
          } else {
            cb([]);
          }
        }
      });
    }
  });
  // Update autocomplete on type change
  $('#searchForm_type').change(function searchTypeChange() {
    searchbox.autocomplete().clearCache();
  });
}

/**
 * Handle arrow keys to jump to next record
 */
function keyboardShortcuts() {
  var $searchform = $('#searchForm_lookfor');
  if ($('.pager').length > 0) {
    $(window).keydown(function shortcutKeyDown(e) {
      if (!$searchform.is(':focus')) {
        var $target = null;
        switch (e.keyCode) {
        case 37: // left arrow key
          $target = $('.pager').find('a.previous');
          if ($target.length > 0) {
            $target[0].click();
            return;
          }
          break;
        case 38: // up arrow key
          if (e.ctrlKey) {
            $target = $('.pager').find('a.backtosearch');
            if ($target.length > 0) {
              $target[0].click();
              return;
            }
          }
          break;
        case 39: //right arrow key
          $target = $('.pager').find('a.next');
          if ($target.length > 0) {
            $target[0].click();
            return;
          }
          break;
        case 40: // down arrow key
          break;
        }
      }
    });
  }
}

function setupIeSupport() {
  // Disable Bootstrap modal focus enforce on IE since it breaks Recaptcha.
  // Cannot use conditional comments since IE 11 doesn't support them but still has
  // the issue
  var ua = window.navigator.userAgent;
  if (ua.indexOf('MSIE') || ua.indexOf('Trident/')) {
    $.fn.modal.Constructor.prototype.enforceFocus = function emptyEnforceFocus() { };
  }
}

function setupJumpMenus(_container) {
  var container = _container || $('body');
  container.find('select.jumpMenu').change(function jumpMenu(){ $(this).parent('form').submit(); });
}

function setupMultiILSLoginFields(loginMethods, idPrefix) {
  var searchPrefix = idPrefix ? '#' + idPrefix : '#';
  $(searchPrefix + 'target').change(function onChangeLoginTarget() {
    var target = $(this).val();
    var $username = $(searchPrefix + 'username');
    var $usernameGroup = $username.closest('.form-group');
    var $password = $(searchPrefix + 'password');
    if (loginMethods[target] === 'email') {
      $username.attr('type', 'email').attr('autocomplete', 'email');
      $usernameGroup.find('label.password-login').addClass('hidden');
      $usernameGroup.find('label.email-login').removeClass('hidden');
      $password.closest('.form-group').addClass('hidden');
      // Set password to a dummy value so that any checks for username+password work
      $password.val('****');
    } else {
      $username.attr('type', 'text').attr('autocomplete', 'username');
      $usernameGroup.find('label.password-login').removeClass('hidden');
      $usernameGroup.find('label.email-login').addClass('hidden');
      $password.closest('.form-group').removeClass('hidden');
      // Reset password from the dummy value in email login
      if ($password.val() === '****') {
        $password.val('');
      }
    }
  }).change();
}

function setupQRCodeLinks(_container) {
  var container = _container || $('body');

  container.find('a.qrcodeLink').click(function qrcodeToggle() {
    if ($(this).hasClass("active")) {
      $(this).html(VuFind.translate('qrcode_show')).removeClass("active");
    } else {
      $(this).html(VuFind.translate('qrcode_hide')).addClass("active");
    }

    var holder = $(this).next('.qrcode');
    if (holder.find('img').length === 0) {
      // We need to insert the QRCode image
      var template = holder.find('.qrCodeImgTag').html();
      holder.html(template);
    }
    holder.toggleClass('hidden');
    return false;
  });
}

/**
 * Function to check if browser supports current js.
 */
function checkForBrowserSupport() {
  /* jshint ignore:start */
  /* eslint-disable */
  try {
    // Arrow functions support
    () => { };
    // Class support
    class __ES6FeatureDetectionTest { };
    // Object initializer property and method shorthands
    let a = true;
    let b = { 
      a,
      c() { return true; },
      d: [1, 2, 3],
    };
    // Object destructuring
    let {c, d} = b;
    // Spread operator
    let e = [...d, 4];
  } catch (exception) {
    var outdated = document.getElementById('browser-outdated');
    if (outdated) {
      outdated.classList.remove('hidden');
    }
  }
  /* eslint-enable */
  /* jshint ignore:end */
}

$(document).ready(function commonDocReady() {
  checkForBrowserSupport();
  // Start up all of our submodules
  VuFind.init();
  // Setup search autocomplete
  setupAutocomplete();
  // Off canvas
  setupOffcanvas();
  // Keyboard shortcuts in detail view
  keyboardShortcuts();

  // support "jump menu" dropdown boxes
  setupJumpMenus();

  // handle QR code links
  setupQRCodeLinks();

  // Checkbox select all
  $('.checkbox-select-all').on('change', function selectAllCheckboxes() {
    var $form = this.form ? $(this.form) : $(this).closest('form');
    if (this.checked) {
      $form.find('.checkbox-select-item:not(:checked)').trigger('click');
    } else {
      $form.find('.checkbox-select-item:checked').trigger('click');
    }
    $('[form="' + $form.attr('id') + '"]').prop('checked', this.checked);
    $form.find('.checkbox-select-all').prop('checked', this.checked);
    $('.checkbox-select-all[form="' + $form.attr('id') + '"]').prop('checked', this.checked);
  });
  $('.checkbox-select-item').on('change', function selectAllDisable() {
    var $form = this.form ? $(this.form) : $(this).closest('form');
    if ($form.length === 0) {
      return;
    }
    if (!$(this).prop('checked')) {
      $form.find('.checkbox-select-all').prop('checked', false);
      $('.checkbox-select-all[form="' + $form.attr('id') + '"]').prop('checked', false);
    }
  });

  // Print
  var url = window.location.href;
  if (url.indexOf('?print=') !== -1 || url.indexOf('&print=') !== -1) {
    $("link[media='print']").attr("media", "all");
  }

  setupIeSupport();
});
