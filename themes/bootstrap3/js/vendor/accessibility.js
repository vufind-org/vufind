(function (global, factory) {
  if (typeof define === "function" && define.amd) {
    define(['exports'], factory);
  } else if (typeof exports !== "undefined") {
    factory(exports);
  } else {
    var mod = {
      exports: {}
    };
    factory(mod.exports);
    global.accessibilityjs = mod.exports;
  }
})(this, function (exports) {
  'use strict';

  Object.defineProperty(exports, "__esModule", {
    value: true
  });
  exports.scanForProblems = scanForProblems;
  function scanForProblems(context, logError, options) {
    var _iteratorNormalCompletion = true;
    var _didIteratorError = false;
    var _iteratorError = undefined;

    try {
      for (var _iterator = context.querySelectorAll('img')[Symbol.iterator](), _step; !(_iteratorNormalCompletion = (_step = _iterator.next()).done); _iteratorNormalCompletion = true) {
        var img = _step.value;

        if (!img.hasAttribute('alt')) {
          logError(new ImageWithoutAltAttributeError(img));
        }
      }
    } catch (err) {
      _didIteratorError = true;
      _iteratorError = err;
    } finally {
      try {
        if (!_iteratorNormalCompletion && _iterator.return) {
          _iterator.return();
        }
      } finally {
        if (_didIteratorError) {
          throw _iteratorError;
        }
      }
    }

    var _iteratorNormalCompletion2 = true;
    var _didIteratorError2 = false;
    var _iteratorError2 = undefined;

    try {
      for (var _iterator2 = context.querySelectorAll('a')[Symbol.iterator](), _step2; !(_iteratorNormalCompletion2 = (_step2 = _iterator2.next()).done); _iteratorNormalCompletion2 = true) {
        var a = _step2.value;

        if (a.hasAttribute('name') || elementIsHidden(a)) {
          continue;
        }
        if (a.getAttribute('href') == null && a.getAttribute('role') !== 'button') {
          logError(new LinkWithoutLabelOrRoleError(a));
        } else if (!accessibleText(a)) {
          logError(new ElementWithoutLabelError(a));
        }
      }
    } catch (err) {
      _didIteratorError2 = true;
      _iteratorError2 = err;
    } finally {
      try {
        if (!_iteratorNormalCompletion2 && _iterator2.return) {
          _iterator2.return();
        }
      } finally {
        if (_didIteratorError2) {
          throw _iteratorError2;
        }
      }
    }

    var _iteratorNormalCompletion3 = true;
    var _didIteratorError3 = false;
    var _iteratorError3 = undefined;

    try {
      for (var _iterator3 = context.querySelectorAll('button')[Symbol.iterator](), _step3; !(_iteratorNormalCompletion3 = (_step3 = _iterator3.next()).done); _iteratorNormalCompletion3 = true) {
        var button = _step3.value;

        if (!elementIsHidden(button) && !accessibleText(button)) {
          logError(new ButtonWithoutLabelError(button));
        }
      }
    } catch (err) {
      _didIteratorError3 = true;
      _iteratorError3 = err;
    } finally {
      try {
        if (!_iteratorNormalCompletion3 && _iterator3.return) {
          _iterator3.return();
        }
      } finally {
        if (_didIteratorError3) {
          throw _iteratorError3;
        }
      }
    }

    var _iteratorNormalCompletion4 = true;
    var _didIteratorError4 = false;
    var _iteratorError4 = undefined;

    try {
      for (var _iterator4 = context.querySelectorAll('label')[Symbol.iterator](), _step4; !(_iteratorNormalCompletion4 = (_step4 = _iterator4.next()).done); _iteratorNormalCompletion4 = true) {
        var label = _step4.value;

        // In case label.control isn't supported by browser, find the control manually (IE)
        var control = label.control || document.getElementById(label.getAttribute('for')) || label.querySelector('input');

        if (!control && !elementIsHidden(label)) {
          logError(new LabelMissingControlError(label));
        }
      }
    } catch (err) {
      _didIteratorError4 = true;
      _iteratorError4 = err;
    } finally {
      try {
        if (!_iteratorNormalCompletion4 && _iterator4.return) {
          _iterator4.return();
        }
      } finally {
        if (_didIteratorError4) {
          throw _iteratorError4;
        }
      }
    }

    var _iteratorNormalCompletion5 = true;
    var _didIteratorError5 = false;
    var _iteratorError5 = undefined;

    try {
      for (var _iterator5 = context.querySelectorAll('input[type=text], input[type=url], input[type=search], input[type=number], textarea')[Symbol.iterator](), _step5; !(_iteratorNormalCompletion5 = (_step5 = _iterator5.next()).done); _iteratorNormalCompletion5 = true) {
        var input = _step5.value;

        // In case input.labels isn't supported by browser, find the control manually (IE)
        var inputLabel = input.labels ? input.labels[0] : input.closest('label') || document.querySelector('label[for="' + input.id + '"]');
        if (!inputLabel && !elementIsHidden(input) && !input.hasAttribute('aria-label')) {
          logError(new InputMissingLabelError(input));
        }
      }
    } catch (err) {
      _didIteratorError5 = true;
      _iteratorError5 = err;
    } finally {
      try {
        if (!_iteratorNormalCompletion5 && _iterator5.return) {
          _iterator5.return();
        }
      } finally {
        if (_didIteratorError5) {
          throw _iteratorError5;
        }
      }
    }

    if (options && options['ariaPairs']) {
      for (var selector in options['ariaPairs']) {
        var ARIAAttrsRequired = options['ariaPairs'][selector];
        var _iteratorNormalCompletion6 = true;
        var _didIteratorError6 = false;
        var _iteratorError6 = undefined;

        try {
          for (var _iterator6 = context.querySelectorAll(selector)[Symbol.iterator](), _step6; !(_iteratorNormalCompletion6 = (_step6 = _iterator6.next()).done); _iteratorNormalCompletion6 = true) {
            var target = _step6.value;

            var missingAttrs = [];

            var _iteratorNormalCompletion7 = true;
            var _didIteratorError7 = false;
            var _iteratorError7 = undefined;

            try {
              for (var _iterator7 = ARIAAttrsRequired[Symbol.iterator](), _step7; !(_iteratorNormalCompletion7 = (_step7 = _iterator7.next()).done); _iteratorNormalCompletion7 = true) {
                var attr = _step7.value;

                if (!target.hasAttribute(attr)) missingAttrs.push(attr);
              }
            } catch (err) {
              _didIteratorError7 = true;
              _iteratorError7 = err;
            } finally {
              try {
                if (!_iteratorNormalCompletion7 && _iterator7.return) {
                  _iterator7.return();
                }
              } finally {
                if (_didIteratorError7) {
                  throw _iteratorError7;
                }
              }
            }

            if (missingAttrs.length > 0) {
              logError(new ARIAAttributeMissingError(target, missingAttrs.join(', ')));
            }
          }
        } catch (err) {
          _didIteratorError6 = true;
          _iteratorError6 = err;
        } finally {
          try {
            if (!_iteratorNormalCompletion6 && _iterator6.return) {
              _iterator6.return();
            }
          } finally {
            if (_didIteratorError6) {
              throw _iteratorError6;
            }
          }
        }
      }
    }
  }

  function errorSubclass(fn) {
    fn.prototype = new Error();
    fn.prototype.constructor = fn;
  }

  function ImageWithoutAltAttributeError(element) {
    this.name = 'ImageWithoutAltAttributeError';
    this.stack = new Error().stack;
    this.element = element;
    this.message = 'Missing alt attribute on ' + inspect(element);
  }
  errorSubclass(ImageWithoutAltAttributeError);

  function ElementWithoutLabelError(element) {
    this.name = 'ElementWithoutLabelError';
    this.stack = new Error().stack;
    this.element = element;
    this.message = 'Missing text, title, or aria-label attribute on ' + inspect(element);
  }
  errorSubclass(ElementWithoutLabelError);

  function LinkWithoutLabelOrRoleError(element) {
    this.name = 'LinkWithoutLabelOrRoleError';
    this.stack = new Error().stack;
    this.element = element;
    this.message = 'Missing href or role=button on ' + inspect(element);
  }
  errorSubclass(LinkWithoutLabelOrRoleError);

  function LabelMissingControlError(element) {
    this.name = 'LabelMissingControlError';
    this.stack = new Error().stack;
    this.element = element;
    this.message = 'Label missing control on ' + inspect(element);
  }
  errorSubclass(LabelMissingControlError);

  function InputMissingLabelError(element) {
    this.name = 'InputMissingLabelError';
    this.stack = new Error().stack;
    this.element = element;
    this.message = 'Missing label for or aria-label attribute on ' + inspect(element);
  }
  errorSubclass(InputMissingLabelError);

  function ButtonWithoutLabelError(element) {
    this.name = 'ButtonWithoutLabelError';
    this.stack = new Error().stack;
    this.element = element;
    this.message = 'Missing text or aria-label attribute on ' + inspect(element);
  }
  errorSubclass(ButtonWithoutLabelError);

  function ARIAAttributeMissingError(element, attr) {
    this.name = 'ARIAAttributeMissingError';
    this.stack = new Error().stack;
    this.element = element;
    this.message = 'Missing ' + attr + ' attribute on ' + inspect(element);
  }
  errorSubclass(ARIAAttributeMissingError);

  function elementIsHidden(element) {
    return element.closest('[aria-hidden="true"], [hidden], [style*="display: none"]') != null;
  }

  function isText(value) {
    return typeof value === 'string' && !!value.trim();
  }

  // Public: Check if an element has text visible by sight or screen reader.
  //
  // Examples
  //
  //   <img alt="github" src="github.png">
  //   # => true
  //
  //   <span aria-label="Open"></span>
  //   # => true
  //
  //   <button></button>
  //   # => false
  //
  // Returns String text.
  function accessibleText(node) {
    switch (node.nodeType) {
      case Node.ELEMENT_NODE:
        if (isText(node.getAttribute('alt')) || isText(node.getAttribute('aria-label')) || isText(node.getAttribute('title'))) return true;
        for (var i = 0; i < node.childNodes.length; i++) {
          if (accessibleText(node.childNodes[i])) return true;
        }
        break;
      case Node.TEXT_NODE:
        return isText(node.data);
    }
  }

  function inspect(element) {
    var tagHTML = element.outerHTML;
    if (element.innerHTML) tagHTML = tagHTML.replace(element.innerHTML, '...');

    var parents = [];
    while (element) {
      if (element.nodeName === 'BODY') break;
      parents.push(selectors(element));
      // Stop traversing when we hit an ID
      if (element.id) break;
      element = element.parentNode;
    }
    return '"' + parents.reverse().join(' > ') + '". \n\n' + tagHTML;
  }

  function selectors(element) {
    var components = [element.nodeName.toLowerCase()];
    if (element.id) components.push('#' + element.id);
    if (element.classList) {
      var _iteratorNormalCompletion8 = true;
      var _didIteratorError8 = false;
      var _iteratorError8 = undefined;

      try {
        for (var _iterator8 = element.classList[Symbol.iterator](), _step8; !(_iteratorNormalCompletion8 = (_step8 = _iterator8.next()).done); _iteratorNormalCompletion8 = true) {
          var name = _step8.value;

          if (name.match(/^js-/)) components.push('.' + name);
        }
      } catch (err) {
        _didIteratorError8 = true;
        _iteratorError8 = err;
      } finally {
        try {
          if (!_iteratorNormalCompletion8 && _iterator8.return) {
            _iterator8.return();
          }
        } finally {
          if (_didIteratorError8) {
            throw _iteratorError8;
          }
        }
      }
    }

    return components.join('');
  }
});
