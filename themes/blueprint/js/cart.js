/*global vufindString*/

var _CART_COOKIE = 'vufind_cart';
var _CART_COOKIE_SOURCES = 'vufind_cart_src';
var _CART_COOKIE_DELIM = "\t";

function getItemsFromCartCookie() {
    var ids = $.cookie(_CART_COOKIE);
    if (ids) {
        var cart = ids.split(_CART_COOKIE_DELIM);
        if (!cart) {
            return [];
        }

        var sources = $.cookie(_CART_COOKIE_SOURCES);

        if (!sources) {
            // Backward compatibility with VuFind 1.x -- if no source cookie, all
            // items come from the VuFind source:
            for (var i = 0; i < cart.length; i++) {
                cart[i] = 'VuFind|' + cart[i];
            }
        } else {
            // Default case for VuFind 2.x carts -- decompress source data:
            sources = sources.split(_CART_COOKIE_DELIM);
            for (var i = 0; i < cart.length; i++) {
                var sourceIndex = cart[i].charCodeAt(0) - 65;
                cart[i] = sources[sourceIndex] + '|' + cart[i].substr(1);
            }
        }

        return cart;
    }
    return [];
}

function cartHelp(msg, elId) {
    contextHelp.flash('#' + elId, '10', '1', 'down', 'right', msg, 5000);
}

// return unique values from the given array
function uniqueValues(array) {
    var o = {}, i, l = array.length, r = [];
    for(var i=0; i<l;i++) {
        o[array[i]] = array[i];
    }
    for(var i in o) {
        r.push(o[i]);
    }
    return r;
}

function saveCartCookie(items) {
    // No items?  Clear cookies:
    if (items.length == 0) {
        $.cookie(_CART_COOKIE, null, { path: '/' });
        $.cookie(_CART_COOKIE_SOURCES, null, { path: '/' });
        return;
    }

    // If we got this far, we actually need to save things:
    var sources = [];
    var ids = [];
    for (var i = 0; i < items.length; i++) {
        // Break apart the source and the ID:
        var parts = items[i].split('|');
        var itemSource = parts[0];

        // Just in case the ID contains a pipe, put the pieces back together:
        parts.splice(0, 1);
        var itemId = parts.join('|');

        // Add the source to the source array if it is not already there:
        var sourceIndex = $.inArray(itemSource, sources);
        if (sourceIndex == -1) {
            sourceIndex = sources.length;
            sources[sourceIndex] = itemSource;
        }

        // Encode the source into the ID as a single character:
        ids.push(String.fromCharCode(65 + sourceIndex) + itemId);
    }

    // Save the cookies:
    $.cookie(_CART_COOKIE, ids.join(_CART_COOKIE_DELIM), { path: '/' });
    $.cookie(_CART_COOKIE_SOURCES, sources.join(_CART_COOKIE_DELIM), { path: '/' });
}

function addItemToCartCookie(item) {
    var items = getItemsFromCartCookie();
    if(items.length < vufindString.bookbagMax) {
      items.push(item);
    }
    items = uniqueValues(items);
    saveCartCookie(items);
    return items;
}

function removeItemFromCartCookie(item) {
    var items = getItemsFromCartCookie();
    var index = $.inArray(item, items);
    if (index != -1) {
        items.splice(index, 1);
    }
    saveCartCookie(items);
    return items;
}

function updateRecordState(items) {
    var cartRecordId = $('#cartId').val();
    if (cartRecordId != undefined) {
        var index = $.inArray(cartRecordId, items);
        if(index != -1) {
            $('#recordCart').html(vufindString.removeBookBag).removeClass('cartAdd').addClass('cartRemove');
        } else {
            $('#recordCart').html(vufindString.addBookBag).removeClass('cartRemove').addClass('cartAdd');
        }
    }
}

function updateCartSummary(items) {
    $('#cartSize').empty().append(items.length);
    var cartStatus = (items.length >= vufindString.bookbagMax) ? " (" + vufindString.bookbagStatusFull + ")" : "&nbsp;";
    $('#cartStatus').html(cartStatus);
}

function removeRecordState() {
    $('#recordCart').html(vufindString.addBookBag).removeClass('cartRemove').addClass('cartAdd');
    $('#cartSize').empty().append("0");
}

function removeCartCheckbox() {
 $('.checkbox_ui, .selectAllCheckboxes').each(function(){
     $(this).attr('checked', false);
 });
}

function redrawCartStatus() {
    var items = getItemsFromCartCookie();
    removeCartCheckbox();
    updateRecordState(items);
    updateCartSummary(items);
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
                var orig = getItemsFromCartCookie();
                $(selected).each(function(i) {
                    for (var x in orig) {
                        if (this == orig[x]) {
                            inCart++;
                            return;
                        }
                    }
                    addItemToCartCookie(this);
                });
                var updated = getItemsFromCartCookie();
                var added = updated.length - orig.length;
                msg += added + " " + vufindString.itemsAddBag + "<br />";
                if (inCart > 0 && orig.length > 0) {
                    msg += inCart + " " + vufindString.itemsInBag + "<br />";
                }
                if (updated.length >= vufindString.bookbagMax) {
                  msg += vufindString.bookbagFull + "<br />";
                }
                cartHelp(msg, elId);
            } else {
                cartHelp(vufindString.bulk_noitems_advice, elId);
            }
            redrawCartStatus();
            return false;
        });
    }
}

$(document).ready(function() {
    var cartRecordId = $('#cartId').val();
    $('#cartItems').hide();
    $('#viewCart, #updateCart, #bottom_updateCart').removeClass('offscreen');

    // Record
    $('#recordCart').removeClass('offscreen').click(function() {
        if(cartRecordId != undefined) {
            if ($(this).hasClass('bookbagAdd')) {
                updateCartSummary(addItemToCartCookie(cartRecordId));
                $(this).html(vufindString.removeBookBag).removeClass('bookbagAdd').addClass('bookbagDelete');
            } else {
                updateCartSummary(removeItemFromCartCookie(cartRecordId));
                $(this).html(vufindString.addBookBag).removeClass('bookbagDelete').addClass('bookbagAdd');
            }
        }
        return false;
    });
    redrawCartStatus()
    var $form = $('form[name="bulkActionForm"]');
    registerUpdateCart($form);
});