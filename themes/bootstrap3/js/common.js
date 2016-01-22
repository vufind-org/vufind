/*global btoa, console, hexEncode, isPhoneNumberValid, Lightbox, rc4Encrypt, unescape, VuFind */

function VuFindNamespace(p, s, dsb) {
  var defaultSearchBackend = dsb;
  var path = p;
  var strings = s;

  var getDefaultSearchBackend = function() { return defaultSearchBackend; };
  var getPath = function() { return path; };
  var translate = function(op) { return strings[op] || op; };

  return {
    getDefaultSearchBackend: getDefaultSearchBackend,
    getPath: getPath,
    translate: translate
  };
}

/* --- GLOBAL FUNCTIONS --- */
function htmlEncode(value) {
  if (value) {
    return jQuery('<div />').text(value).html();
  } else {
    return '';
  }
}
function extractClassParams(str) {
  str = $(str).attr('class');
  if (typeof str === "undefined") {
    return [];
  }
  var params = {};
  var classes = str.split(/\s+/);
  for(var i = 0; i < classes.length; i++) {
    if (classes[i].indexOf(':') > 0) {
      var pair = classes[i].split(':');
      params[pair[0]] = pair[1];
    }
  }
  return params;
}
// Turn GET string into array
function deparam(url) {
  if(!url.match(/\?|&/)) {
    return [];
  }
  var request = {};
  var pairs = url.substring(url.indexOf('?') + 1).split('&');
  for (var i = 0; i < pairs.length; i++) {
    var pair = pairs[i].split('=');
    var name = decodeURIComponent(pair[0].replace(/\+/g, ' '));
    if(name.length == 0) {
      continue;
    }
    if(name.substring(name.length-2) == '[]') {
      name = name.substring(0,name.length-2);
      if(!request[name]) {
        request[name] = [];
      }
      request[name].push(decodeURIComponent(pair[1].replace(/\+/g, ' ')));
    } else {
      request[name] = decodeURIComponent(pair[1].replace(/\+/g, ' '));
    }
  }
  return request;
}

// Sidebar
function moreFacets(id) {
  $('.'+id).removeClass('hidden');
  $('#more-'+id).addClass('hidden');
}
function lessFacets(id) {
  $('.'+id).addClass('hidden');
  $('#more-'+id).removeClass('hidden');
}

// Phone number validation
function phoneNumberFormHandler(numID, regionCode) {
  var phoneInput = document.getElementById(numID);
  var number = phoneInput.value;
  var valid = isPhoneNumberValid(number, regionCode);
  if(valid != true) {
    if(typeof valid === 'string') {
      valid = VuFind.translate(valid);
    } else {
      valid = VuFind.translate('libphonenumber_invalid');
    }
    $(phoneInput).siblings('.help-block.with-errors').html(valid);
    $(phoneInput).closest('.form-group').addClass('sms-error');
    return false;
  } else {
    $(phoneInput).closest('.form-group').removeClass('sms-error');
    $(phoneInput).siblings('.help-block.with-errors').html('');
    return true;
  }
}

function refreshPageForLogin() {
  document.addEventListener('VuFind.lightbox.closed', function() {
    window.location.reload();
  }, false);
}
function newAccountHandler() {
  refreshPageForLogin();
  if (VuFind.lightbox.originalUrl.indexOf('UserLogin') > -1) {
    VuFind.modal('hide');
  }
  return true;
}

// This is a full handler for the login form
function ajaxLogin(form) {
  $.ajax({
    url: VuFind.getPath() + '/AJAX/JSON?method=getSalt',
    dataType: 'json',
    success: function(response) {
      if (response.status == 'OK') {
        var salt = response.data;
        // extract form values
        var params = {};
        for (var i = 0; i < form.length; i++) {
          // special handling for password
          if (form.elements[i].name == 'password') {
            // base-64 encode the password (to allow support for Unicode)
            // and then encrypt the password with the salt
            var password = rc4Encrypt(
                salt, btoa(unescape(encodeURIComponent(form.elements[i].value)))
            );
            // hex encode the encrypted password
            params[form.elements[i].name] = hexEncode(password);
          } else {
            params[form.elements[i].name] = form.elements[i].value;
          }
        }

        // login via ajax
        $.ajax({
          type: 'POST',
          url: VuFind.getPath() + '/AJAX/JSON?method=login',
          dataType: 'json',
          data: params,
          success: function(response) {
            if (response.status == 'OK' && response.data) {
              loginHandler();
            } else {
              $('#modal .modal-body .alert,.fa.fa-spinner').remove();
              $('#modal .modal-body h2:first-child').after($('<div>').html(response.data).addClass('alert alert-danger'));
            }
          }
        });
      } else {
        $('#modal .modal-body .alert,.fa.fa-spinner').remove();
        $('#modal .modal-body h2:first-child').after($('<div>').html(response.data).addClass('alert alert-danger'));
      }
    }
  });
}

// Ready functions
function setupOffcanvas() {
  if($('.sidebar').length > 0) {
    $('[data-toggle="offcanvas"]').click(function () {
      $('body.offcanvas').toggleClass('active');
      var active = $('body.offcanvas').hasClass('active');
      var right = $('body.offcanvas').hasClass('offcanvas-right');
      if((active && !right) || (!active && right)) {
        $('.offcanvas-toggle .fa').removeClass('fa-chevron-right').addClass('fa-chevron-left');
      } else {
        $('.offcanvas-toggle .fa').removeClass('fa-chevron-left').addClass('fa-chevron-right');
      }
    });
    $('[data-toggle="offcanvas"]').click().click();
  } else {
    $('[data-toggle="offcanvas"]').addClass('hidden');
  }
}

function setupBacklinks() {
  // Highlight previous links, grey out following
  $('.backlink')
    .mouseover(function() {
      // Underline back
      var t = $(this);
      do {
        t.css({'text-decoration':'underline'});
        t = t.prev();
      } while(t.length > 0);
      // Mute ahead
      t = $(this).next();
      do {
        t.css({'color':'#999'});
        t = t.next();
      } while(t.length > 0);
    })
    .mouseout(function() {
      // Underline back
      var t = $(this);
      do {
        t.css({'text-decoration':'none'});
        t = t.prev();
      } while(t.length > 0);
      // Mute ahead
      t = $(this).next();
      do {
        t.css({'color':''});
        t = t.next();
      } while(t.length > 0);
    });
}

function setupAutocomplete() {
  // Search autocomplete
  $('.autocomplete').each(function(i, op) {
    $(op).autocomplete({
      maxResults: 10,
      loadingString: VuFind.translate('loading')+'...',
      handler: function(query, cb) {
        var searcher = extractClassParams(op);
        $.fn.autocomplete.ajax({
          url: VuFind.getPath() + '/AJAX/JSON',
          data: {
            q:query,
            method:'getACSuggestions',
            searcher:searcher['searcher'],
            type:searcher['type'] ? searcher['type'] : $(op).closest('.searchForm').find('.searchForm_type').val(),
            hiddenFilters:hiddenFilters
          },
          dataType:'json',
          success: function(json) {
            if (json.status == 'OK' && json.data.length > 0) {
              var datums = [];
              for (var i=0;i<json.data.length;i++) {
                datums.push(json.data[i]);
              }
              cb(datums);
            } else {
              cb([]);
            }
          }
        });
      }
    });
  });
  // Update autocomplete on type change
  $('.searchForm_type').change(function() {
    var $lookfor = $(this).closest('.searchForm').find('.searchForm_lookfor[name]');
    $lookfor.autocomplete('clear cache');
    $lookfor.focus();
  });
}

/**
 * Handle arrow keys to jump to next record
 * @returns {undefined}
 */
function keyboardShortcuts() {
    var $searchform = $('#searchForm_lookfor');
    if ($('.pager').length > 0) {
        $(window).keydown(function(e) {
          if (!$searchform.is(':focus')) {
            var $target = null;
            switch (e.keyCode) {
              case 37: // left arrow key
                $target = $('.pager').find('a.previous');
                if ($target.length > 0) {
                    $target[0].click();
                    return;
                }
                break;
              case 38: // up arrow key
                if (e.ctrlKey) {
                    $target = $('.pager').find('a.backtosearch');
                    if ($target.length > 0) {
                        $target[0].click();
                        return;
                    }
                }
                break;
              case 39: //right arrow key
                $target = $('.pager').find('a.next');
                if ($target.length > 0) {
                    $target[0].click();
                    return;
                }
                break;
              case 40: // down arrow key
                break;
            }
          }
        });
    }
}

$(document).ready(function() {
  // Setup search autocomplete
  setupAutocomplete();
  // Setup highlighting of backlinks
  setupBacklinks() ;
  // Off canvas
  setupOffcanvas();
  // Keyboard shortcuts in detail view
  keyboardShortcuts();

  // support "jump menu" dropdown boxes
  $('select.jumpMenu').change(function(){ $(this).parent('form').submit(); });

  // Checkbox select all
  $('.checkbox-select-all').change(function() {
    $(this).closest('form').find('.checkbox-select-item').prop('checked', this.checked);
  });
  $('.checkbox-select-item').change(function() {
    $(this).closest('form').find('.checkbox-select-all').prop('checked', false);
  });

  // handle QR code links
  $('a.qrcodeLink').click(function() {
    if ($(this).hasClass("active")) {
      $(this).html(VuFind.translate('qrcode_show')).removeClass("active");
    } else {
      $(this).html(VuFind.translate('qrcode_hide')).addClass("active");
    }

    var holder = $(this).next('.qrcode');
    if (holder.find('img').length == 0) {
      // We need to insert the QRCode image
      var template = holder.find('.qrCodeImgTag').html();
      holder.html(template);
    }
    holder.toggleClass('hidden');
    return false;
  });

  // Print
  var url = window.location.href;
  if(url.indexOf('?' + 'print' + '=') != -1  || url.indexOf('&' + 'print' + '=') != -1) {
    $("link[media='print']").attr("media", "all");
    $(document).ajaxStop(function() {
      window.print();
    });
    // Make an ajax call to ensure that ajaxStop is triggered
    $.getJSON(VuFind.getPath() + '/AJAX/JSON', {method: 'keepAlive'});
  }

  // Advanced facets
  $('.facetOR').click(function() {
    $(this).closest('.collapse').html('<div class="list-group-item">'+VuFind.translate('loading')+'...</div>');
    window.location.assign($(this).attr('href'));
  });
});
