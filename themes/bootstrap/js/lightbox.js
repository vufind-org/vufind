/*global checkSaveStatuses, console, deparam, extractSource, getFullCartItems, hexEncode, htmlEncode, path, rc4Encrypt, refreshCommentList, vufindString */

/**
 * We save the URL and POST data every time we call getLightboxByUrl.
 * If we don't have a target a form submission, we use these variables
 * to replicate empty target behaviour by submitting to the current "page".
 */
var lastLightboxURL,lastLightboxPOST;
var lightboxShown = false; // Is the lightbox deployed?
var modalXHR; // Used for current in-progress XHR lightbox request
/**
 * This stack holds all the callbacks.
 * Callbacks are triggered on form submissions and when the lightbox is closed.
 * 
 * The only callback added in here is a refresh for Summon under ajaxLogin
 *
 * The default callback action should be closeLightbox.
 */
var callbackStack = [];

/**********************************/
/* ====== LIGHTBOX ACTIONS ====== */
/**********************************/
/**
 * Change the content of the lightbox.
 *
 * Hide the header if it's empty to make more
 * room for content and avoid double headers.
 */
function changeModalContent(html) {
  var header = $('#modal .modal-header');
  if(header.find('h3').html().length == 0) {
    header.css('border-bottom-width', '0');
  } else {
    header.css('border-bottom-width', '1px');
  }
  $('#modal .modal-body').html(html).modal({'show':true,'backdrop':false});
}

/**
 * This is the function you call to manually close the lightbox
 */
function closeLightbox() {
  $('#modal').modal('hide');
}
/**
 * This function is attached to the lightbox close event,
 * so it always runs when the lightbox is closed.
 */
function closeLightboxActions() {
  lightboxShown = false;
  // Clean out stack
  while(f = callbackStack.pop()) {
    f();
  }
  // Abort requests triggered by the lightbox
  if(modalXHR) {
    modalXHR.abort();
  }
  // Reset content so we start fresh when we open a lightbox
  $('#modal').removeData('modal');
  $('#modal').find('.modal-header h3').html('');
  $('#modal').find('.modal-body').html(vufindString.loading + "...");
  
  /**
   * Below here, we're doing content updates (sample events that affect content)
   */ 
  var recordId = $('#record_id').val();
  var recordSource = $('.hiddenSource').val();
   
  // Update the "Saved In" lists (add favorite, login)
  if(typeof checkSaveStatuses === 'function') {
    checkSaveStatuses();
  }
  
  // Update the comment list (add comment, login)
  if(typeof refreshCommentList === 'function') {
    refreshCommentList(recordId, recordSource);
  }
  // Update tag list (add tag)
  var tagList = $('#tagList');
  if (tagList.length > 0) {
      tagList.empty();
      var url = path + '/AJAX/JSON?' + $.param({method:'getRecordTags',id:recordId,'source':recordSource});
      $.ajax({
        dataType: 'json',
        url: url,
        success: function(response) {
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
  
  // Update cart items (add to cart, remove from cart, cart lightbox interface)
  var cartCount = $('#cartItems strong');
  if(cartCount.length > 0) {
    var cart = getFullCartItems();
    var id = $('#cartId');
    if(id.length > 0) {
      id = id.val();
      $('#cart-add,#cart-remove').addClass('hidden');
      if(cart.indexOf(id) > -1) {
        $('#cart-remove').removeClass('hidden');
      } else {
        $('#cart-add').removeClass('hidden');
      }
    }
    cartCount.html(cart.length);
  }
}

/**
 * Insert an alert element into the top of the lightbox
 */
function displayLightboxError(message) {
  var alert = $('#modal .modal-body .alert');
  if(alert.length > 0) {
    $(alert).html(message);
  } else {
    $('#modal .modal-body').prepend('<div class="alert alert-error">'+message+'</div>');
  }
  $('.icon-spinner').remove();
}

/***********************************/
/* ====== LIGHTBOX REQUESTS ====== */
/***********************************/
/**
 * This function creates an XHR request to the URL
 * and handles the response according to the callbackStack.
 *
 * Pop controls whether or not the callback is used immediately
 * after loading or to be stashed for later when it closes. Default true.
 */
function getLightboxByUrl(url, post, callback, pop) {
  // Pop determines if we execute the callback immediately or later
  if(typeof pop === "undefined") pop = true;
  // If we have a callback, push it to the stack
  if(typeof callback !== "undefined") {
    //console.log("Push:",callback);
    callbackStack.push(callback);
  }
  // If the lightbox isn't visible, fix that
  if(lightboxShown === false) {
    $('#modal').modal('show');
    lightboxShown = true;
  }
  // Create our AJAX request, store it in case we need to cancel later
  modalXHR = $.ajax({
    type:'POST',
    url:url,
    data:post,
    success:function(html) { // Success!
      // Check for a flash message error
      if(pop && callbackStack.length > 0 && html.indexOf("alert-error") == -1) {
        var callback = callbackStack.pop();
        callback(html);
      } else {
        changeModalContent(html);
      }
    },
    error:function(d,e) {
      console.log(url,e,d); // Error reporting
    }
  });
  // Store current "page" context for empty targets
  lastLightboxURL = url;
  lastLightboxPOST = post;
  return false;
}
/**
 * This is the friendly face to the function above.
 * It converts a Controller and Action into a URL with GET
 * and pushes the data and callback to the getLightboxByUrl
 */
function getLightbox(controller, action, get, post, callback, pop) {
  // Pop determines if we execute the callback immediately or later
  if(typeof pop === "undefined") pop = true;
  // Build URL
  var url = path+'/AJAX/JSON?method=getLightbox&submodule='+controller+'&subaction='+action;
  if(get && get !== {}) {
    url += '&'+$.param(get);
  }
  return getLightboxByUrl(url, post, callback, pop);
}

/**********************************/
/* ====== FORM SUBMISSIONS ====== */
/**********************************/
/**
 * Call this function after a form is submitted
 */
function ajaxSubmit($form, callback) {
  // Default callback is to close
  if(!callback) {
    if(callbackStack.length > 0) {
      callback = callbackStack.pop();
      //console.log("Pop:",callback);
    } else {
      callback = closeLightbox;
    }
  }
  // Gather all the data
  var inputs = $form.find('*[name]');
  var data = {};
  for(var i=0;i<inputs.length;i++) {
    var currentName = inputs[i].name;
    var array = currentName.substring(currentName.length-2) == '[]';
    if(array && !data[currentName]) {
      data[currentName] = [];
    }
    // Submit buttons
    if(inputs[i].type == 'submit') {
      if($(inputs[i]).attr('clicked') == 'true') {
        data[currentName] = inputs[i].value;
      }
    // Radio buttons
    } else if(inputs[i].type == 'radio') {
      if(inputs[i].checked) {
        if(array) {
          data[currentName][data[currentName].length] = inputs[i].value;
        } else {
          data[currentName] = inputs[i].value;
        }
      }
    // Checkboxes
    } else if($(inputs[i]).attr('type') != 'checkbox' || inputs[i].checked) {
      if(array) {
        data[currentName][data[currentName].length] = inputs[i].value;
      } else {
        data[currentName] = inputs[i].value;
      }
    }
  }
  // If we have an action: parse
  var POST = $form.attr('method') && $form.attr('method').toUpperCase() == 'POST';
  if($form.attr('action')) {
    // Parse action location
    var action = $form.attr('action').substring($form.attr('action').indexOf(path)+path.length+1);
    var params = action.split('?');
    action = action.split('/');
    var get = params.length > 1 ? deparam(params[1]) : data['id'] ? {id:data['id']} : {};
    if(POST) {
      getLightbox(action[0], action[action.length-1], get, data, callback);
    } else {
      getLightbox(action[0], action[action.length-1], data, {}, callback);
    }
  // If not: fake context by using the previous action
  } else if(POST) {
    getLightboxByUrl(lastLightboxURL, data, callback);
  } else {
    getLightboxByUrl(lastLightboxURL, {}, callback);
  }
  $(this).find('.modal-body').html(vufindString.loading + "...");
}
/**
 * Action specific form submissions
 */
// Logging in
function ajaxLogin(form) {
  $.ajax({
    url: path + '/AJAX/JSON?method=getSalt',
    dataType: 'json',
    success: function(response) {
      if (response.status == 'OK') {
        var salt = response.data;

        // get the user entered username/password
        var password = form.password.value;
        var username = form.username.value;

        // encrypt the password with the salt
        password = rc4Encrypt(salt, password);

        // hex encode the encrypted password
        password = hexEncode(password);

        // login via ajax
        $.ajax({
          type: 'POST',
          url: path + '/AJAX/JSON?method=login',
          dataType: 'json',
          data: {username:username, password:password},
          success: function(response) {
            if (response.status == 'OK') {
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
                  // If summon, queue reload
                  callbackStack.unshift(function(){document.location.reload(true);});
                }
              });
              
              // Refresh tab content
              var recordTabs = $('.recordTabs');
              if(!summon && recordTabs.length > 0) { // If summon, skip: about to reload anyway
                var tab = recordTabs.find('.active a').attr('id');
                $.ajax({
                  type:'POST',
                  url:path+'/AJAX/JSON?method=getLightbox&submodule=Record&subaction=AjaxTab&id='+recordId,
                  data:{tab:tab},
                  success:function(html) {
                    recordTabs.next('.tab-container').html(html);
                  },
                  error:function(d,e) {
                    console.log(d,e); // Error reporting
                  }
                });
              }

              // and we update the modal
              if(callbackStack.length > 0) {
                var callback = callbackStack.pop();
                //console.log("Pop:",callback);
                callback();
              } else if(lastLightboxPOST && lastLightboxPOST['loggingin']) {
                closeLightbox();
              } else {
                getLightboxByUrl(lastLightboxURL, lastLightboxPOST);
              }
            } else {
              displayLightboxError(response.data);
            }
          }
        });
      } else {
        displayLightboxError(response.data);
      }
    }
  });
}
// Cart submission
function cartSubmit($form) {
  var submit = $form.find('input[type="submit"][clicked=true]').attr('name');
  switch(submit) {
    case 'saveCart':
    case 'email':
    case 'export':
      ajaxSubmit($form, changeModalContent);
      break;
    case 'delete':
    case 'empty':
      ajaxSubmit($form, closeLightbox);
      break;
    case 'print':
      //redirect page
      var checks = $form.find('input[type="checkbox"]:checked');
      var data = {};
      for(var i=0;i<checks.length;i++) {
        data[checks[i].name] = checks[i].value;
      }
      $.ajax({
        url:path+'/Cart/PrintCart',
        data:data,
        success:function(html) {
          var newDoc = document.open("text/html", "replace");
          newDoc.write(html);
          newDoc.close();
        },
        error:function(d,e) {
          console.log(d,e); // Error reporting
        }
      });
      break;
  }
}

/***********************/
/* ====== SETUP ====== */
/***********************/
/**
 * The jQueries add functionality to content in the lightbox.
 *
 * It is called every time the lightbox is finished loading.
 */
function registerModalEvents(modal) {
  // New list
  $('#make-list').click(function() {
    var id = $(this).find('#edit-save-form input[name="id"]').val();
    var source = $(this).find('#edit-save-form input[name="source"]').val();
    var parts = this.href.split('?');
    var get = deparam(parts[1]);
    get['id'] = 'NEW';
    return getLightbox('MyResearch', 'EditList', get);
  });  
  // Select all checkboxes
  $(modal).find('.checkbox-select-all').change(function() {
    $(this).closest('.modal-body').find('.checkbox-select-item').attr('checked', this.checked);
  });
  // Highlight which submit button clicked
  $(modal).find("form input[type=submit]").click(function() {
    $(this).attr("clicked", "true");
  });
  $(modal).find("form").submit(function() {
    if($(this).find('.icon-spinner').length == 0) {
      $(this).find('*[clicked="true"]').after(' <i class="icon-spinner icon-spin"></i> ');
    }
});
}
/**
 * Prevents default submission, reroutes through ajaxSubmit
 *
 * Called everytime the lightbox is loaded.
 */
function registerModalForms(modal) {
  // Default
  $(modal).find('form').submit(function(){
    ajaxSubmit($(this), closeLightbox);
    return false;
  });
  // Action specific
  $(modal).find('form[name="cartForm"]').unbind('submit').submit(function(){
    cartSubmit($(this));
    return false;
  });
  $(modal).find('form[name="newList"]').unbind('submit').submit(function(){
    ajaxSubmit($(this), changeModalContent);
    return false;
  });
  $(modal).find('form[name="loginForm"]').unbind('submit').submit(function(){
    ajaxLogin(this);
    return false;
  });
}
/**
 * This is where you add click events to open the lightbox.
 * We do it here so that non-JS users still have a good time.
 */
$(document).ready(function() {
  // Cart lightbox
  $('#cartItems').click(function() {
    return getLightbox('Cart','Cart');
  });
  // Help links
  $('.help-link').click(function() {
    var split = this.href.split('=');
    return getLightbox('Help','Home',{topic:split[1]});
  });
  // Hierarchy links
  $('.hierarchyTreeLink a').click(function() {
    var id = $(this).parent().parent().parent().find(".hiddenId")[0].value;
    var hierarchyID = $(this).parent().find(".hiddenHierarchyId")[0].value;
    return getLightbox('Record','AjaxTab',{id:id},{hierarchy:hierarchyID,tab:'HierarchyTree'});
  });
  // Login link
  $('#loginOptions a').click(function() {
    return getLightbox('MyResearch','Login',{},{'loggingin':true});
  });
  /*/ Place a Hold
  $('.placehold').click(function() {
    var params = deparam($(this).attr('href'));
    console.log(params);
    return getLightbox('Record', 'Hold', params, {});
  });*/
  // Save record links
  $('.save-record').click(function() {
    var parts = this.href.split('/');
    return getLightbox(parts[parts.length-3],'Save',{id:$(this).attr('id')});
  });  
  // Tag lightbox
  $('#tagRecord').click(function() {
    var id = $('.hiddenId')[0].value;
    var parts = this.href.split('/');
    return getLightbox(parts[parts.length-3],'AddTag',{id:id});
  });
  /* --- LIGHTBOX BEHAVIOUR --- */
  // Hijack modal forms
  $('#modal').on('show', function() {
    registerModalForms(this);
    registerModalEvents(this);
  });  
  // Reset Content
  $('#modal').on('hidden', function() {
    closeLightboxActions();
  });
  // Modal title
  $('.modal-link,.help-link').click(function() {
    $('#modal .modal-header h3').html($(this).attr('title'));
  });
});