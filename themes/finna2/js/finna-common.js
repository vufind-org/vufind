/*global VuFind, finna */
finna.common = (function finnaCommon() {
  var cookieSettings = {
    path: '/',
    domain: false,
    SameSite: 'Lax'
  };

  function decodeHtml(str) {
    return $("<textarea/>").html(str).text();
  }

  function getField(obj, field) {
    if (field in obj && typeof obj[field] != 'undefined') {
      return obj[field];
    }
    return null;
  }

  function initSearchInputListener() {
    var searchInput = $('.searchForm_lookfor:visible');
    if (searchInput.length === 0) {
      return;
    }
    $(window).keypress(function onSearchInputKeypress(e) {
      if (e && (!$(e.target).is('input, textarea, select, div.CodeMirror-code'))
            && !$(e.target).hasClass('dropdown-toggle') // Bootstrap dropdown
            && !$('#modal').is(':visible')
            && (e.which >= 48) // Start from normal input keys
            && !(e.metaKey || e.ctrlKey || e.altKey)
      ) {
        var letter = String.fromCharCode(e.which);

        // IE 8-9
        if (typeof document.createElement('input').placeholder == 'undefined') {
          if (searchInput.val() === searchInput.attr('placeholder')) {
            searchInput.val('');
            searchInput.removeClass('placeholder');
          }
        }

        // Move cursor to the end of the input
        var tmpVal = searchInput.val();
        searchInput.val(' ').focus().val(tmpVal + letter);

        // Scroll to the search form
        $('html, body').animate({scrollTop: searchInput.offset().top - 20}, 150);

        e.preventDefault();
      }
    });
  }

  function initQrCodeLink(_holder) {
    var holder = typeof _holder === 'undefined' ? $(document) : _holder;
    // handle finna QR code links
    holder.find('a.finnaQrcodeLink').click(function qrcodeToggle() {
      if ($(this).hasClass("active")) {
        $(this).html("<i class='fa fa-qr-code' aria-hidden='true'></i>").removeClass("active");
        $(this).parent().removeClass('qr-box');
      } else {
        $(this).html(VuFind.translate('qrcode_hide')).addClass("active");
        $(this).parent().addClass('qr-box');
      }

      var qrholder = $(this).next('.qrcode');
      if (qrholder.find('img').length === 0) {
        // We need to insert the QRCode image
        var template = qrholder.find('.qrCodeImgTag').html();
        qrholder.html(template);
      }
      qrholder.toggleClass('hidden');
      return false;
    });

    $('a.finnaQrcodeLinkRecord').click(function qrcodeToggleRecord() {
      var qrholder = $(this).parent().find('li');
      if (qrholder.find('img').length === 0) {
        // We need to insert the QRCode image
        var template = qrholder.find('.qrCodeImgTag').html();
        qrholder.html(template);
      }
      return true;
    });
  }

  function _getCookieSettings() {
    return cookieSettings;
  }

  function setCookieSettings(settings) {
    cookieSettings = settings;
  }

  function getCookie(cookie) {
    return window.Cookies.get(cookie);
  }

  function setCookie(cookie, value, settings) {
    window.Cookies.set(cookie, value, $.extend({}, _getCookieSettings(), settings));
  }
  function removeCookie(cookie) {
    window.Cookies.remove(cookie, _getCookieSettings());
  }

  var my = {
    decodeHtml: decodeHtml,
    getField: getField,
    initQrCodeLink: initQrCodeLink,
    init: function init() {
      initSearchInputListener();
      initQrCodeLink();
    },
    getCookie: getCookie,
    setCookie: setCookie,
    removeCookie: removeCookie,
    setCookieSettings: setCookieSettings,
  };

  return my;
})();
