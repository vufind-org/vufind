/*global Cookies, VuFind */
/*exported cartFormHandler */

VuFind.register('cart', function Cart() {
  var _COOKIE = 'vufind_cart';
  var _COOKIE_SOURCES = 'vufind_cart_src';
  var _COOKIE_DELIM = "\t";
  var _COOKIE_DOMAIN = false;
  var _COOKIE_PATH = '/';

  function setDomain(domain) {
    _COOKIE_DOMAIN = domain;
  }

  function setCookiePath(path) {
    _COOKIE_PATH = path;
  }

  function _uniqueArray(op) {
    var ret = [];
    for (var i = 0; i < op.length; i++) {
      if (ret.indexOf(op[i]) < 0) {
        ret.push(op[i]);
      }
    }
    return ret;
  }

  function _getItems() {
    var items = Cookies.getItem(_COOKIE);
    if (items) {
      return items.split(_COOKIE_DELIM);
    }
    return [];
  }
  function _getSources() {
    var items = Cookies.getItem(_COOKIE_SOURCES);
    if (items) {
      return items.split(_COOKIE_DELIM);
    }
    return [];
  }
  function getFullItems() {
    var items = _getItems();
    var sources = _getSources();
    var full = [];
    if (items.length === 0) {
      return [];
    }
    for (var i = items.length; i--;) {
      full[full.length] = sources[items[i].charCodeAt(0) - 65] + '|' + items[i].substr(1);
    }
    return full;
  }

  function updateCount() {
    var items = VuFind.cart.getFullItems();
    $('#cartItems strong').html(items.length);
    if (items.length === parseInt(VuFind.translate('bookbagMax'), 10)) {
      $('#cartItems .full').removeClass('hidden');
    } else {
      $('#cartItems .full').addClass('hidden');
    }
  }

  function addItem(id, _source) {
    var source = _source || VuFind.defaultSearchBackend;
    var cartItems = _getItems();
    var cartSources = _getSources();
    if (cartItems.length >= parseInt(VuFind.translate('bookbagMax'), 10)) {
      return false;
    }
    var sIndex = cartSources.indexOf(source);
    if (sIndex < 0) {
      // Add source to source cookie
      cartItems[cartItems.length] = String.fromCharCode(65 + cartSources.length) + id;
      cartSources[cartSources.length] = source;
      Cookies.setItem(_COOKIE_SOURCES, cartSources.join(_COOKIE_DELIM), false, _COOKIE_PATH, _COOKIE_DOMAIN);
    } else {
      cartItems[cartItems.length] = String.fromCharCode(65 + sIndex) + id;
    }
    Cookies.setItem(_COOKIE, _uniqueArray(cartItems).join(_COOKIE_DELIM), false, _COOKIE_PATH, _COOKIE_DOMAIN);
    updateCount();
    return true;
  }
  function removeItem(id, source) {
    var cartItems = _getItems();
    var cartSources = _getSources();
    // Find
    var cartIndex = cartItems.indexOf(String.fromCharCode(65 + cartSources.indexOf(source)) + id);
    if (cartIndex > -1) {
      var sourceIndex = cartItems[cartIndex].charCodeAt(0) - 65;
      var saveSource = false;
      for (var i = cartItems.length; i--;) {
        if (i === cartIndex) {
          continue;
        }
        // If this source is shared by another, keep it
        if (cartItems[i].charCodeAt(0) - 65 === sourceIndex) {
          saveSource = true;
          break;
        }
      }
      cartItems.splice(cartIndex, 1);
      // Remove unused sources
      if (!saveSource) {
        var oldSources = cartSources.slice(0);
        cartSources.splice(sourceIndex, 1);
        // Adjust source index characters
        for (var j = cartItems.length; j--;) {
          var si = cartItems[j].charCodeAt(0) - 65;
          var ni = cartSources.indexOf(oldSources[si]);
          cartItems[j] = String.fromCharCode(65 + ni) + cartItems[j].substring(1);
        }
      }
      if (cartItems.length > 0) {
        Cookies.setItem(_COOKIE, _uniqueArray(cartItems).join(_COOKIE_DELIM), false, _COOKIE_PATH, _COOKIE_DOMAIN);
        Cookies.setItem(_COOKIE_SOURCES, _uniqueArray(cartSources).join(_COOKIE_DELIM), false, _COOKIE_PATH, _COOKIE_DOMAIN);
      } else {
        Cookies.removeItem(_COOKIE, _COOKIE_PATH, _COOKIE_DOMAIN);
        Cookies.removeItem(_COOKIE_SOURCES, _COOKIE_PATH, _COOKIE_DOMAIN);
      }
      updateCount();
      return true;
    }
    return false;
  }

  var _cartNotificationTimeout = false;
  function _registerUpdate($form) {
    if ($form) {
      $("#updateCart, #bottom_updateCart").unbind('click').click(function cartUpdate() {
        var elId = this.id;
        var selectedBoxes = $("input[name='ids[]']:checked", $form);
        var selected = [];
        $(selectedBoxes).each(function cartCheckboxValues(i) {
          selected[i] = this.value;
        });
        if (selected.length > 0) {
          var msg = "";
          var orig = getFullItems();
          $(selected).each(function cartCheckedItemsAdd() {
            var data = this.split('|');
            addItem(data[1], data[0]);
          });
          var updated = getFullItems();
          var added = updated.length - orig.length;
          var inCart = selected.length - added;
          msg += added + " " + VuFind.translate('itemsAddBag');
          if (updated.length >= parseInt(VuFind.translate('bookbagMax'), 10)) {
            msg += "<br/>" + VuFind.translate('bookbagFull');
          }
          if (inCart > 0 && orig.length > 0) {
            msg += "<br/>" + inCart + " " + VuFind.translate('itemsInBag');
          }
          $('#' + elId).data('bs.popover').options.content = msg;
          $('#cartItems strong').html(updated.length);
        } else {
          $('#' + elId).data('bs.popover').options.content = VuFind.translate('bulk_noitems_advice');
        }
        $('#' + elId).popover('show');
        if (_cartNotificationTimeout !== false) {
          clearTimeout(_cartNotificationTimeout);
        }
        _cartNotificationTimeout = setTimeout(function notificationHide() {
          $('#' + elId).popover('hide');
        }, 5000);
        return false;
      });
    }
  }

  function init() {
    // Record buttons
    var $cartId = $('.cartId');
    if ($cartId.length > 0) {
      $cartId.each(function cartIdEach() {
        var cartId = this.value.split('|');
        var currentId = cartId[1];
        var currentSource = cartId[0];
        var $parent = $(this).parent();
        $parent.find('.cart-add.correct,.cart-remove.correct').removeClass('correct hidden');
        $parent.find('.cart-add').click(function cartAddClick() {
          if (addItem(currentId, currentSource)) {
            $parent.find('.cart-add,.cart-remove').toggleClass('hidden');
          } else {
            $parent.popover({content: VuFind.translate('bookbagFull')});
            setTimeout(function recordCartFullHide() {
              $parent.popover('hide');
            }, 5000);
          }
        });
        $parent.find('.cart-remove').click(function cartRemoveClick() {
          removeItem(currentId, currentSource);
          $parent.find('.cart-add,.cart-remove').toggleClass('hidden');
        });
      });
    } else {
      // Search results
      var $form = $('form[name="bulkActionForm"]');
      _registerUpdate($form);
    }
    $("#updateCart, #bottom_updateCart").popover({content: '', html: true, trigger: 'manual'});
    updateCount();
  }

  // Reveal
  return {
    // Methods
    addItem: addItem,
    removeItem: removeItem,
    getFullItems: getFullItems,
    updateCount: updateCount,
    setDomain: setDomain,
    setCookiePath: setCookiePath,
    // Init
    init: init
  };
});

// Building an array and checking indexes prevents a race situation
// We want to prioritize empty over printing
function cartFormHandler(event, data) {
  var keys = [];
  for (var i in data) {
    if (data.hasOwnProperty(i)) {
      keys.push(data[i].name);
    }
  }
  if (keys.indexOf('ids[]') === -1) {
    return null;
  }
  if (keys.indexOf('print') > -1) {
    return true;
  }
}

document.addEventListener('VuFind.lightbox.closed', VuFind.cart.updateCount, false);
