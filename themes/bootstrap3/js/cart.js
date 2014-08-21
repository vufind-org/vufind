/*global Cookies, newAccountHandler, path, vufindString, Lightbox, updatePageForLogin */

var Cart = {
  COOKIE: 'vufind_cart',
  COOKIE_SOURCES: 'vufind_cart_src',
  COOKIE_DELIM: "\t",
  count: 0,
  currentId: false,
  currentSource: false,

  init: function() {
    Cart.count = Cart.getItemCount();
    $('#cartItems strong').html(Cart.count);
  },

  getItems: function() {
    var items = Cookies.getItem(Cart.COOKIE);
    if(items) {
      return items.split(Cart.COOKIE_DELIM);
    }
    return [];
  },
  getSources: function() {
    var items = Cookies.getItem(Cart.COOKIE_SOURCES);
    if(items) {
      return items.split(Cart.COOKIE_DELIM);
    }
    return [];
  },
  getFullItems: function() {
    var items = Cart.getItems(),
        sources = Cart.getSources(),
        full = [];
    if(items.length === 0) {
      return [];
    }
    for(var i=items.length;i--;) {
      full[full.length] = sources[items[i].charCodeAt(0)-65]+'|'+items[i].substr(1);
    }
    return full;
  },
  getItemCount: function() {
    var items = Cart.getItems();
    return items.length;
  },
  addItem: function(id,source) {
    if(!source) {
      source = 'VuFind';
    }
    var cartItems = Cart.getItems();
    var cartSources = Cart.getSources();
    var sIndex = cartSources.indexOf(source);
    if(sIndex < 0) {
      // Add source to source cookie
      cartItems[cartItems.length] = String.fromCharCode(65+cartSources.length) + id;
      cartSources[cartSources.length] = source;
      Cookies.setItem(Cart.COOKIE_SOURCES, cartSources.join(Cart.COOKIE_DELIM), false, '/');
    } else {
      cartItems[cartItems.length] = String.fromCharCode(65+sIndex) + id;
    }
    Cookies.setItem(Cart.COOKIE, $.unique(cartItems).join(Cart.COOKIE_DELIM), false, '/');
    $('#cartItems strong').html(++Cart.count);
    return true;
  },
  removeItem: function(id,source) {
    var cartItems = Cart.getItems();
    var cartSources = Cart.getSources();
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
        Cookies.setItem(Cart.COOKIE, uniqueArray(cartItems).join(Cart.COOKIE_DELIM), false, '/');
        Cookies.setItem(Cart.COOKIE_SOURCES, uniqueArray(cartSources).join(Cart.COOKIE_DELIM), false, '/');
      } else {
        Cookies.removeItem(Cart.COOKIE, '/');
        Cookies.removeItem(Cart.COOKIE_SOURCES, '/');
      }
      $('#cartItems strong').html(--Cart.count);
      return true;
    }
    return false;
  },
  notificationTimeout: false,
  registerUpdate: function($form) {
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
          var orig = Cart.getFullItems();
          $(selected).each(function(i) {
            for (var x in orig) {
              if (this == orig[x]) {
                inCart++;
                return;
              }
            }
            var data = this.split('|');
            Cart.addItem(data[1], data[0]);
          });
          var updated = Cart.getFullItems();
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
        if (Cart.notificationTimeout !== false) {
          clearTimeout(Cart.notificationTimeout);
        }
        Cart.notificationTimeout = setTimeout(function() {
          $('#'+elId).popover('hide');
        }, 5000);
        return false;
      });
    }
  },

  // Ajax cart submission for the lightbox
  lastSubmit: false,
  submit: function($form) {
    Cart.lastSubmit = $form;
    var submit = $form.find('input[type="submit"][clicked=true]').attr('name');
    if (submit == 'print') {
      //redirect page
      var checks = $form.find('input.checkbox-select-item:checked');
      if(checks.length > 0) {
        var url = path+'/Records/Home?print=true';
        for(var i=0;i<checks.length;i++) {
          url += '&id[]='+checks[i].value;
        }
        document.location.href = url;
      } else {
        Lightbox.displayError(vufindString['bulk_noitems_advice']);
      }
    } else {
      Lightbox.submit($form, Lightbox.changeContent);
    }
  }
};
function uniqueArray(op) {
  var ret = [];
  for(var i=0;i<op.length;i++) {
    if(ret.indexOf(op[i]) < 0) {
      ret.push(op[i]);
    }
  }
  return ret;
}
$(document).ready(function() {
  Cart.init();
  // Record buttons
  var cartId = $('#cartId');
  if(cartId.length > 0) {
    cartId = cartId.val().split('|');
    Cart.currentId = cartId[1];
    Cart.currentSource = cartId[0];
    $('#cart-add.correct,#cart-remove.correct').removeClass('correct hidden');
    $('#cart-add').click(function() {
      Cart.addItem(Cart.currentId,Cart.currentSource);
      $('#cart-add,#cart-remove').toggleClass('hidden');
    });
    $('#cart-remove').click(function() {
      Cart.removeItem(Cart.currentId,Cart.currentSource);
      $('#cart-add,#cart-remove').toggleClass('hidden');
    });
  } else {
    // Search results
    var $form = $('form[name="bulkActionForm"]');
    Cart.registerUpdate($form);
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
    if (Cart.lastSubmit !== false) {
      Cart.submit(Cart.lastSubmit);
      Cart.lastSubmit = false;
    } else {
      newAccountHandler(html);
    }
  });
  Lightbox.addFormHandler('cartForm', function(evt) {
    Cart.submit($(evt.target));
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
  Lightbox.addFormHandler('exportForm', function(evt) {
    $.ajax({
      url: path + '/AJAX/JSON?' + $.param({method:'exportFavorites'}),
      type:'POST',
      dataType:'json',
      data:Lightbox.getFormData($(evt.target)),
      success:function(data) {
        if(data.data.needs_redirect) {
          document.location.href = data.data.result_url;
        } else {
          Lightbox.changeContent(data.data.result_additional);
        }
      },
      error:function(d,e) {
        //console.log(d,e); // Error reporting
      }
    });
    return false;
  });
  document.addEventListener('Lightbox.ready', function() {
    $form = $('#modal [name=cartForm]');
    $form.find('[name=empty]').click(function() {
      Cart.count = 0;
    });
  }, false)
  document.addEventListener('Lightbox.close', function() {
    // Update cart items (add to cart, remove from cart, cart lightbox interface)
    Cart.init();
    $('#cart-add,#cart-remove').addClass('hidden');
    if(Cart.count > 0) {
      var cart = Cart.getFullItems();
      var id = $('#cartId');
      if(id.length > 0) {
        id = id.val();
        if(cart.indexOf(id) > -1) {
          $('#cart-remove').removeClass('hidden');
        } else {
          $('#cart-add').removeClass('hidden');
        }
      }
    } else {
      $('#cart-add').removeClass('hidden');
    }
  }, false);
});
