/*global checkSaveStatuses, console, extractSource, hexEncode, Lightbox, path, rc4Encrypt, refreshCommentList, vufindString */

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
  var request = {};
  var pairs = url.substring(url.indexOf('?') + 1).split('&');
  for (var i = 0; i < pairs.length; i++) {
    var pair = pairs[i].split('=');
    var name = decodeURIComponent(pair[0]);
    if(name.length == 0) {
      continue;
    }
    if(name.substring(name.length-2) == '[]') {
      name = name.substring(0,name.length-2);
      if(!request[name]) {
        request[name] = [];
      }
      request[name].push(decodeURIComponent(pair[1]));
    } else {
      request[name] = decodeURIComponent(pair[1]);
    }
  }
  return request;
}

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

/* --- COMMON AND DEFAULT LIGHTBOX FUNCTIONS --- */
/**
 * This function adds jQuery events to elements in the lightbox
 *
 * This is a default open action, so it runs every time changeContent
 * is called and the 'shown' lightbox event is triggered
 */
function registerLightboxEvents() {
  var modal = $("#modal");
  // New list
  $('#make-list').click(function() {
    var parts = this.href.split('?');
    var get = deparam(parts[1]);
    get['id'] = 'NEW';
    return Lightbox.get('MyResearch', 'EditList', get);
  });
  // New account link handler
  $('.createAccountLink').click(function() {
    return Lightbox.get('MyResearch', 'Account');
  });
  // Select all checkboxes
  $(modal).find('.checkbox-select-all').change(function() {
    $(this).closest('.modal-body').find('.checkbox-select-item').attr('checked', this.checked);
  });
  $(modal).find('.checkbox-select-item').change(function() {
    if(!this.checked) { // Uncheck all selected if one is unselected
      $(this).closest('.modal-body').find('.checkbox-select-all').attr('checked', false);
    }
  });
  // Highlight which submit button clicked
  $(modal).find("form input[type=submit]").click(function() {
    // Abort requests triggered by the lightbox
    $('#modal .icon-spinner').remove();
    // Add useful information
    $(this).attr("clicked", "true");
    // Add prettiness
    $(this).after(' <i class="icon-spinner icon-spin"></i> ');
  });
  /**
   * Hide the header in the lightbox content
   * if it matches the title bar of the lightbox
   */
  var header = $('#modal .modal-header h3').html();
  $('#modal .modal-body .lead').each(function(i,op) {
    if (op.innerHTML == header) {
      $(op).hide();
    }
  });
  $('#modal .collapse').on('hidden', function(e){ e.stopPropagation(); });
}
function updatePageForLogin()
{
  // Hide "log in" options and show "log out" options:
  $('#loginOptions').hide();
  $('.logoutOptions').show();

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
    $.ajax({ // Shouldn't be cancelled, not assigned to XHR
      type:'POST',
      url:path+'/AJAX/JSON?method=get&submodule=Record&subaction=AjaxTab&id='+recordId,
      data:{tab:tab},
      success:function(html) {
        recordTabs.next('.tab-container').html(html);
      },
      error:function(d,e) {
        console.log(d,e); // Error reporting
      }
    });
  }
}
/**
 * This is a full handler for the login form
 */
function ajaxLogin(form) {
  Lightbox.ajax({
    url: path + '/AJAX/JSON?method=getSalt',
    dataType: 'json',
    success: function(response) {
      if (response.status == 'OK') {
        var salt = response.data;

        // get the user entered password
        var password = form.password.value;

        // encrypt the password with the salt
        password = rc4Encrypt(salt, password);

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
        Lightbox.ajax({
          type: 'POST',
          url: path + '/AJAX/JSON?method=login',
          dataType: 'json',
          data: params,
          success: function(response) {
            if (response.status == 'OK') {
              updatePageForLogin();
              // and we update the modal
              if(Lightbox.lastPOST && Lightbox.lastPOST['loggingin']) {
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

/* --- BOOTSTRAP LIBRARY TWEAKS --- */
// Prevent typeahead highlighting
$.fn.typeahead.Constructor.prototype.render = function(items) {
  var that = this;

  items = $(items).map(function (i, item) {
    i = $(that.options.item).attr('data-value', item);
    i.find('a').html(that.highlighter(item));
    return i[0];
  });

  this.$menu.html(items);
  return this;
};
// Enter without highlight does not delete the query
$.fn.typeahead.Constructor.prototype.select = function () {
  var val = this.$menu.find('.active');
  if (val.length > 0) {
    val = val.attr('data-value');
  } else {
    val = this.$element.val();
  }
  this.$element.val(this.updater(val)).change();
  return this.hide();
};

$(document).ready(function() {
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
  var autoCompleteRequest, autoCompleteTimer;
  $('.autocomplete').typeahead({
    minLength:3,
    source:function(query, process) {
      clearTimeout(autoCompleteTimer);
      if(autoCompleteRequest) {
        autoCompleteRequest.abort();
      }
      var searcher = extractClassParams('.autocomplete');
      autoCompleteTimer = setTimeout(function() {
        autoCompleteRequest = $.ajax({
          url: path + '/AJAX/JSON',
          data: {method:'getACSuggestions',type:$('#searchForm_type').val(),searcher:searcher['searcher'],q:query},
          dataType:'json',
          success: function(json) {
            if (json.status == 'OK' && json.data.length > 0) {
              process(json.data);
            } else {
              process([]);
            }
          }
        });
      }, 500); // Delay request submission
    },
    updater : function(item) { // Submit on update
      // console.log(this.$element[0].form.submit);
      this.$element[0].value = item;
      this.$element[0].form.submit();
      return item;
    }
  });

  // Checkbox select all
  $('.checkbox-select-all').change(function() {
    $(this).closest('form').find('.checkbox-select-item').attr('checked', this.checked);
  });

  // handle QR code links
  $('a.qrcodeLink').click(function() {
    if ($(this).hasClass("active")) {
      $(this).html(vufindString.qrcode_show).removeClass("active");
    } else {
      $(this).html(vufindString.qrcode_hide).addClass("active");
    }
    $(this).next('.qrcode').toggle();
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

  // Collapsing facets
  $('.sidebar .collapsed .nav-header').click(function(){$(this).parent().toggleClass('open');});

  // Advanced facets
  setupOrFacets();

  /******************************
   * LIGHTBOX DEFAULT BEHAVIOUR *
   ******************************/
  Lightbox.addOpenAction(registerLightboxEvents);
  Lightbox.addFormCallback('newList', Lightbox.changeContent);
  Lightbox.addFormHandler('loginForm', function(evt) {
    ajaxLogin(evt.target);
    return false;
  });
  Lightbox.addFormCallback('accountForm', function() {
    var params = deparam(Lightbox.openingURL);
    if (params['subaction'] != 'Login') {
      Lightbox.getByUrl(Lightbox.openingURL);
      Lightbox.openingURL = false;
    } else {
      Lightbox.close();
    }
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
    return Lightbox.get('MyResearch','UserLogin',{},{'loggingin':true});
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
  Lightbox.addFormCallback('emailSearch', function(x) {
    Lightbox.confirm(vufindString['bulk_email_success']);
  });
});