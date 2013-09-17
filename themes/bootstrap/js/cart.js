/*global Cookies, vufindString */

var _CART_COOKIE = 'vufind_cart';
var _CART_COOKIE_SOURCES = 'vufind_cart_src';
var _CART_COOKIE_DELIM = "\t";

var currentId,currentSource;

function getCartItems() {
  var items = Cookies.getItem(_CART_COOKIE);
  if(items) {
    return items.split(_CART_COOKIE_DELIM);
  }
  return [];
}
function getCartSources() {
  var items = Cookies.getItem(_CART_COOKIE_SOURCES);
  if(items) {
    return items.split(_CART_COOKIE_DELIM);
  }
  return [];
}
function getFullCartItems() {
  var items = getCartItems();
  var sources = getCartSources();
  var full = [];
  if(items.length == 0) {
    return [];
  }
  for(var i=items.length;i--;) {
    full[full.length] = sources[items[i].charCodeAt(0)-65]+'|'+items[i].substr(1);
  }
  return full;
}

function addItemToCart(id,source) {
  if(!source) {
    source = 'VuFind';
  }
  var cartItems = getCartItems();
  var cartSources = getCartSources();
  var sIndex = cartSources.indexOf(source);
  if(sIndex < 0) {
    // Add source to source cookie
    cartItems[cartItems.length] = String.fromCharCode(65+cartSources.length) + id;
    cartSources[cartSources.length] = source;
    Cookies.setItem(_CART_COOKIE_SOURCES, cartSources.join(_CART_COOKIE_DELIM), false, '/');
  } else {
    cartItems[cartItems.length] = String.fromCharCode(65+sIndex) + id;
  }
  Cookies.setItem(_CART_COOKIE, $.unique(cartItems).join(_CART_COOKIE_DELIM), false, '/');
  $('#cartItems strong').html(parseInt($('#cartItems strong').html(), 10)+1);
  return true;
}
function removeItemFromCart(id,source) {
  var cartItems = getCartItems();
  var cartSources = getCartSources();
  for(var i=cartItems.length;i--;) {
    if(cartItems[i].substr(1) == id && cartSources[cartItems[i].charCodeAt(0)-65] == source) {
      var saveSource = false;
      for(var j=cartItems.length;j--;) {
        if(j==i) {
          continue;
        }
        if(cartItems[j].charCodeAt(0)-65 == i) {
          saveSource = true;
          break;
        }
      }
      cartItems.splice(i,1);
      if(!saveSource) {
        cartSources.splice(i,1);
      }
      if(cartItems.length > 0) {
        Cookies.setItem(_CART_COOKIE, $.unique(cartItems).join(_CART_COOKIE_DELIM), false, '/');
        Cookies.setItem(_CART_COOKIE_SOURCES, $.unique(cartSources).join(_CART_COOKIE_DELIM), false, '/');
      } else {
        Cookies.removeItem(_CART_COOKIE, '/');
        Cookies.removeItem(_CART_COOKIE_SOURCES, '/');
      }
      $('#cartItems strong').html(parseInt($('#cartItems strong').html(), 10)-1);
      return true;
    }
  }
  return false;
}

function registerUpdateCart($form) {
  if($form) {
    $("#updateCart, #bottom_updateCart").unbind('click').click(function(){
      var elId = this.id;
      var selectedBoxes = $("input[name='ids[]']:checked", $form);
      var selected = [];
      $(selectedBoxes).each(function(i) {
        selected[i] = this.value;
      });
      if (selected.length > 0) {
        var inCart = 0;
        var msg = "";
        var orig = getFullCartItems();
        $(selected).each(function(i) {
          for (var x in orig) {
            if (this == orig[x]) {
              inCart++;
              return;
            }
          }
          var data = this.split('|');
          addItemToCart(data[1], data[0]);
        });
        var updated = getFullCartItems();
        var added = updated.length - orig.length;
        msg += added + " " + vufindString.itemsAddBag + "\n\n";
        if (inCart > 0 && orig.length > 0) {
          msg += inCart + " " + vufindString.itemsInBag + "\n\n";
        }
        if (updated.length >= vufindString.bookbagMax) {
          msg += vufindString.bookbagFull;
        }
        $('#'+elId).popover({content:msg}).popover('show');
        $('#cartItems strong').html(updated.length);
      } else {
        $('#'+elId).popover({content:vufindString.bulk_noitems_advice}).popover('show');
      }
      return false;
    });
  }
}

$(document).ready(function() {
  // Record buttons
  var cartId = $('#cartId');
  if(cartId.length > 0) {
    cartId = cartId.val().split('|');
    currentId = cartId[1];
    currentSource = cartId[0];
    $('#cart-add.correct,#cart-remove.correct').removeClass('correct hidden');
    $('#cart-add').click(function() {
      addItemToCart(currentId,currentSource);
      $('#cart-add,#cart-remove').toggleClass('hidden');
    });
    $('#cart-remove').click(function() {
      removeItemFromCart(currentId,currentSource);
      $('#cart-add,#cart-remove').toggleClass('hidden');
    });
  } else {
    // Search results
    var $form = $('form[name="bulkActionForm"]');
    registerUpdateCart($form);
  }
});
