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
function html_entity_decode(string, quote_style) {
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

// Advanced facets
function updateOrFacets(url, op) {
  window.location.assign(url);
  var list = $(op).parents('ul');
  var header = $(list).find('li.nav-header');
  list.html(header[0].outerHTML+'<div class="alert alert-info">'+vufindString.loading+'...</div>');
}
function setupOrFacets() {
  $('.facetOR').find('.icon-check').replaceWith('<input type="checkbox" checked onChange="updateOrFacets($(this).parent().parent().attr(\'href\'), this)"/>');
  $('.facetOR').find('.icon-check-empty').replaceWith('<input type="checkbox" onChange="updateOrFacets($(this).parent().attr(\'href\'), this)"/> ');
}

// Record
function refreshCommentList(recordId, recordSource, parent) {
  var url = path + '/AJAX/JSON?' + $.param({method:'getRecordCommentsAsHTML',id:recordId,'source':recordSource});
  $.ajax({
    dataType: 'json',
    url: url,
    success: function(response) {
      // Update HTML
      if (response.status == 'OK') {
        $commentList = typeof parent === "undefined" || $(parent).find('.commentList').length === 0
          ? $('.commentList')
          : $(parent).find('.commentList');
        $commentList.empty();
        $commentList.append(response.data);
        $('input[type="submit"]').button('reset');
        $('.delete').unbind('click').click(function() {
          var commentId = $(this).attr('id').substr('recordComment'.length);
          deleteRecordComment(this, recordId, recordSource, commentId);
          return false;
        });
      }
    }
  });
}

function refreshTagList(loggedin, parent) {
  loggedin = !!loggedin || userIsLoggedIn;
  var recordId = typeof parent === "undefined"
    ? $('.hiddenId').val()
    : $(parent).find('.hiddenId').val();
  var recordSource = typeof parent === "undefined"
    ? $('.hiddenSource').val()
    : $(parent).find('.hiddenSource').val();
  var $tagList = typeof parent === "undefined"
    ? $('.tagList')
    : $(parent).find('.tagList');
  if ($tagList.length > 0) {
    $tagList.empty();
    var url = path + '/AJAX/JSON?' + $.param({method:'getRecordTags',id:recordId,'source':recordSource});
    $.ajax({
      dataType: 'json',
      url: url,
      complete: function(response) {
        if(response.status == 200) {
          $tagList.html(response.responseText);
          if(loggedin) {
            $tagList.addClass('loggedin');
          } else {
            $tagList.removeClass('loggedin');
          }
        }
      }
    });
  }
}
function ajaxTagUpdate(link, tag, remove) {
  if(typeof remove === "undefined") {
    remove = false;
  }
  var $parent = $(link).closest('.record');
  var recordId = $parent.find('.hiddenId').val();
  var recordSource = $parent.find('.hiddenSource').val();
  $.ajax({
    url:path+'/AJAX/JSON?method=tagRecord',
    method:'POST',
    data:{
      tag:'"'+tag.replace(/\+/g, ' ')+'"',
      id:recordId,
      source:recordSource,
      remove:remove
    },
    complete:function() {
      refreshTagList(false, $parent);
    }
  });
}

/**
 * @param string form Form or element containing the comment hidden, textarea, and button
 */
function registerAjaxCommentRecord(form) {
  // Form submission
  var $form = $(form);
  var id = $form.find('[name="id"]').val();
  var recordSource = $form.find('[name="source"]').val();
  var url = path + '/AJAX/JSON?' + $.param({method:'commentRecord'});
  var data = {
    comment:$form.find('[name="comment"]').val(),
    id:id,
    source:recordSource
  };
  $.ajax({
    type: 'POST',
    url:  url,
    data: data,
    dataType: 'json',
    success: function(response) {
      if (response.status == 'OK') {
        refreshCommentList(id, recordSource, form);
        $form.find('textarea[name="comment"]').val('');
        $form.find('input[type="submit"]').button('loading');
      } else {
        Lightbox.displayError(response.data);
      }
    }
  });
  return false;
}
function deleteRecordComment(element, recordId, recordSource, commentId) {
  var url = path + '/AJAX/JSON?' + $.param({method:'deleteRecordComment',id:commentId});
  $.ajax({
    dataType: 'json',
    url: url,
    success: function(response) {
      if (response.status == 'OK') {
        $($(element).parents('.comment')[0]).remove();
      }
    }
  });
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
  var button = $form.find('[type="submit"][clicked=true]');
  var submit = button.attr('name');
  var checks = $form.find('input.checkbox-select-item:checked');
  if(checks.length == 0 && submit != 'empty') {
    Lightbox.displayError(vufindString['bulk_noitems_advice']);
    return false;
  }
  if (submit == 'print') {
    //redirect page
    var url = path+'/Records/Home?print=true';
    for(var i=0;i<checks.length;i++) {
      url += '&id[]='+checks[i].value;
    }
    document.location.href = url;
  } else {
    $('#modal .modal-title').html(button.attr('title'));
    Lightbox.titleSet = true;
    Lightbox.submit($form, Lightbox.changeContent);
  }
  return false;
}
function registerLightboxEvents() {
  var modal = $("#modal");
  // New list
  $('#make-list').click(function() {
    var get = deparam(this.href);
    get['id'] = 'NEW';
    return Lightbox.get('MyResearch', 'EditList', get);
  });
  // New account link handler
  $('.createAccountLink').click(function() {
    var get = deparam(this.href);
    return Lightbox.get('MyResearch', 'Account', get);
  });
  $('.back-to-login').click(function() {
    Lightbox.getByUrl(Lightbox.openingURL);
    return false;
  });
  // Select all checkboxes
  $(modal).find('.checkbox-select-all').change(function() {
    $(this).closest('.modal-body').find('.checkbox-select-item').prop('checked', this.checked);
  });
  $(modal).find('.checkbox-select-item').change(function() {
    $(this).closest('.modal-body').find('.checkbox-select-all').prop('checked', false);
  });
  // Highlight which submit button clicked
  $(modal).find("form [type=submit]").click(function() {
    // Abort requests triggered by the lightbox
    $('#modal .fa-spinner').remove();
    // Remove other clicks
    $(modal).find('[type="submit"][clicked=true]').attr('clicked', false);
    // Add useful information
    $(this).attr("clicked", "true");
    // Add prettiness
    if($(modal).find('.has-error,.sms-error').length == 0 && !$(this).hasClass('dropdown-toggle')) {
      $(this).after(' <i class="fa fa-spinner fa-spin"></i> ');
    }
  });
  /**
   * Hide the header in the lightbox content
   * if it matches the title bar of the lightbox
   */
  var header = $('#modal .modal-title').html();
  var contentHeader = $('#modal .modal-body h2');
  contentHeader.each(function(i,op) {
    if (op.innerHTML == header) {
      $(op).hide();
    }
  });
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
    var recordSource = $(this).closest('.record').find('.hiddenSource').val();
    refreshCommentList(recordId, recordSource);
  });

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
  if(typeof ajaxLoadTab == "function" && !summon && recordTabs.length > 0) { // If summon, skip: about to reload anyway
    var tab = recordTabs.find('.active a').attr('id');
    ajaxLoadTab(tab);
  }
  if(typeof ajaxFLLoadTab == "function") {
    var $activeTab = $('.search_tabs .recordTabs li.active a');
    if($activeTab.length === 0) {
      $activeTab = $('.panel-heading:not(.collapsed)');
      ajaxFLLoadTab($activeTab.data('target').substring(1), true);
    } else {
      ajaxFLLoadTab($activeTab.attr('id'), true);
    }
  }
  // Refresh tag list
  if(typeof refreshTagList === "function") {
    refreshTagList(true);
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

// This is a full handler for the login form
function ajaxLogin(form) {
  Lightbox.ajax({
    url: path + '/AJAX/JSON?method=getSalt',
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
        Lightbox.ajax({
          type: 'POST',
          url: path + '/AJAX/JSON?method=login',
          dataType: 'json',
          data: params,
          success: function(response) {
            if (response.status == 'OK') {
              updatePageForLogin();
              // and we update the modal
              var params = deparam(Lightbox.lastURL);
              if (params['subaction'] == 'UserLogin') {
                Lightbox.close();
              } else {
                Lightbox.getByUrl(
                  Lightbox.lastURL,
                  Lightbox.lastPOST,
                  Lightbox.changeContent
                );
              }
            } else {
              Lightbox.displayError(response.data);
            }
          }
        });
      } else {
        Lightbox.displayError(response.data);
      }
    }
  });
}

/**
 * Functions and event handlers specific to record pages.
 */
function checkRequestIsValid(element, requestURL, requestType, blockedClass) {
  var recordId = requestURL.match(/\/Record\/([^\/]+)\//)[1];
  var vars = {}, hash;
  var hashes = requestURL.slice(requestURL.indexOf('?') + 1).split('&');

  for(var i = 0; i < hashes.length; i++)
  {
    hash = hashes[i].split('=');
    var x = hash[0];
    var y = hash[1];
    vars[x] = y;
  }
  vars['id'] = recordId;

  var url = path + '/AJAX/JSON?' + $.param({method:'checkRequestIsValid', id: recordId, requestType: requestType, data: vars});
  $.ajax({
    dataType: 'json',
    cache: false,
    url: url,
    success: function(response) {
      if (response.status == 'OK') {
        if (response.data.status) {
          $(element).removeClass('disabled')
            .attr('title', response.data.msg)
            .html('<i class="fa fa-flag"></i>&nbsp;'+response.data.msg);
        } else {
          $(element).remove();
        }
      } else if (response.status == 'NEED_AUTH') {
        $(element).replaceWith('<span class="' + blockedClass + '">' + response.data.msg + '</span>');
      }
    }
  });
}
function setUpCheckRequest() {
  $('.checkRequest').each(function(i) {
    if ($(this).hasClass('checkRequest')) {
      var isValid = checkRequestIsValid(this, this.href, 'Hold', 'holdBlocked');
    }
  });
  $('.checkStorageRetrievalRequest').each(function(i) {
    if ($(this).hasClass('checkStorageRetrievalRequest')) {
      var isValid = checkRequestIsValid(this, this.href, 'StorageRetrievalRequest',
          'StorageRetrievalRequestBlocked');
    }
  });
  $('.checkILLRequest').each(function(i) {
    if ($(this).hasClass('checkILLRequest')) {
      var isValid = checkRequestIsValid(this, this.href, 'ILLRequest',
          'ILLRequestBlocked');
    }
  });
}
function registerTabEvents(parent) {
  if(typeof parent === "undefined") {
    parent = document;
  }
  // register the record comment form to be submitted via AJAX
  $(parent).find('form.comment').unbind('submit').submit(function() {
    return registerAjaxCommentRecord(this);
  });

  setUpCheckRequest();

  // Place a Hold
  // Place a Storage Hold
  // Place an ILL Request
  $(parent).find('.placehold,.placeStorageRetrievalRequest,.placeILLRequest').unbind('click').click(function() {
    var parts = $(this).attr('href').split('?');
    parts = parts[0].split('/');
    var params = deparam($(this).attr('href'));
    params.id = parts[parts.length-2];
    params.hashKey = params.hashKey.split('#')[0]; // Remove #tabnav
    return Lightbox.get('Record', parts[parts.length-1], params, false, function(html) {
      Lightbox.checkForError(html, Lightbox.changeContent);
    });
  });
}
function registerLightboxRecordActions(parent, id) {
  if(typeof parent === "undefined") {
    parent = document;
    id = $(this).closest('.record').find('.hiddenId').val();
  }
  // Cite lightbox
  $(parent).find('.cite-record').unbind('click').click(function() {
    var params = extractClassParams(this);
    return Lightbox.get(params['controller'], 'Cite', {id: id});
  });
  // Mail lightbox
  $(parent).find('.mail-record').unbind('click').click(function() {
    var params = extractClassParams(this);
    return Lightbox.get(params['controller'], 'Email', {id: id});
  });
  // Save lightbox
  $(parent).find('.save-record').unbind('click').click(function() {
    var params = extractClassParams(this);
    return Lightbox.get(params['controller'], 'Save', {id: id});
  });
  // SMS lightbox
  $(parent).find('.sms-record').unbind('click').click(function() {
    var params = extractClassParams(this);
    return Lightbox.get(params['controller'], 'SMS', {id: id});
  });
  // Tag lightbox
  $(parent).find('.tagRecord').unbind('click').click(function() {
    var parts = this.href.split('/');
    return Lightbox.get(parts[parts.length-3], 'AddTag', {id: id});
  });
  // Place a Hold
  // Place a Storage Hold
  // Place an ILL Request
  $(parent).find('.placehold,.placeStorageRetrievalRequest,.placeILLRequest').unbind('click').click(function() {
    var parts = $(this).attr('href').split('?');
    parts = parts[0].split('/');
    var params = deparam($(this).attr('href'));
    params.id = parts[parts.length-2];
    params.hashKey = params.hashKey.split('#')[0]; // Remove #tabnav
    return Lightbox.get('Record', parts[parts.length-1], params, false, function(html) {
      Lightbox.checkForError(html, Lightbox.changeContent);
    });
  });

  $(parent).find('form.comment input[type=submit]').unbind('click').click(function() {
    if($.trim($(this).siblings('textarea').val()) == '') {
      Lightbox.displayError(vufindString['add_comment_fail_blank']);
    } else {
      registerAjaxCommentRecord(parent);
    }
    return false;
  });

  refreshCommentList(id, $(parent).find('.hiddenSource').val(), parent);
  setUpCheckRequest();
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
  $('.autocomplete').each(function (i, element) {
    $(element).typeahead(
      {
        highlight: true,
        minLength: 3
      }, {
        displayKey:'val',
        source: function(query, cb) {
          var searcher = extractClassParams(element);
          $.ajax({
            url: path + '/AJAX/JSON',
            data: {
              q:query,
              method:'getACSuggestions',
              searcher:searcher['searcher'],
              type:searcher['type'] ? searcher['type'] : $(element).closest('.searchForm').find('.searchForm_type').val()
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
  });
  $('.searchForm_type').change(function() {
    var $lookfor = $(this).closest('.searchForm').find('.searchForm_lookfor[name]');
    var query = $lookfor.val();
    $lookfor.focus().typeahead('val', '').typeahead('val', query);
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

  $('[name=bulkActionForm]').submit(function() {
    return bulkActionSubmit($(this));
  });
  $('[name=bulkActionForm]').find("[type=submit]").click(function() {
    // Abort requests triggered by the lightbox
    $('#modal .fa-spinner').remove();
    // Remove other clicks
    $(this).closest('form').find('[type="submit"][clicked=true]').attr('clicked', false);
    // Add useful information
    $(this).attr("clicked", "true");
  });

  /******************************
   * LIGHTBOX DEFAULT BEHAVIOUR *
   ******************************/
  Lightbox.addOpenAction(registerLightboxEvents);

  // Form callbacks
  Lightbox.addFormCallback('accountForm', newAccountHandler);
  Lightbox.addFormCallback('bulkDelete', function(html) {
    location.reload();
  });
  Lightbox.addFormCallback('bulkRecord', function(html) {
    Lightbox.close();
    checkSaveStatuses();
  });
  Lightbox.addFormCallback('bulkSave', function(html) {
    Lightbox.addCloseAction(updatePageForLogin);
    Lightbox.confirm(vufindString['bulk_save_success']);
  });
  Lightbox.addFormCallback('emailRecord', function() {
    Lightbox.confirm(vufindString['bulk_email_success']);
  });
  Lightbox.addFormCallback('emailSearch', function(html) {
    Lightbox.confirm(vufindString['bulk_email_success']);
  });
  Lightbox.addFormCallback('newList', Lightbox.changeContent);
  Lightbox.addFormCallback('saveRecord', function() {
    Lightbox.confirm(vufindString['bulk_save_success']);
    checkSaveStatuses();
  });
  Lightbox.addFormCallback('smsRecord', function() {
    Lightbox.confirm(vufindString['sms_success']);
  });

  // Form handlers
  Lightbox.addFormHandler('exportForm', function(evt) {
    $.ajax({
      url: path + '/AJAX/JSON?' + $.param({method:'exportFavorites'}),
      type:'POST',
      dataType:'json',
      data:Lightbox.getFormData($(evt.target)),
      success:function(data) {
        if(data.data.export_type == 'download' || data.data.needs_redirect) {
          document.location.href = data.data.result_url;
          Lightbox.close();
          return false;
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
  Lightbox.addFormHandler('feedback', function(evt) {
    var $form = $(evt.target);
    // Grabs hidden inputs
    var formSuccess     = $form.find("input#formSuccess").val();
    var feedbackFailure = $form.find("input#feedbackFailure").val();
    var feedbackSuccess = $form.find("input#feedbackSuccess").val();
    // validate and process form here
    var name  = $form.find("input#name").val();
    var email = $form.find("input#email").val();
    var comments = $form.find("textarea#comments").val();
    if (name.length == 0 || comments.length == 0) {
      Lightbox.displayError(feedbackFailure);
    } else {
      Lightbox.get('Feedback', 'Email', {}, {'name':name,'email':email,'comments':comments}, function() {
        Lightbox.changeContent('<div class="alert alert-info">'+formSuccess+'</div>');
      });
    }
    return false;
  });
  Lightbox.addFormHandler('loginForm', function(evt) {
    ajaxLogin(evt.target);
    return false;
  });

  // Feedback
  $('#feedbackLink').click(function() {
    return Lightbox.get('Feedback', 'Home');
  });
  // Help links
  $('.help-link').click(function() {
    var split = this.href.split('=');
    return Lightbox.get('Help','Home',{topic:split[1]});
  });
  // Hierarchy links
  $('.hierarchyTreeLink a').click(function() {
    var id = $(this).parent().parent().parent().find(".hiddenId")[0].value;
    var hierarchyID = $(this).parent().find(".hiddenHierarchyId")[0].value;
    return Lightbox.get('Record','AjaxTab',{id:id},{hierarchy:hierarchyID,tab:'HierarchyTree'});
  });
  // Login link
  $('#loginOptions a.modal-link').click(function() {
    return Lightbox.get('MyResearch','UserLogin');
  });
  // Email search link
  $('.mailSearch').click(function() {
    return Lightbox.get('Search','Email',{url:document.URL});
  });
  // Save record links
  $('.save-record').click(function() {
    var parts = this.href.split('/');
    return Lightbox.get(parts[parts.length-3],'Save',{id:$(this).attr('id')});
  });
});
