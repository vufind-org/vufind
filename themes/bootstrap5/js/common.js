/*global bootstrap, grecaptcha, isPhoneNumberValid, loadCovers */
/*exported VuFind, bulkFormHandler, deparam, escapeHtmlAttr, extractClassParams, getFocusableNodes, getUrlRoot, htmlEncode, phoneNumberFormHandler, recaptchaOnLoad, resetCaptcha, setupMultiILSLoginFields, unwrapJQuery */

var VuFind = (function VuFind() {
  var defaultSearchBackend = null;
  var path = null;
  var _initialized = false;
  var _submodules = [];
  var _cspNonce = '';
  var _searchId = null;

  var _icons = {};
  var _translations = {};

  var _elementBase;
  var _iconsCache = {};

  // Event controls

  let listeners = {};
  function unlisten(event, fn) {
    if (typeof listeners[event] === "undefined") {
      return;
    }

    const index = listeners[event].indexOf(fn);

    if (index > -1) {
      listeners[event].splice(index, 1);
    }
  }

  // Add a function to call when an event is emitted
  //
  // Options:
  // - once: remove this listener after it's been called
  function listen(event, fn, { once = false } = {}) {
    if (typeof listeners[event] === "undefined") {
      listeners[event] = [];
    }

    listeners[event].push(fn);
    const removeListener = () => unlisten(event, fn);

    if (once) {
      // Remove a "once" listener after calling
      // Add the function to remove the listener
      // to the array, listeners are called in order
      listeners[event].push(removeListener);
    }

    // Return a function to disable the listener
    // Makes it easier to control activating and deactivating listeners
    // This is common for similar libraries
    return removeListener;
  }

  // Broadcast an event, passing arguments to all listeners
  function emit(event, ...args) {
    // No listeners for this event
    if (typeof listeners[event] === "undefined") {
      return;
    }

    // iterate over a copy of the listeners array
    // this prevents listeners from being skipped
    // if the listener before it is removed during execution
    for (const fn of Array.from(listeners[event])) {
      fn(...args);
    }
  }

  // Module control

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

  var initDisableSubmitOnClick = () => {
    var forms = document.querySelectorAll("[data-disable-on-submit]");
    forms.forEach(form =>
      form.addEventListener("submit", () => {
        var submitButtons = form.querySelectorAll('[type="submit"]');
        // Disable submit elements via setTimeout so that the submit button value gets
        // included in the submitted data before being disabled:
        setTimeout(() => {
          submitButtons.forEach(button => button.disabled = true);
        }, 0);
      }));
  };

  var initClickHandlers = function initClickHandlers() {
    let checkClickHandlers = function (event, elem) {
      if (elem.hasAttribute('data-click-callback')) {
        return evalCallback(elem.dataset.clickCallback, event, {});
      }
      if (elem.hasAttribute('data-click-set-checked')) {
        document.getElementById(elem.dataset.clickSetChecked).checked = true;
        event.preventDefault();
      }
      if (elem.hasAttribute('data-toggle-aria-expanded')) {
        elem.setAttribute('aria-expanded', elem.getAttribute('aria-expanded') === 'true' ? 'false' : 'true');
        event.preventDefault();
      }
      // Check also parent node for spans (e.g. a button with icon)
      if (!event.defaultPrevented && elem.localName === 'span' && elem.parentNode) {
        checkClickHandlers(event, elem.parentNode);
      }
    };

    window.addEventListener(
      'click',
      function handleClick(event) {
        checkClickHandlers(event, event.target);
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

  /**
   * Get an icon identified by a name.
   *
   * @param {String} name          Name of the icon to create
   * @param {Object} attrs         Object containing attributes,
   *                               key is the attribute of an HTMLElement,
   *                               value is the values to add for the attribute.
   * @param {Boolean}   returnElement [Optional] Should the function return an HTMLElement.
   *                               Default is false.
   *
   * @returns {String|HTMLElement}
   */
  var icon = function icon(name, attrs = {}, returnElement = false) {
    if (typeof _icons[name] == "undefined") {
      console.error("JS icon missing: " + name);
      return name;
    }
    // Create a template element for icon function
    if (!_elementBase) {
      _elementBase = document.createElement('div');
    }
    const cacheKey = `${name}||${JSON.stringify(attrs)}`;
    if (_iconsCache[cacheKey]) {
      return returnElement
        ? _iconsCache[cacheKey].cloneNode(true)
        : _iconsCache[cacheKey].outerHTML;
    }

    const clone = _elementBase.cloneNode();
    clone.insertAdjacentHTML('afterbegin', _icons[name]);
    let element = clone.firstChild;

    // Add additional attributes
    function addAttrs(_element, _attrs = {}) {
      Object.keys(_attrs).forEach(key => {
        if (key !== 'class') {
          _element.setAttribute(key, _attrs[key]);
          return;
        }
        let newAttrs = _attrs[key].split(" ");
        const oldAttrs = _element.getAttribute(key) || [];
        const newAttrsSet = new Set([...newAttrs, ...oldAttrs.split(" ")]);
        _element.className = Array.from(newAttrsSet).join(" ");
      });
    }

    if (typeof attrs == "string") {
      addAttrs(element, { class: attrs });
    } else if (Object.keys(attrs).length > 0) {
      addAttrs(element, attrs);
    }
    _iconsCache[cacheKey] = element;
    return returnElement ? element.cloneNode(true) : element.outerHTML;
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
  var refreshPage = function refreshPage(forceGet) {
    var parts = window.location.href.split('#');
    const hasHash = typeof parts[1] !== 'undefined';
    if (!hasHash && !forceGet) {
      window.location.reload();
    } else {
      var href = parts[0];
      // Force reload with a timestamp
      href += href.indexOf('?') === -1 ? '?_=' : '&_=';
      href += new Date().getTime();
      if (hasHash) {
        href += '#' + parts[1];
      }
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

  /**
   * Set element contents and ensure that any inline scripts run properly
   *
   * @param {Element} elm      Target element
   * @param {string}  html     HTML
   * @param {Object}  attrs    Any additional attributes
   * @param {string}  property Target property ('innerHTML', 'outerHTML' or '' for no HTML update)
   */
  function setElementContents(elm, html, attrs = {}, property = 'innerHTML') {
    // Extract any scripts from the HTML and add them separately so that they are executed properly:
    const scripts = [];
    const tmpDiv = document.createElement('div');
    tmpDiv.innerHTML = html;
    tmpDiv.querySelectorAll('script').forEach((el) => {
      const type = el.getAttribute('type');
      if (!type || 'text/javascript' === type) {
        scripts.push(el.cloneNode(true));
        el.remove();
      }
    });

    let newElm = elm;
    if (property === 'innerHTML') {
      elm.innerHTML = tmpDiv.innerHTML;
    } else if (property === 'outerHTML') {
      // Replacing outerHTML will invalidate elm, so find it again by using its next sibling or parent as reference:
      const nextElm = elm.nextElementSibling;
      const parentElm = elm.parentElement ? elm.parentElement : null;
      elm.outerHTML = tmpDiv.innerHTML;
      // Try to find a new reference, leave as is if not possible:
      if (nextElm) {
        newElm = nextElm.previousElementSibling;
      } else if (parentElm) {
        newElm = parentElm.lastElementChild;
      }
    }

    // Set any attributes (N.B. has to be done before scripts in case they rely on the attributes):
    Object.entries(attrs).forEach(([attr, value]) => newElm.setAttribute(attr, value));

    // Append any scripts:
    scripts.forEach((script) => {
      const scriptEl = document.createElement('script');
      scriptEl.innerHTML = script.innerHTML;
      scriptEl.setAttribute('nonce', getCspNonce());
      if (script.src) {
        scriptEl.src = script.src;
      }
      newElm.appendChild(scriptEl);
    });
  }

  /**
   * Set innerHTML and ensure that any inline scripts run properly
   *
   * @param {Element} elm   Target element
   * @param {string}  html  HTML
   * @param {Object}  attrs Any additional attributes
   */
  function setInnerHtml(elm, html, attrs = {}) {
    setElementContents(elm, html, attrs, 'innerHTML');
  }

  /**
   * Set outerHTML and ensure that any inline scripts run properly
   *
   * @param {Element} elm   Target element
   * @param {string}  html  HTML
   * @param {Object}  attrs Any additional attributes
   */
  function setOuterHtml(elm, html, attrs = {}) {
    setElementContents(elm, html, attrs, 'outerHTML');
  }

  var loadHtml = function loadHtml(_element, url, data, success) {
    var element = typeof _element === 'string' ? document.querySelector(_element) : _element.get(0);
    if (!element) {
      return;
    }

    fetch(url, {
      method: 'GET',
      body: data ? JSON.stringify(data) : null
    })
      .then(response => {
        if (!response.ok) {
          throw new Error(translate('error_occurred'));
        }
        return response.text();
      })
      .then(htmlContent => {
        setInnerHtml(element, htmlContent);
        if (typeof success === 'function') {
          success(htmlContent);
        }
      })
      .catch(error => {
        console.error('Request failed:', error);
        setInnerHtml(element, translate('error_occurred'));
        if (typeof success === 'function') {
          success(null, error);
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

  function setupQRCodeLinks(_container) {
    var container = _container || document.body;
    var qrcodeLinks = container.querySelectorAll('a.qrcodeLink');
    qrcodeLinks.forEach((link) => {
      link.addEventListener('click', function toggleQRCode() {
        var holder = this.nextElementSibling;
        if (holder.querySelectorAll('img').length === 0) {
          // Replace the QRCode template with the image:
          const templateEl = holder.querySelector('.qrCodeImgTag');
          if (templateEl) {
            templateEl.parentNode.innerHTML = templateEl.innerHTML;
          }
        }
      });
    });
  }

  /**
   * Initialize result page scripts.
   *
   * @param {string|Element} _container
   */
  var initResultScripts = function initResultScripts(_container) {
    let container = typeof _container === 'string' ? document.querySelector(_container) : _container;
    emit('results-init', {container: container});
    setupQRCodeLinks(container);
    if (typeof loadCovers === 'function') {
      loadCovers();
    }
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
    // handle QR code links
    setupQRCodeLinks();
  };

  function getBootstrapMajorVersion() {
    // Bootstrap 5 defines bootstrap global, while 3 doesn't, so we can use that as
    // an easy way to determine the version:
    return typeof bootstrap === 'undefined' ? 3 : 5;
  }

  /**
   * Disable transition effects and return the previous state
   *
   * @param {Element} elem Element to handle (not used with Bootstrap 3)
   */
  function disableTransitions(elem) {
    if (getBootstrapMajorVersion() === 3) {
      const oldState = $.support.transition;
      $.support.transition = false;
      return oldState;
    }
    const oldState = elem.style.transitionDuration;
    elem.style.transitionDuration = '0s';
    return oldState;
  }

  /**
   * Restore transition effects to the given state
   *
   * @param {Element} elem Element to handle (not used with Bootstrap 3)
   * @param {(string|boolean)} state State from previous call to disableTransitions
   */
  function restoreTransitions(elem, state) {
    if (getBootstrapMajorVersion() === 3) {
      $.support.transition = state;
      return;
    }

    elem.style.transitionDuration = state;
  }

  /**
   * Check if URLSearchParams contains the given key+value
   *
   * URLSearchParams.has(key, value) support is not yet widespread enough to be used
   * (see https://caniuse.com/mdn-api_urlsearchparams_has_value_parameter)
   *
   * @param {URLSearchParams} params URLSearchParams to check
   * @param {string} key Key
   * @param {string} value Value
   *
   * @returns boolean
   */
  function inURLSearchParams(params, key, value) {
    for (const [paramsKey, paramsValue] of params) {
      if (paramsKey === key && paramsValue === value) {
        return true;
      }
    }
    return false;
  }

  /**
   * Delete a key+value from URLSearchParams
   *
   * URLSearchParams.delete(key, value) support is not yet widespread enough to be used
   * (see https://caniuse.com/mdn-api_urlsearchparams_delete_value_parameter)
   *
   * @param {URLSearchParams} params URLSearchParams to delete from
   * @param {string} deleteKey Key to delete
   * @param {string} deleteValue Value to delete
   *
   * @returns URLSearchParams
   */
  function deleteKeyValueFromURLSearchParams(params, deleteKey, deleteValue) {
    const newParams = new URLSearchParams();
    for (const [key, value] of params) {
      if (key !== deleteKey || value !== deleteValue) {
        newParams.append(key, value);
      }
    }
    return newParams;
  }

  /**
   * Delete a set of parameters from URLSearchParams
   *
   * URLSearchParams.delete(key, value) support is not yet widespread enough to be used
   * (see https://caniuse.com/mdn-api_urlsearchparams_delete_value_parameter)
   *
   * @param {URLSearchParams} params URLSearchParams to delete from
   * @param {URLSearchParams} deleteParams URLSearchParams containing all params to delete
   *
   * @returns URLSearchParams
   */
  function deleteParamsFromURLSearchParams(params, deleteParams) {
    const newParams = new URLSearchParams();
    for (const [key, value] of params) {
      if (!inURLSearchParams(deleteParams, key, value)) {
        newParams.append(key, value);
      }
    }
    return newParams;
  }

  //Reveal
  return {
    defaultSearchBackend: defaultSearchBackend,
    path: path,

    addIcons: addIcons,
    addTranslations: addTranslations,
    init: init,
    emit: emit,
    listen: listen,
    unlisten: unlisten,
    evalCallback: evalCallback,
    getCspNonce: getCspNonce,
    icon: icon,
    isPrinting: isPrinting,
    refreshPage: refreshPage,
    register: register,
    setCspNonce: setCspNonce,
    spinner: spinner,
    loadHtml: loadHtml,
    loading: loading,
    translate: translate,
    updateCspNonce: updateCspNonce,
    getCurrentSearchId: getCurrentSearchId,
    setCurrentSearchId: setCurrentSearchId,
    initResultScripts: initResultScripts,
    setupQRCodeLinks: setupQRCodeLinks,
    setInnerHtml: setInnerHtml,
    setOuterHtml: setOuterHtml,
    setElementContents: setElementContents,
    getBootstrapMajorVersion: getBootstrapMajorVersion,
    disableTransitions: disableTransitions,
    restoreTransitions: restoreTransitions,
    inURLSearchParams: inURLSearchParams,
    deleteKeyValueFromURLSearchParams: deleteKeyValueFromURLSearchParams,
    deleteParamsFromURLSearchParams: deleteParamsFromURLSearchParams
  };
})();

/* --- GLOBAL FUNCTIONS --- */
function htmlEncode(value) {
  return String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
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

function extractClassParams(el) {
  var str = el.className;
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
    if (name.endsWith('[]')) {
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

/**
 * Phone number validation
 * @param {String} numID Phone number field ID
 * @param {String} regionCode Region code
 * @deprecated See validation.js for replacement
 */
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
    phoneInput.setCustomValidity(valid);
    return false;
  } else {
    phoneInput.setCustomValidity('');
  }
}

// Setup captchas after Google script loads
function recaptchaOnLoad() {
  if (typeof grecaptcha !== 'undefined') {
    var captchas = document.querySelectorAll('.g-recaptcha:empty');
    for (var i = 0; i < captchas.length; i++) {
      var captchaElement = captchas[i];
      var captchaData = captchaElement.dataset;
      var captchaId = grecaptcha.render(captchaElement, captchaData);
      captchaElement.dataset.captchaId = captchaId;
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
  let numberOfSelected = VuFind.listItemSelection.getAllSelected(event.target).length;
  if (numberOfSelected === 0) {
    VuFind.lightbox.alert(VuFind.translate('bulk_noitems_advice'), 'danger');
    return false;
  }
  // originalEvent check can be removed and event.submitter can directly used once jQuery is no longer used in the lightbox
  const submitter = event.originalEvent.submitter !== undefined && event.originalEvent.submitter !== null
    ? event.originalEvent.submitter
    : (event.submitter !== undefined && event.submitter !== null ? event.submitter : null);

  if (submitter !== null) {
    let limit = submitter.dataset.itemLimit;
    if (numberOfSelected > limit) {
      VuFind.lightbox.alert(
        VuFind.translate('bulk_limit_exceeded', {'%%count%%': numberOfSelected, '%%limit%%': limit}),
        'danger'
      );
      return false;
    }
  }

  for (var i in data) {
    if ('print' === data[i].name) {
      return true;
    }
  }
}

// Ready functions
function setupOffcanvas() {
  const sidebar = document.querySelector('.sidebar');
  const body = document.body;

  if (sidebar && body.classList.contains("vufind-offcanvas")) {
    const offcanvasToggle = document.querySelectorAll('[data-toggle="vufind-offcanvas"]');

    offcanvasToggle.forEach((element) => {
      element.addEventListener("click", function offcanvasClick(e) {
        e.preventDefault();
        body.classList.toggle('active');
      });
    });
  }
}

function unwrapJQuery(node) {
  return node instanceof Node ? node : node[0];
}

function setupJumpMenus(_container) {
  var container = unwrapJQuery(_container || document.body);
  var selects = container.querySelectorAll('select.jumpMenu');
  selects.forEach((select) => {
    select.addEventListener('change', function jumpMenu() {
      // Check if jumpMenu is still enabled (search.js may have disabled it):
      if (select.classList.contains('jumpMenu')) {
        select.parentElement.submit();
      }
    });
  });
}

function setupMultiILSLoginFields(loginMethods, idPrefix) {
  var searchPrefix = idPrefix ? '#' + idPrefix : '#';
  $(searchPrefix + 'target').on("change", function onChangeLoginTarget() {
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
  }).trigger("change");
}

document.addEventListener('DOMContentLoaded', () => {
  VuFind.emit("ready");
  // Start up all of our submodules
  VuFind.init();
  // Off canvas
  setupOffcanvas();

  // support "jump menu" dropdown boxes
  setupJumpMenus();

  // Print
  var url = window.location.href;
  if (url.indexOf('?print=') !== -1 || url.indexOf('&print=') !== -1) {
    var printStylesheets = document.querySelectorAll('link[media="print"]');
    printStylesheets.forEach((stylesheet) => {
      stylesheet.media = 'all';
    });
  }
});
