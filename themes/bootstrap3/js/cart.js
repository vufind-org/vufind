/*global bulkActionSubmit, cartCookieDomain, Cookies, newAccountHandler, path, vufindString, Lightbox, updatePageForLogin */

var _CART_COOKIE = 'vufind_cart';
var _CART_COOKIE_SOURCES = 'vufind_cart_src';
var _CART_COOKIE_DELIM = "\t";

var currentId,currentSource;
var lastCartSubmit = false;

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
    Cookies.setItem(_CART_COOKIE_SOURCES, cartSources.join(_CART_COOKIE_DELIM), false, '/', cartCookieDomain);
  } else {
    cartItems[cartItems.length] = String.fromCharCode(65+sIndex) + id;
  }
  Cookies.setItem(_CART_COOKIE, $.unique(cartItems).join(_CART_COOKIE_DELIM), false, '/', cartCookieDomain);
  $('#cartItems strong').html(parseInt($('#cartItems strong').html(), 10)+1);
  return true;
}
function uniqueArray(op) {
  var ret = [];
  for(var i=0;i<op.length;i++) {
    if(ret.indexOf(op[i]) < 0) {
      ret.push(op[i]);
    }
  }
  return ret;
}
function removeItemFromCart(id,source) {
  var cartItems = getCartItems();
  var cartSources = getCartSources();
  // Find
  var cartIndex = cartItems.indexOf(String.fromCharCode(65+cartSources.indexOf(source))+id);
  if(cartIndex > -1) {
    var sourceIndex = cartItems[cartIndex].charCodeAt(0)-65;
    var cartItem = cartItems[cartIndex];
    var saveSource = false;
    for(var i=cartItems.length;i--;) {
      if(i==cartIndex) {
        continue;
      }
      // If this source is shared by another, keep it
      if(cartItems[i].charCodeAt(0)-65 == sourceIndex) {
        saveSource = true;
        break;
      }
    }
    cartItems.splice(cartIndex,1);
    // Remove unused sources
    if(!saveSource) {
      var oldSources = cartSources.slice(0);
      cartSources.splice(sourceIndex,1);
      // Adjust source index characters
      for(var j=cartItems.length;j--;) {
        var si = cartItems[j].charCodeAt(0)-65;
        var ni = cartSources.indexOf(oldSources[si]);
        cartItems[j] = String.fromCharCode(65+ni)+cartItems[j].substring(1);
      }
    }
    if(cartItems.length > 0) {
      Cookies.setItem(_CART_COOKIE, uniqueArray(cartItems).join(_CART_COOKIE_DELIM), false, '/', cartCookieDomain);
      Cookies.setItem(_CART_COOKIE_SOURCES, uniqueArray(cartSources).join(_CART_COOKIE_DELIM), false, '/', cartCookieDomain);
    } else {
      Cookies.removeItem(_CART_COOKIE, '/', cartCookieDomain);
      Cookies.removeItem(_CART_COOKIE_SOURCES, '/', cartCookieDomain);
    }
    $('#cartItems strong').html(parseInt($('#cartItems strong').html(), 10)-1);
    return true;
  }
  return false;
}
var cartNotificationTimeout = false;
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
        msg += added + " " + vufindString.itemsAddBag;
        if (inCart > 0 && orig.length > 0) {
          msg += "<br/>" + inCart + " " + vufindString.itemsInBag;
        }
        if (updated.length >= vufindString.bookbagMax) {
          msg += "<br/>" + vufindString.bookbagFull;
        }
        $('#'+elId).data('bs.popover').options.content = msg;
        $('#cartItems strong').html(updated.length);
      } else {
        $('#'+elId).data('bs.popover').options.content = vufindString.bulk_noitems_advice;
      }
      $('#'+elId).popover('show');
      if (cartNotificationTimeout !== false) {
        clearTimeout(cartNotificationTimeout);
      }
      cartNotificationTimeout = setTimeout(function() {
        $('#'+elId).popover('hide');
      }, 5000);
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
  $("#updateCart, #bottom_updateCart").popover({content:'', html:true, trigger:'manual'});

  // Setup lightbox behavior
  // Cart lightbox
  $('#cartItems').click(function() {
    return Lightbox.get('Cart','Cart');
  });
  // Overwrite
  Lightbox.addFormCallback('accountForm', function(html) {
    updatePageForLogin();
    if (lastCartSubmit !== false) {
      bulkActionSubmit(lastCartSubmit);
      lastCartSubmit = false;
    } else {
      newAccountHandler(html);
    }
  });
  Lightbox.addFormHandler('cartForm', function(evt) {
    lastCartSubmit = $(evt.target);
    bulkActionSubmit($(evt.target));
    return false;
  });
  Lightbox.addFormCallback('bulkEmail', function(html) {
    Lightbox.confirm(vufindString['bulk_email_success']);
  });
  Lightbox.addFormCallback('bulkSave', function(html) {
    // After we close the lightbox, redirect to list view
    Lightbox.addCloseAction(function() {
      document.location.href = path+'/MyResearch/MyList/'+Lightbox.lastPOST['list'];
    });
    Lightbox.confirm(vufindString['bulk_save_success']);
  });
  $('#modal').on('hidden.bs.modal', function() {
    // Update cart items (add to cart, remove from cart, cart lightbox interface)
    var cartCount = $('#cartItems strong');
    if(cartCount.length > 0) {
      var cart = getFullCartItems();
      var id = $('#cartId');
      if(id.length > 0) {
        id = id.val();
        $('#cart-add,#cart-remove').addClass('hidden');
        if(cart.indexOf(id) > -1) {
          $('#cart-remove').removeClass('hidden');
        } else {
          $('#cart-add').removeClass('hidden');
        }
      }
      cartCount.html(cart.length);
    }
  });
});
