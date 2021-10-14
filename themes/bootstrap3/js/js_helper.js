/* exported jsHelper */
var jsHelper = (function jsHelper() {

  /**
   * Create a html element
   * 
   * @param {String} tag 
   * @param {String} className 
   * @param {Object} attributes 
   */
  function createElement(tag, className, attributes) {
    var element = document.createElement(tag);
    element.className = className;
    if (attributes) {
      for (var attr in attributes) {
        if (attributes.hasOwnProperty(attr)) {
          element.setAttribute(attr, attributes[attr]);
        }
      }
    }
    return element;
  }

  /**
   * Find next sibling with selector
   * 
   * @param {HTMLElement} el
   * @param {String} selector 
   */
  function getNextElementSibling(el, selector) {
    var sibling = el.nextElementSibling;
    while (sibling) {
      if (sibling.matches(selector)) {
        return sibling;
      }
      sibling = sibling.nextElementSibling;
    }
    return undefined;
  }

  /**
   * Find previous sibling with selector
   * 
   * @param {HTMLElement} el
   * @param {String} selector 
   */
  function getPreviousElementSibling(el, selector) {
    var sibling = el.previousElementSibling;
    while (sibling) {
      if (sibling.matches(selector)) {
        return sibling;
      }
      sibling = sibling.previousElementSibling;
    }
    return undefined;
  }

  /**
   * Binds an event to the element and given selector checks if the event can be ran.
   * Useful for binding events to dynamically created buttons etc.
   * 
   * @param {HTMLElement} el
   * @param {String} selector
   * @param {String} eventName
   * @param {Array} path 
   */
  function addDynamicListener(el, selector, eventName, handler) {
    el.addEventListener(eventName, function checkEvent(e) {
      // loop parent nodes from the target to the delegation node
      var target = e.target;
      while (target) {
        if (target.matches(selector)) {
          handler.call(target, e);
          break;
        }
        target = target.parentNode;
      }
    }, false);
  }

  /**
   * Return all parents of the element until selector is satisfied.
   * 
   * @param {HTMLElement} el 
   * @param {String} selector
   */
  function getParentsUntil(el, selector) {
    var parents = [];
    var target = el.parentNode;
    while (target) {
      parents.push(target);
      if (target.matches(selector)) {
        return parents;
      }
      target = target.parentNode;
    }
    return [];
  }

  /**
   * Find parent which matches the selector.
   * 
   * @param {HTMLElement} el 
   * @param {String} selector
   */
  function findParent(el, selector) {
    var target = el.parentNode;
    while (target) {
      if (target.matches(selector)) {
        return target;
      }
      target = target.parentNode;
    }
    return undefined;
  }

  /**
   * Check if the element is the selector.
   * 
   * @param {object} el 
   * @param {String} selector 
   */
  function is(el, selector) {
    return (el.matches || el.matchesSelector || el.msMatchesSelector || el.mozMatchesSelector || el.webkitMatchesSelector || el.oMatchesSelector).call(el, selector);
  }

  /**
   * Get the current coordinates of the element
   * 
   * @param {HTMLElement} el 
   */
  function offset(el) {
    var rect = el.getBoundingClientRect();

    return {
      top: rect.top + document.body.scrollTop,
      left: rect.left + document.body.scrollLeft
    };
  }

  /**
   * Return the outer height of element with margin.
   * 
   * @param {HTMLElement} el 
   */
  function outerHeightWithMargin(el) {
    var height = el.offsetHeight;
    var style = getComputedStyle(el);
  
    height += parseInt(style.marginTop) + parseInt(style.marginBottom);
    return height;
  }

  /**
   * Return the outer width of element with margin.
   * 
   * @param {HTMLElement} el 
   */
  function outerWidthWithMargin(el) {
    var width = el.offsetWidth;
    var style = getComputedStyle(el);

    width += parseInt(style.marginLeft) + parseInt(style.marginRight);
    return width;
  }

  /**
   * Add a listener for when DOMContent has been loaded.
   * If the DOMContent has been loaded, trigger immediately.
   * 
   * @param {Function} fn 
   */
  function ready(fn) {
    if (document.readyState !== 'loading') {
      fn();
    } else {
      document.addEventListener('DOMContentLoaded', fn);
    }
  }
  
  /**
   * Bind a custom event to an element.
   * 
   * @param {HTMLElement} el
   * @param {String} eventName 
   * @param {Object} data 
   */
  function bindCustomEvent(el, eventName, data) {
    var event;
    if (window.CustomEvent && typeof window.CustomEvent === 'function') {
      event = new CustomEvent(eventName, {detail: data});
    } else {
      event = document.createEvent('CustomEvent');
      event.initCustomEvent(eventName, true, true, data);
    }

    el.dispatchEvent(event);
  }

  /**
   * Get position of elements as top and left object
   * 
   * @param {HTMLElement} el 
   */
  function getPosition(el) {
    var style = window.getComputedStyle(el);
    var marginTop = style.getPropertyValue('margin-top');
    var marginLeft = style.getPropertyValue('margin-left');

    return {
      top: el.offsetTop - parseFloat(marginTop),
      left: el.offsetLeft - parseFloat(marginLeft)
    };
  }

  /**
   * Keep the given index inside min and max values. If cycle is true
   * then start from min if it is over max and vice versa.
   * 
   * @param {Integer} index 
   * @param {Integer} min 
   * @param {Integer} max 
   * @param {boolean} cycle 
   */
  function keepIndexInBounds(index, min, max, cycle) {
    if (cycle) {
      if (index > max) {
        return cycle ? min : max;
      }
      if (index < min) {
        return cycle ? max : min;
      }
    }
    return index;
  }

  var my = {
    is: is,
    offset: offset,
    outerHeightWithMargin: outerHeightWithMargin,
    outerWidthWithMargin: outerWidthWithMargin,
    ready: ready,
    bindCustomEvent: bindCustomEvent,
    createElement: createElement,
    addDynamicListener: addDynamicListener,
    getPosition: getPosition,
    getNextElementSibling: getNextElementSibling,
    getPreviousElementSibling: getPreviousElementSibling,
    findParent: findParent,
    keepIndexInBounds: keepIndexInBounds,
    getParentsUntil: getParentsUntil
  };

  return my;
})();
