/*global VuFind, finna */
finna.common = (function finnaCommon() {

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

  function initQrCodeLink() {
    // handle finna QR code links
    $('a.finnaQrcodeLink').click(function qrcodeToggle() {
      if ($(this).hasClass("active")) {
        $(this).html("<i class='fa fa-qr-code' aria-hidden='true'></i>").removeClass("active");
        $(this).parent().removeClass('qr-box');
      } else {
        $(this).html(VuFind.translate('qrcode_hide')).addClass("active");
        $(this).parent().addClass('qr-box');
      }

      var holder = $(this).next('.qrcode');
      if (holder.find('img').length === 0) {
        // We need to insert the QRCode image
        var template = holder.find('.qrCodeImgTag').html();
        holder.html(template);
      }
      holder.toggleClass('hidden');
      return false;
    });

    $('a.finnaQrcodeLinkRecord').click(function qrcodeToggleRecord() {
      var holder = $(this).parent().find('li');
      if (holder.find('img').length === 0) {
        // We need to insert the QRCode image
        var template = holder.find('.qrCodeImgTag').html();
        holder.html(template);
      }
      return true;
    });
  }

  var my = {
    decodeHtml: decodeHtml,
    getField: getField,
    init: function init() {
      initSearchInputListener();
      initQrCodeLink();
    }
  };

  return my;

})();
