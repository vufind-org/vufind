/*global Autocomplete, grecaptcha, isPhoneNumberValid */
/*exported VuFind, bulkFormHandler, deparam, escapeHtmlAttr, getFocusableNodes, getUrlRoot, htmlEncode, phoneNumberFormHandler, recaptchaOnLoad, resetCaptcha, setupMultiILSLoginFields, unwrapJQuery */

// IE 9< console polyfill
window.console = window.console || { log: function polyfillLog() {} };

var VuFind = (function VuFind() {
  var defaultSearchBackend = null;
  var path = null;
  var _initialized = false;
  var _submodules = [];
  var _cspNonce = '';
  var _searchId = null;

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

  /**
   * Evaluate a callback
   */
  var evalCallback = function evalCallback(callback, event, data) {
    if ('function' === typeof window[callback]) {
      return window[callback](event, data);
    }
    var parts = callback.split('.');
    if (typeof window[parts[0]] === 'object') {
      var obj = window[parts[0]];
      for (var i = 1; i < parts.length; i++) {
        if (typeof obj[parts[i]] === 'undefined') {
          obj = false;
          break;
        }
        obj = obj[parts[i]];
      }
      if ('function' === typeof obj) {
        return obj(event, data);
      }
    }
    console.error('Callback function ' + callback + ' not found.');
    return null;
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

  var initClickHandlers = function initClickHandlers() {
    window.addEventListener(
      'click',
      function handleClick(event) {
        let elem = event.target;
        if (elem.hasAttribute('data-click-callback')) {
          return evalCallback(elem.dataset.clickCallback, event, {});
        }
        if (elem.hasAttribute('data-click-set-checked')) {
          document.getElementById(elem.dataset.clickSetChecked).checked = true;
          event.preventDefault();
        }
      }
    );
    window.addEventListener(
      'change',
      function handleChange(event) {
        let elem = event.target;
        if (elem.hasAttribute('data-submit-on-change')) {
          elem.form.requestSubmit();
        }
      }
    );
  };

  var init = function init() {
    for (var i = 0; i < _submodules.length; i++) {
      if (this[_submodules[i]].init) {
        this[_submodules[i]].init();
      }
    }
    _initialized = true;

    initDisableSubmitOnClick();
    initClickHandlers();
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
  var icon = function icon(name, attrs = {}) {
    if (typeof _icons[name] == "undefined") {
      console.error("JS icon missing: " + name);
      return name;
    }

    var html = _icons[name];

    // Add additional attributes
    function addAttrs(_html, _attrs = {}) {
      var mod = String(_html);
      for (var attr in _attrs) {
        if (Object.prototype.hasOwnProperty.call(_attrs, attr)) {
          var sliceStart = html.indexOf(" ");
          var sliceEnd = sliceStart;
          var value = _attrs[attr];
          var regex = new RegExp(` ${attr}=(['"])([^\\1]+?)\\1`);
          var existing = html.match(regex);
          if (existing) {
            sliceStart = existing.index;
            sliceEnd = sliceStart + existing[0].length;
            value = existing[2] + " " + value;
          }
          mod = mod.slice(0, sliceStart) +
              " " + attr + '="' + value + '"' +
              mod.slice(sliceEnd);
        }
      }
      return mod;
    }

    if (typeof attrs == "string") {
      return addAttrs(html, { class: attrs });
    }

    if (Object.keys(attrs).length > 0) {
      return addAttrs(html, attrs);
    }

    return html;
  };
  // Icon shortcut methods
  var spinner = function spinner(extraClass = "") {
    let className = ("loading-spinner " + extraClass).trim();
    return '<span class="' + className + '">' + icon('spinner') + '</span>';
  };
  var loading = function loading(text = null, extraClass = "") {
    let className = ("loading-spinner " + extraClass).trim();
    let string = translate(text === null ? 'loading_ellipsis' : text);
    return '<span class="' + className + '">' + icon('spinner') + ' ' + string + '</span>';
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

  var isPrinting = function() {
    return Boolean(window.location.search.match(/[?&]print=/));
  };

  var getCurrentSearchId = function getCurrentSearchId() {
    if (null !== _searchId) {
      return _searchId;
    }
    var match = location.href.match(/[&?]sid=(\d+)/);
    return match ? match[1] : '';
  };

  var setCurrentSearchId = function setCurrentSearchId(searchId) {
    _searchId = searchId;
  };

  //Reveal
  return {
    defaultSearchBackend: defaultSearchBackend,
    path: path,

    addIcons: addIcons,
    addTranslations: addTranslations,
    init: init,
    emit: emit,
    evalCallback: evalCallback,
    getCspNonce: getCspNonce,
    icon: icon,
    isPrinting: isPrinting,
    listen: listen,
    refreshPage: refreshPage,
    register: register,
    setCspNonce: setCspNonce,
    spinner: spinner,
    loadHtml: loadHtml,
    loading: loading,
    translate: translate,
    updateCspNonce: updateCspNonce,
    getCurrentSearchId: getCurrentSearchId,
    setCurrentSearchId: setCurrentSearchId
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

/**
 * Keyboard and focus controllers
 * Adapted from Micromodal
 * - https://github.com/ghosh/Micromodal/blob/master/lib/src/index.js
 */
const FOCUSABLE_ELEMENTS = ['a[href]', 'area[href]', 'input:not([disabled]):not([type="hidden"]):not([aria-hidden])', 'select:not([disabled]):not([aria-hidden])', 'textarea:not([disabled]):not([aria-hidden])', 'button:not([disabled]):not([aria-hidden])', 'iframe', 'object', 'embed', '[contenteditable]', '[tabindex]:not([tabindex^="-"])'];
function getFocusableNodes(container) {
  const nodes = container.querySelectorAll(FOCUSABLE_ELEMENTS);
  return Array.from(nodes);
}

/**
 * Adapted from Laminas.
 * Source: https://github.com/laminas/laminas-escaper/blob/2.13.x/src/Escaper.php
 *
 * @param  {string} str Attribute
 * @return {string}
 */
function escapeHtmlAttr(str) {
  if (!str) {
    return str;
  }

  const namedEntities = {
    34: 'quot', // quotation mark
    38: 'amp', // ampersand
    60: 'lt', // less-than sign
    62: 'gt', // greater-than sign
  };

  const regexp = new RegExp(/[^a-z0-9,\\.\\-_]/giu);
  return str.replace(regexp, (char) => {
    const code = char.charCodeAt(0);

    // Named entities
    if (code in namedEntities) {
      return `&${namedEntities[code]};`;
    }

    /**
     * The following replaces characters undefined in HTML with the
     * hex entity for the Unicode replacement character.
     */
    if (
      (code >= 0x7f && code <= 0x9f) ||
      (code <= 0x1f && char !== "\t" && char !== "\n" && char !== "\r")
    ) {
      return '&#xFFFD;';
    }

    const hex = code.toString(16).toUpperCase();

    if (code > 255) {
      return `&#x${hex.padStart(4, 0)};`;
    }

    return `&#x${hex.padStart(2, 0)};`;
  });
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
  var $searchbox = $('#searchForm_lookfor.autocomplete');
  if ($searchbox.length === 0) {
    return;
  }

  const typeahead = new Autocomplete({
    rtl: $(document.body).hasClass("rtl"),
    maxResults: 10,
    loadingString: VuFind.translate('loading_ellipsis'),
  });

  let cache = {};
  const input = $searchbox[0];
  typeahead(input, function vufindACHandler(query, callback) {
    const classParams = extractClassParams(input);
    const searcher = classParams.searcher;
    const type = classParams.type ? classParams.type : $('#searchForm_type').val();

    const cacheKey = searcher + "|" + type;
    if (typeof cache[cacheKey] === "undefined") {
      cache[cacheKey] = {};
    }

    if (typeof cache[cacheKey][query] !== "undefined") {
      callback(cache[cacheKey][query]);
      return;
    }

    var hiddenFilters = [];
    $('#searchForm').find('input[name="hiddenFilters[]"]').each(function hiddenFiltersEach() {
      hiddenFilters.push($(this).val());
    });

    $.ajax({
      url: VuFind.path + '/AJAX/JSON',
      data: {
        q: query,
        method: 'getACSuggestions',
        searcher: searcher,
        type: type,
        hiddenFilters,
      },
      dataType: 'json',
      success: function autocompleteJSON(json) {
        const highlighted = json.data.suggestions.map(
          (item) => ({
            text: item.replace(query, `<b>${query}</b>`),
            value: item,
          })
        );
        cache[cacheKey][query] = highlighted;
        callback(highlighted);
      }
    });
  });

  // Bind autocomplete auto submit
  if ($searchbox.hasClass("ac-auto-submit")) {
    input.addEventListener("ac-select", (event) => {
      const value = typeof event.detail === "string"
        ? event.detail
        : event.detail.value;
      input.value = value;
      $("#searchForm").submit();
    });
  }
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

function unwrapJQuery(node) {
  return node instanceof Node ? node : node[0];
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
    var holder = $(this).next('.qrcode');
    if (holder.find('img').length === 0) {
      // We need to insert the QRCode image
      var template = holder.find('.qrCodeImgTag').html();
      holder.html(template);
    }
  });
}

$(document).ready(function commonDocReady() {
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
