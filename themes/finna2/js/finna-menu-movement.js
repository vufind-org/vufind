/* global finna */

/**
 * Creates an arrow key movement to given menu element, typically an ul.
 * 
 * @param {jQuery} element 
 */
function FinnaMovement (element) {
  var _ = this;
  _.menuRootElement = $(element);
  _.menuElements = [];
  _.isHorizontal = _.menuRootElement.hasClass('horizontal');
  _.setChildData();
  _.keys = {
    up: 38,
    down: 40,
    left: 37,
    right: 39,
    space: 32
  };
  _.offset = 0;
  _.offsetCache = -1;
  _.childOffset = -1;
  _.setEvents();
}

/**
 * Set events related to the movement component
 */
FinnaMovement.prototype.setEvents = function setEvents() {
  var _ = this;
  _.menuRootElement.on('reindex.finna', function reIndex() {
    _.setChildData();
    _.setFocusTo();
  });
  _.menuRootElement.on('focusout', function setFocusOut(e) {
    if (!$.contains(_.menuRootElement[0], e.relatedTarget)) {
      _.reset();
    }
  });
  _.menuRootElement.on('keydown', function detectKeyPress(e) {
    _.checkKey(e);
  });
};

/**
 * Reset the internal pointers of movement handler
 */
FinnaMovement.prototype.reset = function reset() {
  var _ = this;
  _.offsetCache = _.offset;
  _.offset = 0;
  _.childOffset = -1;
};

/**
 * Function to refocus to cached element
 */
FinnaMovement.prototype.setFocusTo = function setFocusTo() {
  var _ = this;
  if (_.offsetCache !== -1) {
    _.offset = _.calculateOffset(_.offsetCache, _.menuElements, 0);
    _.offsetCache = -1;
  }
  
  _.menuElements[_.offset].input.focus();
};

/** 
 * Finds all menu elements and their children if the menu is horizontal
 */
FinnaMovement.prototype.setChildData = function setChildData() {
  var _ = this;
  var i = 0;
  _.menuElements = [];

  var FOCUSABLE_ELEMENTS = ['a[href]', 'area[href]', 'input[type=radio]:checked', 'input:not([disabled]):not([type="hidden"]):not([aria-hidden]):not([type=radio])', 'select:not([disabled]):not([aria-hidden])', 'textarea:not([disabled]):not([aria-hidden])', 'button:not([disabled]):not([aria-hidden])', 'iframe', 'object', 'embed', '[contenteditable]', '[tabindex]:not([tabindex^="-"])'];

  var nodes = _.menuRootElement[0].querySelectorAll(FOCUSABLE_ELEMENTS);
  var children = [].slice.apply(nodes);
  var formedObjects = [];
  var oldObj;
  children.forEach(function testEach(element) {
    var obj = {input: $(element), children: [], parent: undefined};
    obj.input.attr('tabindex', (i++ === 0) ? '0' : '-1');
    if (typeof oldObj !== 'undefined' && _.isHorizontal) {
      if ($.contains(oldObj.parent[0], obj.input[0])) {
        oldObj.children.push(obj);
        return;
      }
    }
    if (obj.input.is('a') && _.isHorizontal) {
      obj.parent = obj.input.parent();
      oldObj = obj;
    }
    formedObjects.push(obj);
  });
  _.menuElements = formedObjects;
};

/**
 * Check the input key given by the user
 */
FinnaMovement.prototype.checkKey = function checkKey(e) {
  var _ = this;
  var code = (e.keyCode ? e.keyCode : e.which);
  switch (code) {
  case _.keys.up:
    if (_.isHorizontal) {
      _.moveSubmenu(-1);
    } else {
      _.moveMainmenu(-1);
    }
    e.preventDefault();
    break;
  case _.keys.right:
    if (_.isHorizontal) {
      _.moveMainmenu(1);
      e.preventDefault();
    } else {
      _.openSubmenu();
    }
    break;
  case _.keys.down:
    if (_.isHorizontal) {
      _.moveSubmenu(1);
    } else {
      _.moveMainmenu(1);
    }
    e.preventDefault();
    break;
  case _.keys.left:
    if (_.isHorizontal) {
      _.moveMainmenu(-1);
      e.preventDefault();
    } else {
      _.openSubmenu();
    }
    break;
  case _.keys.space:
    _.openSubmenu();
    e.preventDefault();
    break;
  }
};

/**
 * Move the cursor in the level 1 menu elements, adjusted by direction
 * 
 * @param {int} dir
 *
 */
FinnaMovement.prototype.moveMainmenu = function moveMainmenu(dir) {
  var _ = this;
  _.childOffset = -1;
  _.offset = _.calculateOffset(_.offset, _.menuElements, dir);
  var current = _.menuElements[_.offset];
  if (current.input.is(':hidden')) {
    _.moveMainmenu(dir);
  } else {
    current.input.focus();
  }
};

/**
 * Try to trigger submenu open call
 */
FinnaMovement.prototype.openSubmenu = function openSubmenu() {
  var _ = this;
  if (_.childOffset > -1) {
    return;
  }
  _.menuElements[_.offset].input.trigger('togglesubmenu');
};

/**
 * Move the cursor in the level 2 menu elements, adjusted by direction
 * 
 * @param {int} dir
 */
FinnaMovement.prototype.moveSubmenu = function moveSubmenu(dir) {
  var _ = this;
  var current = _.menuElements[_.offset];

  if (current.input.hasClass('collapsed')) {
    _.openSubmenu();
  }

  if (current.children.length === 0) {
    return;
  }

  if (current.children.length) {
    _.childOffset = _.calculateOffset(_.childOffset, current.children, dir);
    current.children[_.childOffset].input.focus();
  }
};

/**
 * Function to calculate desired index, given the old offset, array of elements and dir
 * 
 * @param {int} offset
 * @param {Array} elements
 * @param {int} dir
 */
FinnaMovement.prototype.calculateOffset = function calculateOffset(offset, elements, dir) {
  var tmp = offset;
  if (tmp + dir > elements.length - 1) {
    tmp = 0;
  } else if (tmp + dir < 0) {
    tmp = elements.length - 1;
  } else {
    tmp += dir;
  }
  return tmp;
};

finna.finnaMovement = (function finnaMovement() {
  var my = {
    init: function init() {
      $('.finna-movement').each(function initKeyboardMovement() {
        new FinnaMovement(this);
      });
    }
  };

  return my;
})();
