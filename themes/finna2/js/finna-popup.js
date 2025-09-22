/*global VuFind, unwrapJQuery, getFocusableNodes */
/**
 * Constructor for finna popup element
 * @param {jQuery} trigger Element to act as a trigger
 * @param {object} params Popup parameters
 * @param {string} id Unique id of the trigger
 */
function FinnaPopup(trigger, params, id) {
  var _ = this;
  _.triggers = [];
  _.isOpen = false;
  _.openIndex = 0;
  _.id = id;
  _.triggerId = 'popup-' + id + '-index';

  // If given parent element, we create a new element inside that rather than opening a new popup
  _.parent = params.parent;
  if (typeof params.onPopupInit !== 'undefined') {
    _.onPopupInit = params.onPopupInit;
  }
  if (typeof params.beforeOpen !== 'undefined') {
    _.beforeOpen = params.beforeOpen;
  }
  _.addTrigger(trigger);
  // Popup modal stuff, backdrop and content etc
  _.cycle = typeof params.cycle !== 'undefined' ? params.cycle : true;
  _.backDrop = undefined;
  _.content = undefined;
  _.nextPopup = undefined;
  _.previousPopup = undefined;
  _.closeButton = undefined;
  _.beforeOpenFocus = undefined;
  _.classes = typeof params.classes === 'undefined' ? '' : params.classes;
  _.modalBase = typeof params.modal !== 'undefined' ? $(params.modal) : $('<div class="finna-popup default-modal"/>');
  _.translations = typeof params.translations !== 'undefined' ? params.translations : {close: 'close'};
  _.patterns = {
    youtube: {
      index: 'youtube.com',
      id: 'v=',
      src: '//www.youtube.com/embed/%id%?autoplay=1'
    },
    youtube_short: {
      index: 'youtu.be/',
      id: 'youtu.be/',
      src: '//www.youtube.com/embed/%id%?autoplay=1'
    },
    vimeo: {
      index: 'vimeo.com/',
      id: '/',
      src: '//player.vimeo.com/video/%id%?autoplay=1'
    }
  };
}

/**
 * Adjusts a given src to match an embed link in popular services
 * @param {string} src Url to change into
 * @returns {string} Embed src
 */
FinnaPopup.prototype.adjustEmbedLink = function adjustEmbedLink(src) {
  var _ = this;
  var embedSrc = src;
  $.each(_.patterns, function findPattern() {
    var p = this;
    if (embedSrc.indexOf(p.index) > -1) {
      embedSrc = embedSrc.substr(embedSrc.lastIndexOf(p.id) + p.id.length, embedSrc.length);
      embedSrc = p.src.replace('%id%', embedSrc );
      return false;
    }
  });
  return embedSrc;
};

/**
 * Adds a trigger element to popups internal array, so it can be properly found
 * @param {jQuery} trigger Trigger to display a popup
 */
FinnaPopup.prototype.addTrigger = function addTrigger(trigger) {
  var _ = this;
  if (typeof trigger.data(_.triggerId) === 'undefined') {
    _.triggers.push(trigger);
    trigger.attr('data-popup-id', _.id);
    trigger.data(_.triggerId, _.triggers.length - 1);
    _.onPopupInit(trigger);
  }
};

/**
 * If popup needs to do something before it opens
 */
FinnaPopup.prototype.beforeOpen = function beforeOpen(){};

/**
 * If popup needs to do something custom when its being opened
 */
FinnaPopup.prototype.customOpen = function customOpen(){};

/**
 * If popup needs to do something custom when its being closed
 */
FinnaPopup.prototype.customClose = function customClose(){};

/**
 * Reindex the triggers to proper objects, so they are in correct order
 */
FinnaPopup.prototype.reIndex = function reIndex() {
  var _ = this;
  _.triggers = [];
  $(`[data-popup-id="${_.id}"]`).each(function toList() {
    var trigger = $(this);
    trigger.removeData(_.triggerId);
    _.addTrigger(trigger);
  });
};

/**
 * Returns the current open trigger
 * @returns {jQuery} Current open trigger
 */
FinnaPopup.prototype.currentTrigger = function currentTrigger() {
  var _ = this;
  return $(_.triggers[_.openIndex]);
};

/**
 * Close a trigger and open the next one found from the internal array
 * @param {number} direction -1 or 1 to look for a trigger
 */
FinnaPopup.prototype.getTrigger = function getTrigger(direction) {
  var _ = this;
  if (typeof _.triggers[_.openIndex + direction] !== 'undefined') {
    _.customClose();
    _.triggers[_.openIndex + direction].trigger('openmodal');
  }
  _.checkButtons();
};

/**
 * Checks if the buttons needs to be hidden if there is no other popups in the internal array
 */
FinnaPopup.prototype.checkButtons = function checkButtons() {
  var _ = this;
  if (typeof _.previousPopup === 'undefined' && typeof _.nextPopup === 'undefined') {
    return;
  }

  _.previousPopup.toggle(_.openIndex > 0 && _.triggers.length > 1);
  _.nextPopup.toggle(_.openIndex < _.triggers.length - 1 && _.triggers.length > 1);
};

/**
 * Main function to open a popup and properly display it
 */
FinnaPopup.prototype.show = function show() {
  const next = '<button class="popup-arrow popup-right-arrow next-record" type="button">' + VuFind.icon('record-next', 'record-next-icon') + '</button>';
  const previous = '<button class="popup-arrow popup-left-arrow previous-record" type="button">' + VuFind.icon('record-prev', 'record-prev-icon') + '</button>';
  const closeTemplate = '<button class="finna-popup close-button" title="close_translation" aria-label="close_translation">' + VuFind.icon('popup-close', 'popup-close-icon') + '</button>';
  const srElement = '<span class="visually-hidden"></span>';
  var _ = this;
  var hasParent = typeof _.parent !== 'undefined';
  if (!hasParent) {
    $(document).on('focusin.finna', function setFocusTrap(e) {
      _.focusTrap(e);
    });
  }
  _.setKeyBinds();

  if (typeof _.backDrop === 'undefined' && !hasParent) {
    _.backDrop = $('<div class="finna-popup backdrop"></div>');
    $(document.body).prepend(_.backDrop);
    _.backDrop.off('click').on('click', function checkClose(e) {
      if (!$.contains(_.modalHolder[0], e.target)) {
        _.onPopupClose();
      }
    });
    $(document.body).addClass('overflow-hidden');
  }
  if (typeof _.modalHolder !== 'undefined') {
    _.modalHolder.remove();
  }
  if (typeof _.content === 'undefined' && !hasParent) {
    _.content = $('<div class="finna-popup content" tabindex="-1"></div>');
    _.backDrop.append(_.content);
  } else if (hasParent) {
    _.content = $('#' + _.parent);
    _.content.addClass('finna-popup');
    if (_.content.children().length > 0) {
      _.content.empty();
    }
  }
  _.modalHolder = $('<div class="finna-popup ' + _.classes + ' modal-holder"/>');
  _.content.prepend(_.modalHolder);
  if (typeof _.parent === 'undefined') {
    if (typeof _.closeButton === 'undefined') {
      _.closeButton = $(closeTemplate).clone();
      _.closeButton.attr({
        'title': _.getTranslation('close'),
        'aria-label': _.getTranslation('close')
      });
    }
    _.closeButton.on('click', function callClose(e) {
      e.preventDefault();
      e.stopPropagation();
      _.onPopupClose();
    });
    _.modalHolder.prepend(_.closeButton);
  }
  if (typeof _.previousPopup === 'undefined' && _.cycle) {
    _.previousPopup = $(previous).clone();
    _.previousPopup.off('click').on('click', function nextPopup(e) {
      e.preventDefault();
      e.stopPropagation();
      _.getTrigger(-1);
    });
    _.previousPopup.attr('title', _.getTranslation('previous'));
    _.previousPopup.append($(srElement).clone().html(_.getTranslation('previous')));
    _.content.append(_.previousPopup);
  }

  if (typeof _.nextPopup === 'undefined' && _.cycle) {
    _.nextPopup = $(next).clone();
    _.nextPopup.off('click').on('click', function nextPopup(e) {
      e.preventDefault();
      e.stopPropagation();
      _.getTrigger(+1);
    });
    _.nextPopup.attr('title', _.getTranslation('next'));
    _.nextPopup.append($(srElement).clone().html(_.getTranslation('next')));
    _.content.append(_.nextPopup);
  }
  _.isOpen = true;
  _.checkButtons();
};

/**
 * Get translation for internal key
 * @param {string} key Translation key to get
 * @returns {string} Translation or the key if no translation found
 */
FinnaPopup.prototype.getTranslation = function getTranslation(key) {
  var _ = this;
  return typeof _.translations[key] === 'undefined' ? key : _.translations[key];
};

/**
 * Function to bind keyup events to modal
 */
FinnaPopup.prototype.setKeyBinds = function setKeyBinds() {
  var _ = this;
  $(document).off('keyup.finna').on('keyup.finna', function checkKey(e) {
    var key = e.key;
    if (key !== 'undefined') {
      switch (key) {
      case 'Esc':
      case 'Escape':
        _.onPopupClose();
        break;
      default:
        break;
      }
    }
  });
};

/**
 * Remove keyup events
 */
FinnaPopup.prototype.clearKeyBinds = function clearKeyBinds() {
  $(document).off('keyup.finna');
};

/**
 * Function to do after the trigger has been added to the internal array
 */
FinnaPopup.prototype.onPopupInit = function onPopupInit(/*trigger*/) { };

/**
 * Handles the flow of opening modals
 * @param {Function} open Function when the popup opens
 * @param {Function} close Function when the popup closes
 */
FinnaPopup.prototype.onPopupOpen = function onPopupOpen(open, close) {
  var _ = this;
  _.beforeOpenFocus = $(':focus')[0];
  _.beforeOpen();
  _.show();

  if (typeof open !== 'undefined') {
    _.customOpen = open;
  }
  if (typeof close !== 'undefined') {
    _.customClose = close;
  }
  var modalClone = _.modalBase.clone();
  _.modalHolder.append(modalClone);
  _.customOpen();
};

/**
 * Function that handles the flow when a popup closes
 */
FinnaPopup.prototype.onPopupClose = function onPopupClose() {
  var _ = this;
  if (typeof _.parent === 'undefined') {
    $(document.body).removeClass('overflow-hidden');
    $(document).off('focusin.finna');
  }
  if (typeof _.backDrop !== 'undefined') {
    _.backDrop.remove();
    _.backDrop = undefined;
  }

  _.modalHolder = undefined;
  if (_.parent) {
    _.content.empty();
  }
  _.content = undefined;
  _.nextPopup = undefined;
  _.previousPopup = undefined;
  _.closeButton = undefined;
  _.customClose();
  _.customOpen = function customOpen() {};
  _.customClose = function customClose() {};
  _.isOpen = false;

  _.clearKeyBinds();

  if (typeof _.beforeOpenFocus !== 'undefined') {
    _.beforeOpenFocus.focus();
    _.beforeOpenFocus = undefined;
  }
};

/**
 * Way to keep users tab inside modal elements
 * @param {object} e Event handler object
 */
FinnaPopup.prototype.focusTrap = function focusTrap(e) {
  var _ = this;
  const element = unwrapJQuery(_.content);
  if (!$.contains(element, e.target)) {
    const nodes = getFocusableNodes(element);
    if (nodes.length) {
      nodes[0].focus();
    }
  }
};

(function popupModule($) {
  $.fn.finnaPopup = function finnaPopup(params) {
    var _ = $(this);
    if (typeof $.fn.finnaPopup.popups === 'undefined') {
      $.fn.finnaPopup.popups = {};
    }
    var id = typeof params.id === 'undefined' ? 'default' : params.id;

    if (typeof $.fn.finnaPopup.popups[id] === 'undefined') {
      $.fn.finnaPopup.popups[id] = new FinnaPopup($(this), params, params.id);
    } else {
      $.fn.finnaPopup.popups[id].addTrigger($(this));
    }
    var events = (typeof params.noClick === 'undefined' || !params.noClick) ? 'click openmodal.finna' : 'openmodal.finna';
    if (params.overrideEvents) {
      events = params.overrideEvents;
    }
    _.off(events).on(events, function showModal(e) {
      e.preventDefault();
      // We need to tell which triggers is being used
      $.fn.finnaPopup.popups[id].openIndex = _.data('popup-' + id + '-index');
      $.fn.finnaPopup.popups[id].onPopupOpen(params.onPopupOpen, params.onPopupClose);
    });
    _.on('removeclick.finna', function removeClick() {
      _.off('click');
    });
    if (typeof params.embed !== 'undefined' && params.embed) {
      if (typeof $.fn.finnaPopup.popups[id].content === 'undefined') {
        $.fn.finnaPopup.popups[id].triggers[0].trigger('openmodal.finna');
      }
    }
  };
  $.fn.finnaPopup.reIndex = function reIndex() {
    $.each($.fn.finnaPopup.popups, function callReindex(key, obj) {
      obj.reIndex();
    });
  };
  $.fn.finnaPopup.getCurrent = function getCurrent(id) {
    if (typeof $.fn.finnaPopup.popups !== 'undefined') {
      return $.fn.finnaPopup.popups[id].openIndex;
    }
    return undefined;
  };
  $.fn.finnaPopup.closeOpen = function closeOpen(id) {
    if (id) {
      if ($.fn.finnaPopup.popups && typeof $.fn.finnaPopup.popups[id] !== 'undefined') {
        if ($.fn.finnaPopup.popups[id].isOpen) {
          $.fn.finnaPopup.popups[id].onPopupClose();
        }
      }
    } else {
      $.each($.fn.finnaPopup.popups, function callClose(key, obj) {
        if (obj.isOpen && typeof obj.parent === 'undefined') {
          obj.onPopupClose();
        }
      });
    }
  };
  $.fn.finnaPopup.isOpen = function isOpen() {
    var open = false;
    $.each($.fn.finnaPopup.popups, function checkOpen(key, obj) {
      if (obj.isOpen) {
        open = true;
        return false;
      }
    });
    return open;
  };
})(jQuery);
