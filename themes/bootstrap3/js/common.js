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
  if (typeof str === "undefined") return [];
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
  return String(myid).replace(/[!"#$%&'()*+,.\/:;<=>?@[\\\]^`{|}~]/g, "\\$&");
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

// Lightbox
/*
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
    var parts = this.href.split('?');
    var get = deparam(parts[1]);
    return Lightbox.get('MyResearch', 'Account', get);
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
    $('#modal .fa-spinner').remove();
    // Add useful information
    $(this).attr("clicked", "true");
    // Add prettiness
    $(this).after(' <i class="fa fa-spinner fa-spin"></i> ');
  });
  /**
   * Hide the header in the lightbox content
   * if it matches the title bar of the lightbox
   */
  var header = $('#modal .modal-title').html();
  var contentHeader = $('#modal .modal-body .lead');
  if(contentHeader.length == 0) {
    contentHeader = $('#modal .modal-body h2');
  }
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
    ajaxLoadTab(tab);
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
  var searcher = extractClassParams('.autocomplete');
  var autocompleteEngine = new Bloodhound({
    name: 'search-suggestions',
    remote: {
      url: path + '/AJAX/JSON?q=%QUERY',
      ajax: {
        data: {
          method:'getACSuggestions',
          type:$('#searchForm_type').val(),
          searcher:searcher['searcher']
        },
        dataType:'json'
      },
      filter: function(json) {
        if (json.status == 'OK' && json.data.length > 0) {
          var datums = [];
          for (var i=0;i<json.data.length;i++) {
            datums.push({val:json.data[i]});
          }
          return datums;
        } else {
          return [];
        }
      }
    },
    datumTokenizer: Bloodhound.tokenizers.obj.whitespace('val'),
    queryTokenizer: Bloodhound.tokenizers.whitespace
  });
  autocompleteEngine.initialize();
  $('.autocomplete').typeahead(
    {
      highlight: true,
      minLength: 3,
    }, {
      displayKey:'val',
      source: autocompleteEngine.ttAdapter()
    }
  );

  // Checkbox select all
  $('.checkbox-select-all').click(function(event) {
    if(this.checked) {
      $(this).closest('form').find('.checkbox-select-item').each(function() {
        this.checked = true;
      });
    } else {
      $(this).closest('form').find('.checkbox-select-item').each(function() {
        this.checked = false;
      });
    }
  });

  // handle QR code links
  $('a.qrcodeLink').click(function() {
    if ($(this).hasClass("active")) {
      $(this).html(vufindString.qrcode_show).removeClass("active");
    } else {
      $(this).html(vufindString.qrcode_hide).addClass("active");
    }
    $(this).next('.qrcode').toggleClass('hidden');
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
  Lightbox.addFormCallback('accountForm', newAccountHandler);
  Lightbox.addFormCallback('emailSearch', function(html) {
    Lightbox.confirm(vufindString['bulk_email_success']);
  });
  Lightbox.addFormCallback('saveRecord', function(html) {
    Lightbox.close();
    checkSaveStatuses();
  });
  Lightbox.addFormCallback('bulkRecord', function(html) {
    Lightbox.close();
    checkSaveStatuses();
  });
  Lightbox.addFormHandler('feedback', function(evt) {
    $form = $(evt.target);
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