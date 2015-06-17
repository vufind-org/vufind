/*global ajaxLoadTab, btoa, checkSaveStatuses, console, extractSource, hexEncode, isPhoneNumberValid, Lightbox, path, rc4Encrypt, refreshCommentList, refreshTagList, unescape, vufindString */

/* --- GLOBAL FUNCTIONS --- */
function htmlEncode(value){
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
function jqEscape(myid) {
  return String(myid).replace(/[!"#$%&'()*+,.\/:;<=>?@\[\\\]\^`{|}~]/g, "\\$&");
}
function html_entity_decode(string, quote_style)
{
  var hash_map = {},
    symbol = '',
    tmp_str = '',
    entity = '';
  tmp_str = string.toString();

  delete(hash_map['&']);
  hash_map['&'] = '&amp;';
  hash_map['>'] = '&gt;';
  hash_map['<'] = '&lt;';

  for (symbol in hash_map) {
    entity = hash_map[symbol];
    tmp_str = tmp_str.split(entity).join(symbol);
  }
  tmp_str = tmp_str.split('&#039;').join("'");

  return tmp_str;
}

// Turn GET string into array
function deparam(url) {
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
var libphoneTranslateCodes = ["libphonenumber_invalid", "libphonenumber_invalidcountry", "libphonenumber_invalidregion", "libphonenumber_notanumber", "libphonenumber_toolong", "libphonenumber_tooshort", "libphonenumber_tooshortidd"];
var libphoneErrorStrings = ["Phone number invalid", "Invalid country calling code", "Invalid region code", "The string supplied did not seem to be a phone number", "The string supplied is too long to be a phone number", "The string supplied is too short to be a phone number", "Phone number too short after IDD"];
function phoneNumberFormHandler(numID, regionCode) {
  var phoneInput = document.getElementById(numID);
  var number = phoneInput.value;
  var valid = isPhoneNumberValid(number, regionCode);
  if(valid != true) {
    if(typeof valid === 'string') {
      for(var i=libphoneErrorStrings.length;i--;) {
        if(valid.match(libphoneErrorStrings[i])) {
          valid = vufindString[libphoneTranslateCodes[i]];
        }
      }
    } else {
      valid = vufindString['libphonenumber_invalid'];
    }
    $(phoneInput).siblings('.help-block.with-errors').html(valid);
    $(phoneInput).closest('.form-group').addClass('sms-error');
  } else {
    $(phoneInput).closest('.form-group').removeClass('sms-error');
    $(phoneInput).siblings('.help-block.with-errors').html('');
  }
  return valid == true;
}

// Lightbox
/*
 * This function adds jQuery events to elements in the lightbox
 *
 * This is a default open action, so it runs every time changeContent
 * is called and the 'shown' lightbox event is triggered
 */
function bulkActionSubmit($form) {
  var submit = $form.find('[type="submit"][clicked=true]').attr('name');
  var checks = $form.find('input.checkbox-select-item:checked');
  if(checks.length == 0 && submit != 'empty') {
    return Lightbox.displayError(vufindString['bulk_noitems_advice']);
  }
  if (submit == 'print') {
    //redirect page
    var url = path+'/Records/Home?print=true';
    for(var i=0;i<checks.length;i++) {
      url += '&id[]='+checks[i].value;
    }
    document.location.href = url;
  } else {
    Lightbox.submit($form, Lightbox.changeContent);
  }
  return false;
}
function updatePageForLogin() {
  // Hide "log in" options and show "log out" options:
  $('#loginOptions').addClass('hidden');
  $('.logoutOptions').removeClass('hidden');

  var recordId = $('#record_id').val();

  // Update user save statuses if the current context calls for it:
  if (typeof(checkSaveStatuses) == 'function') {
    checkSaveStatuses();
  }

  // refresh the comment list so the "Delete" links will show
  $('.commentList').each(function(){
    var recordSource = extractSource($('#record'));
    refreshCommentList(recordId, recordSource);
  });
  // Logged in AJAX
  if ('function' === typeof registerAjaxCommentRecord) {
    $('form[name="commentRecord"]').unbind('submit').submit(registerAjaxCommentRecord);
    $('form[name="commentRecord"]').removeAttr('data-lightbox');
    $('form[name="commentRecord"]').removeAttr('data-lightbox-after-login');
  }

  var summon = false;
  $('.hiddenSource').each(function(i, e) {
    if(e.value == 'Summon') {
      summon = true;
      // If summon, queue reload for when we close
      Lightbox.addCloseAction(function(){document.location.reload(true);});
    }
  });

  // Refresh tab content
  var recordTabs = $('.recordTabs');
  if(!summon && recordTabs.length > 0) { // If summon, skip: about to reload anyway
    var tab = recordTabs.find('.active a').attr('id');
    ajaxLoadTab(tab);
  }

  if(!('undefined' === typeof lightboxLoginCallback || false === lightboxLoginCallback)) {
    lightboxLoginCallback();
    lightboxLoginCallback = false;
  }
}
function newAccountHandler(html) {
  updatePageForLogin();
  var params = deparam(Lightbox.openingURL);
  if (params['subaction'] != 'UserLogin') {
    Lightbox.getByUrl(Lightbox.openingURL);
    Lightbox.openingURL = false;
  } else {
    Lightbox.close();
  }
}
function refreshTags() {
  var recordId = $('#record_id').val();
  var recordSource = $('.hiddenSource').val();

  // Update tag list (add tag)
  var tagList = $('#tagList');
  if (tagList.length > 0) {
    tagList.empty();
    var url = path + '/AJAX/JSON?' + $.param({method:'getRecordTags',id:recordId,'source':recordSource});
    $.ajax({
      dataType: 'json',
      url: url,
      success: function(response) {
        console.log(response);
        if (response.status == 'OK') {
          $.each(response.data, function(i, tag) {
            var href = path + '/Tag?' + $.param({lookfor:tag.tag});
            var html = (i>0 ? ', ' : ' ') + '<a href="' + htmlEncode(href) + '">' + htmlEncode(tag.tag) +'</a> (' + htmlEncode(tag.cnt) + ')';
            tagList.append(html);
          });
        } else if (response.data && response.data.length > 0) {
          tagList.append(response.data);
        }
      }
    });
  }
}

// This is a full handler for the login form
function ajaxLogin(event, data) {
  var form = event.target;
  $.ajax({
    url: path + '/AJAX/JSON?method=getSalt',
    dataType: 'json',
    success: function(response) {
      if (response.status == 'OK') {
        var salt = response.data;
        // get the user entered password
        var password = form.password.value;

        // base-64 encode the password (to allow support for Unicode)
        // and then encrypt the password with the salt
        password = rc4Encrypt(salt, btoa(unescape(encodeURIComponent(password))));

        // hex encode the encrypted password
        password = hexEncode(password);

        var params = {password:password};

        // get any other form values
        for (var i = 0; i < form.length; i++) {
          if (form.elements[i].name == 'password') {
            continue;
          }
          params[form.elements[i].name] = form.elements[i].value;
        }

        // login via ajax
        $.ajax({
          type: 'POST',
          url: path + '/AJAX/JSON?method=login',
          dataType: 'json',
          data: params,
          success: function(response) {
            if (response.status == 'OK') {
              updatePageForLogin();
              lightboxAJAX(event, data);
            } else {
              $('#modal .modal-body .alert,.fa.fa-spinner').remove();
              $('#modal .modal-body p.lead').after($('<div>').html(response.data).addClass('alert alert-danger'));
            }
          }
        });
      } else {
        $('#modal .modal-body .alert,.fa.fa-spinner').remove();
        $('#modal .modal-body p.lead').after($('<div>').html(response.data).addClass('alert alert-danger'));
      }
    }
  });
}

$(document).ready(function() {
  // Off canvas
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

  // support "jump menu" dropdown boxes
  $('select.jumpMenu').change(function(){ $(this).parent('form').submit(); });

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

  // Search autocomplete
  $('.autocomplete').typeahead(
    {
      highlight: true,
      minLength: 3
    }, {
      displayKey:'val',
      source: function(query, cb) {
        var searcher = extractClassParams('.autocomplete');
        $.ajax({
          url: path + '/AJAX/JSON',
          data: {
            q:query,
            method:'getACSuggestions',
            searcher:searcher['searcher'],
            type:$('#searchForm_type').val()
          },
          dataType:'json',
          success: function(json) {
            if (json.status == 'OK' && json.data.length > 0) {
              var datums = [];
              for (var i=0;i<json.data.length;i++) {
                datums.push({val:json.data[i]});
              }
              cb(datums);
            } else {
              cb([]);
            }
          }
        });
      }
    }
  );
  $('#searchForm_type').change(function() {
    var query = $('#searchForm_lookfor').val();
    $('#searchForm_lookfor').focus().typeahead('val', '').typeahead('val', query);
  });

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
      $(this).html(vufindString.qrcode_show).removeClass("active");
    } else {
      $(this).html(vufindString.qrcode_hide).addClass("active");
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
    $.getJSON(path + '/AJAX/JSON', {method: 'keepAlive'});
  }

  // Advanced facets
  $('.facetOR').click(function() {
    $(this).closest('.collapse').html('<div class="list-group-item">'+vufindString.loading+'...</div>');
    window.location.assign($(this).attr('href'));
  });
});