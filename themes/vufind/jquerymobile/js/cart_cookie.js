/***
 * Functions to manipulate the "cart" cookie data.
 */
var _CART_COOKIE = 'vufind_cart';
var _CART_COOKIE_DELIM = "\t";

function getItemsFromCartCookie() {
    var cookie = $.cookie(_CART_COOKIE);
    if (cookie) {
        var cart = cookie.split(_CART_COOKIE_DELIM);
        return cart ? cart : Array();
    } 
    return Array();
}

function addItemToCartCookie(item) {
    var items = getItemsFromCartCookie();
    if (items.indexOf(item) == -1) {
        items.push(item);
        $.cookie(_CART_COOKIE, items.join(_CART_COOKIE_DELIM), { path: '/' });
    }
    return items;
}

function removeItemFromCartCookie(item) {
    var items = getItemsFromCartCookie();
    var index = items.indexOf(item);
    if (index != -1) {
        items.splice(index, 1);
    }
    $.cookie(_CART_COOKIE, items.join(_CART_COOKIE_DELIM), { path: '/' });
    return items;
}

function emptyCartCookie() {
    var items = Array();
    $.cookie(_CART_COOKIE, items.join(_CART_COOKIE_DELIM), { path: '/' });
    return items;
}