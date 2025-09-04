/*global grecaptcha, loadCovers */
/*exported VuFind, bulkFormHandler, deparam, escapeHtmlAttr, extractClassParams, getFocusableNodes, getUrlRoot, htmlEncode, recaptchaOnLoad, resetCaptcha, setupMultiILSLoginFields, unwrapJQuery */

var VuFind = (function VuFind() {
  var defaultSearchBackend = null;
  var path = null;
  var _initialized = false;
  var _submodules = [];
  var _cspNonce = '';
  var _searchId = null;
  var _theme = null;

  var _icons = {};
  var _translations = {};

  var _elementBase;
  var _iconsCache = {};

  /**
   * Element creator function
   * @param {string}         tagName   Element tag name
   * @param {string}         className Element class
   * @param {object}         attrs     Additional attrs as key => value
   * @param {Array|NodeList} children  Child nodes to be added
   * @returns {Element} Created Element
   */
  function el(tagName, className = '', attrs = {}, children = []) {
    const newElement = document.createElement(tagName);
    newElement.className = className;
    for (const [key, value] of Object.entries(attrs)) {
      newElement.setAttribute(key, value);
    }
    newElement.append(...children);
    return newElement;
  }

  // Event controls

  let listeners = {};
  /**
   * Remove a listener function from a specific event.
   * @param {string}   event The name of the event.
   * @param {Function} fn    The function to remove.
   * @returns {void}
   */
  function unlisten(event, fn) {
    if (typeof listeners[event] === "undefined") {
      return;
    }

    const index = listeners[event].indexOf(fn);

    if (index > -1) {
      listeners[event].splice(index, 1);
    }
  }
  
  /**
   * Add a function to be called when an event is emitted.
   * @param {string}   event          The name of the event.
   * @param {Function} fn             The function to call when the event is emitted.
   * @param {object}   [options]      Options for the listener.
   * @param {boolean}  [options.once] If true, the listener will be removed after being called once (default = false).
   * @returns {Function} A function to remove the listener.
   */
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
  
  /**
   * Broadcast an event, passing arguments to all registered listeners.
   * @param {string} event The name of the event.
   * @param {...*}   args  Arguments to pass to the listeners.
   * @returns {void}
   */
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
   * Evaluates a callback function
   * @param {string} callback The name of the callback function.
   * @param {*}      event    The event object.
   * @param {*}      data     Additional data.
   * @returns {*} The result of the callback function, or null if not found.
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
   * @param {string}  name            Name of the icon to create
   * @param {object}  attrs           Object containing attributes, key is the attribute of an HTMLElement,
   *                                  value is the values to add for the attribute.
   * @param {boolean} [returnElement] Should the function return an HTMLElement (default = false).
   * @returns {string|HTMLElement} Return the icon
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

    
    /**
     * Adds attributes to an HTML element.
     * @param {HTMLElement} _element The HTML element to which attributes will be added.
     * @param {object}      _attrs   An object of key-value pairs representing the attributes to add (default = {}).
     */
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
   * Return a spinner html element
   * @param {string} extraClass Extra class string to add for spinner wrapper (default = '')
   * @returns {HTMLSpanElement} The spinner HTML element.
   */
  var spinnerElement = function spinnerElement(extraClass = '') {
    const spinnerIcon = icon('spinner', {}, true);
    const spinnerSpan = el('span', `loading-spinner ${extraClass}`.trim());
    spinnerSpan.append(spinnerIcon);
    return spinnerSpan;
  };

  /**
   * Return a spinner html element with loading text
   * @param {string|null} [text]       Translation key to append inside span wrapper; loading_ellipsis is used if null (default = null)
   * @param {string}      [extraClass] Extra class string to add for spinner wrapper (default = '')
   * @returns {HTMLSpanElement} The spinner HTML element with text.
   */
  var loadingElement = function loadingElement(text = null, extraClass = '') {
    const spinnerSpan = spinnerElement(extraClass);
    const translated = translate(text === null ? 'loading_ellipsis' : text);
    const spinnerText = document.createTextNode(` ${translated}`);
    spinnerSpan.appendChild(spinnerText);
    return spinnerSpan;
  };

  /**
   * Return an overlay html element that contains a spinner with loading text
   * @param {string|null} [text]       Translation key to append inside span wrapper; loading_ellipsis is used if null (default = null)
   * @param {string}      [extraClass] Extra class string to add for spinner wrapper (default = '')
   * @returns {HTMLDivElement} The loading overlay element.
   */
  function loadingOverlay(text = null, extraClass = '') {
    const overlay = document.createElement('div');
    overlay.classList = 'loading-overlay';
    overlay.setAttribute('aria-live', 'polite');
    overlay.setAttribute('role', 'status');
    overlay.append(loadingElement(text, extraClass));
    return overlay;
  }

  /**
   * Reload the page without causing trouble with POST parameters while keeping hash
   * @param {boolean} forceGet If true, forces a GET request with a cache-busting timestamp.
   * @returns {void}
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
   * @param {Element} elm      Target element
   * @param {string}  html     HTML
   * @param {object}  attrs    Any additional attributes (does not work with outerHtml as property)
   * @param {string}  property Target property ('innerHTML', 'outerHTML' or '' for no HTML update) (default = 'innerHTML')
   */
  function setElementContents(elm, html, attrs = {}, property = 'innerHTML') {
    const tmpDiv = document.createElement('div');
    tmpDiv.innerHTML = html;
    const scripts = [];
    // Cloning scripts wont work as they pass internal executed state so save them for later
    tmpDiv.querySelectorAll('script').forEach(script => {
      const type = script.getAttribute('type');
      if (!type || 'text/javascript' === type) {
        scripts.push(script.cloneNode(true));
        script.remove();
      }
    });

    let scriptElement = elm;

    if (property === 'innerHTML') {
      elm.replaceChildren(...tmpDiv.childNodes);
    } else if (property === 'outerHTML') {
      scriptElement = elm.parentNode;
      elm.replaceWith(...tmpDiv.childNodes);
    }

    if (property !== 'outerHTML') {
      // Set any attributes (N.B. has to be done before scripts in case they rely on the attributes):
      Object.entries(attrs).forEach(([attr, value]) => elm.setAttribute(attr, value));
    } else if (Object.keys(attrs).length > 0) {
      console.error("Incompatible parameter 'attrs' " + JSON.stringify(attrs) + " passed to setElementContents() while 'property' is 'outerHTML'.");
    }

    // Append any scripts:
    scripts.forEach(script => {
      const newScript = document.createElement('script');
      newScript.append(...script.childNodes);
      if (script.src) {
        newScript.src = script.src;
      }
      newScript.setAttribute('nonce', getCspNonce());
      scriptElement.appendChild(newScript);
    });
  }

  /**
   * Set innerHTML and ensure that any inline scripts run properly
   * @param {Element} elm   Target element
   * @param {string}  html  HTML
   * @param {object}  attrs Any additional attributes
   */
  function setInnerHtml(elm, html, attrs = {}) {
    setElementContents(elm, html, attrs, 'innerHTML');
  }

  /**
   * Set outerHTML and ensure that any inline scripts run properly
   * @param {Element} elm   Target element
   * @param {string}  html  HTML
   */
  function setOuterHtml(elm, html) {
    setElementContents(elm, html, {}, 'outerHTML');
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

  var getTheme = function getTheme() {
    return _theme;
  };

  var setTheme = function setTheme(theme) {
    _theme = theme;
  };

  /**
   * Sets up click handlers for QR code links to display the QR code image on click.
   * @param {Element} [_container] The container element to search for links (default = document.body).
   * @returns {void}
   */
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
            setInnerHtml(templateEl.parentElement, templateEl.innerHTML);
          }
        }
      });
    });
  }

  /**
   * Initialize result page scripts.
   * @param {string|Element} _container The container element to initialize scripts on.
   * @returns {void}
   */
  var initResultScripts = function initResultScripts(_container) {
    let container = typeof _container === 'string' ? document.querySelector(_container) : _container;
    emit('results-init', {container: container});
    setupQRCodeLinks(container);
    if (typeof loadCovers === 'function') {
      loadCovers();
    }
  };

  /**
   * Initialize all registered submodules and global handlers.
   * @returns {void}
   */
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

  /**
   * Disable transition effects and return the previous state
   * @param {Element} elem Element to handle
   * @returns {string} The original `transitionDuration` style.
   */
  function disableTransitions(elem) {
    const oldState = elem.style.transitionDuration;
    elem.style.transitionDuration = '0s';
    return oldState;
  }

  /**
   * Restore transition effects to the given state
   * @param {Element}        elem  Element to handle
   * @param {string|boolean} state State from previous call to disableTransitions
   */
  function restoreTransitions(elem, state) {
    elem.style.transitionDuration = state;
  }

  /**
   * Check if URLSearchParams contains the given key-value pair
   * URLSearchParams.has(key, value) support is not yet widespread enough to be used
   * (see https://caniuse.com/mdn-api_urlsearchparams_has_value_parameter)
   * @param {URLSearchParams} params URLSearchParams to check
   * @param {string} key The key to look for
   * @param {string} value The value to match
   * @returns {boolean} Return true if the key-value pair exists
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
   * URLSearchParams.delete(key, value) support is not yet widespread enough to be used
   * (see https://caniuse.com/mdn-api_urlsearchparams_delete_value_parameter)
   * @param {URLSearchParams} params URLSearchParams to delete from
   * @param {string} deleteKey Key to delete
   * @param {string} deleteValue Value to delete
   * @returns {URLSearchParams} A new URLSearchParams object without the specified key-value pair.
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
   * URLSearchParams.delete(key, value) support is not yet widespread enough to be used
   * (see https://caniuse.com/mdn-api_urlsearchparams_delete_value_parameter)
   * @param {URLSearchParams} params URLSearchParams to delete from
   * @param {URLSearchParams} deleteParams URLSearchParams containing all params to delete
   * @returns {URLSearchParams} A new URLSearchParams object with the specified pairs removed.
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

  /**
   * MultiILS: Display password recovery link for enabled login targets
   * @param {object} links Recovery links
   * @param {string|null} idPrefix Optional prefix for the ID selectors.
   * @returns {void}
   */
  function displayILSPasswordRecoveryLink(links, idPrefix) {
    const searchPrefix = idPrefix ? '#' + idPrefix : '#';
    const targetSelector = document.querySelector(searchPrefix + 'target');
    const recoveryLink = document.querySelector('#recovery_link');
    if (targetSelector && recoveryLink) {
      const changeListener = () => {
        const target = targetSelector.value;
        if (links[target]) {
          recoveryLink.setAttribute('href', links[target]);
          recoveryLink.classList.remove('hidden');
        } else {
          recoveryLink.classList.add('hidden');
        }
      };
      targetSelector.addEventListener('change', changeListener);
      changeListener();
    }
  }

  //Reveal
  return {
    defaultSearchBackend: defaultSearchBackend,
    path: path,

    addIcons: addIcons,
    addTranslations: addTranslations,
    init: init,
    el: el,
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
    spinnerElement: spinnerElement,
    loadHtml: loadHtml,
    loading: loading,
    loadingElement: loadingElement,
    loadingOverlay,
    translate: translate,
    updateCspNonce: updateCspNonce,
    getCurrentSearchId: getCurrentSearchId,
    setCurrentSearchId: setCurrentSearchId,
    initResultScripts: initResultScripts,
    setupQRCodeLinks: setupQRCodeLinks,
    setInnerHtml: setInnerHtml,
    setOuterHtml: setOuterHtml,
    setElementContents: setElementContents,
    disableTransitions: disableTransitions,
    restoreTransitions: restoreTransitions,
    inURLSearchParams: inURLSearchParams,
    deleteKeyValueFromURLSearchParams: deleteKeyValueFromURLSearchParams,
    deleteParamsFromURLSearchParams: deleteParamsFromURLSearchParams,
    getTheme,
    setTheme,
    displayILSPasswordRecoveryLink
  };
})();

/* --- GLOBAL FUNCTIONS --- */

/**
 * HTML-encode a string.
 * @param {string} value The string to encode.
 * @returns {string} The encoded string.
 */
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
/**
 * Get all focusable nodes within a given container element.
 * @param {Element} container The container to search within.
 * @returns {Array<Element>} An array of focusable elements.
 */
function getFocusableNodes(container) {
  const nodes = container.querySelectorAll(FOCUSABLE_ELEMENTS);
  return Array.from(nodes);
}

/**
 * Escape a string for use as an HTML attribute.
 * Adapted from Laminas.
 * Source: https://github.com/laminas/laminas-escaper/blob/2.13.x/src/Escaper.php
 * @param  {string} str The string to escape.
 * @returns {string} The escaped string.
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

/**
 * Extract key-value parameters from an element's class string.
 * @param {Element} el The element to extract parameters from.
 * @returns {object} An object of key-value pairs.
 */
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

/**
 * Parse a URL's query string into an object.
 * @param {string} url The URL string.
 * @returns {object} An object representing the query string parameters.
 */
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

/**
 * Extract the root URL path from a given URL string.
 * @param {string} url The URL string.
 * @returns {string|null} The root URL path or null if not found.
 */
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
 * Initialize Google reCAPTCHA widgets on a page.
 * @param {Document|Element} [_context] The context to search for reCAPTCHA elements (default = document).
 * @returns {void}
 */
function recaptchaOnLoad(_context) {
  if (typeof grecaptcha !== 'undefined') {
    const context = typeof _context === "undefined" ? document : _context;
    context.querySelectorAll('.g-recaptcha:empty').forEach((captchaElement) => {
      captchaElement.dataset.captchaId = grecaptcha.render(captchaElement, captchaElement.dataset);
    });
  }
}

/**
 * Reset a reCAPTCHA widget within a given target element.
 * @param {Element} target The element containing the reCAPTCHA widget.
 * @returns {void}
 */
function resetCaptcha(target) {
  if (typeof grecaptcha !== 'undefined') {
    const captcha = target.querySelector('.g-recaptcha');
    if (captcha) {
      grecaptcha.reset(captcha.dataset.captchaId);
    }
  }
}

/**
 * Handle a bulk form submission.
 * @param {Event}         event The form submission event.
 * @param {Array<object>} data  The form data.
 * @returns {boolean|void} Return false to prevent form submission.
 */
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

/**
 * Set up click handlers for off-canvas sidebar toggles.
 * @returns {void}
 */
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

/**
 * Unwrap a jQuery object to return the native DOM node.
 * @param {Node|jQuery} node The node or jQuery object to unwrap.
 * @returns {Node} The native DOM node.
 */
function unwrapJQuery(node) {
  return node instanceof Node ? node : node[0];
}

/**
 * Set up change event handlers for "jump menu" dropdowns.
 * @param {Element} [_container] The container to search for jump menus (default = document.body).
 * @returns {void}
 */
function setupJumpMenus(_container) {
  var container = _container || document.body;
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

/**
 * Set up dynamic login fields for multi-ILS login.
 * @param {object} loginMethods An object mapping login targets to their method.
 * @param {string} idPrefix     An optional prefix for ID selectors.
 * @returns {void}
 */
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
  if (VuFind.isPrinting()) {
    var printStylesheets = document.querySelectorAll('link[media="print"]');
    printStylesheets.forEach((stylesheet) => {
      stylesheet.media = 'all';
    });
  }
});
