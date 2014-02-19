/*global checkSaveStatuses, console, deparam, extractSource, getFullCartItems, hexEncode, htmlEncode, path, rc4Encrypt, refreshCommentList, vufindString */

/**
 * We save the URL and POST data every time we call getLightboxByUrl.
 * If we don't have a target a form submission, we use these variables
 * to replicate empty target behaviour by submitting to the current "page".
 */
var lastLightboxURL,lastLightboxPOST;
var lightboxShown = false; // Is the lightbox deployed?
var modalXHR; // Used for current in-progress XHR lightbox request
var modalOpenStack = [];
var modalCloseStack = [];

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
  while(modalCloseStack.length > 0) {
    var f = modalCloseStack.pop();
    f();
  }
  // Abort requests triggered by the lightbox
  if(modalXHR) { modalXHR.abort() }
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
 * This function changes the content of the lightbox to a message.
 */
function lightboxConfirm(message) {
  changeModalContent('<div class="alert alert-info">'+message+'</div><button class="btn" data-dismiss="modal" aria-hidden="true">'+vufindString['close']+'</button>');
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
 * and handles the response according to the callback.
 *
 * Unless there's an error, default callback is changeModalContent
 */
function getLightboxByUrl(url, post, callback) {
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
      if(typeof callback !== "undefined") {
        callback(html);
      } else {
        changeModalContent(html);
      }
    },
    error:function(d,e) {
      console.log(e,d); // Error reporting
      console.log(url,post);
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
function getLightbox(controller, action, get, post, callback) {
  // Build URL
  var url = path+'/AJAX/JSON?method=getLightbox&submodule='+controller+'&subaction='+action;
  if(get && get !== {}) {
    url += '&'+$.param(get);
  }
  return getLightboxByUrl(url, post, callback);
}

/**********************************/
/* ====== FORM SUBMISSIONS ====== */
/**********************************/
/**
 * Call this function after a form is submitted
 */
function getDataFromForm($form) {
  // Gather all the data
  var inputs = $form.find('*[name]');
  var data = {};
  for(var i=0;i<inputs.length;i++) {
    var currentName = inputs[i].name;
    var array = currentName.substring(currentName.length-2) == '[]';
    if(array && !data[currentName.substring(0,currentName.length-2)]) {
      data[currentName.substring(0,currentName.length-2)] = [];
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
          var n = currentName.substring(0,currentName.length-2);
          data[n].push(inputs[i].value);
        } else {
          data[currentName] = inputs[i].value;
        }
      }
    // Checkboxes
    } else if($(inputs[i]).attr('type') != 'checkbox' || inputs[i].checked) {
      if(array) {
        var n = currentName.substring(0,currentName.length-2);
        data[n].push(inputs[i].value);
      } else {
        data[currentName] = inputs[i].value;
      }
    }
  }
  return data;
}
function ajaxSubmit($form, callback) {
  // Default callback is to close
  if(typeof callback == "undefined") {
    callback = changeModalContent;
  }
  var data = getDataFromForm($form);
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
  modalXHR = $.ajax({
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
        modalXHR = $.ajax({
          type: 'POST',
          url: path + '/AJAX/JSON?method=login',
          dataType: 'json',
          data: params,
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
                  // If summon, queue reload for when we close
                  addLightboxOnClose(function(){document.location.reload(true);});
                }
              });
              
              // Refresh tab content
              var recordTabs = $('.recordTabs');
              if(!summon && recordTabs.length > 0) { // If summon, skip: about to reload anyway
                var tab = recordTabs.find('.active a').attr('id');
                $.ajax({ // Shouldn't be cancelled, not assigned to modalXHR
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
              if(lastLightboxPOST && lastLightboxPOST['loggingin']) {
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
    var parts = this.href.split('?');
    var get = deparam(parts[1]);
    get['id'] = 'NEW';
    return getLightbox('MyResearch', 'EditList', get);
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
    if(modalXHR) { modalXHR.abort() }
    $(this).remove('.icon-spinner');
    // Add useful information
    $(this).attr("clicked", "true");
    // Add prettiness
    $(this).after(' <i class="icon-spinner icon-spin"></i> ');
  });
}

/**
 * Prevents default submission, reroutes through ajaxSubmit
 * or a specified action based on form name. Please return false.
 *
 * Called everytime the lightbox is loaded.
 */
var modalFormHandlers = {
  loginForm:
    function() {
      ajaxLogin(this);
      return false;
    },
};
function registerModalForms(modal) {
  var $form = $(modal).find('form');
  // Assign form handler based on name
  if(typeof modalFormHandlers[$form.attr('name')] !== "undefined") {
    $form.submit(modalFormHandlers[$form.attr('name')]);
  } else {
    // Default
    $(modal).find('form').submit(function(){
      ajaxSubmit($(this), changeModalContent);
      return false;
    });
  }
}
/**
 * Register custom open event handlers
 */
function addLightboxOnOpen(func) {
  modalOpenStack.push(func);
}
/**
 * Register custom close event handlers
 */
function addLightboxOnClose(func) {
  modalCloseStack.push(func);
}
/**
 * Register custom form handlers
 */
function addLightboxFormHandler(formName, func) {
  modalFormHandlers[formName] = func;
}
/**
 * This is where you add click events to open the lightbox.
 * We do it here so that non-JS users still have a good time.
 */
$(document).ready(function() {
  /* --- LIGHTBOX BEHAVIOUR --- */
  // First things first
  addLightboxOnOpen(registerModalEvents);
  addLightboxOnOpen(registerModalForms);
  // Hijack modal forms
  $('#modal').on('show', function() {
    for(var i=0;i<modalOpenStack.length;i++) {
      modalOpenStack[i](this);
    }
  });  
  // Reset Content
  $('#modal').on('hidden', function() {
    closeLightboxActions();
  });
  // Modal title
  $('.modal-link,.help-link').click(function() {
    var title = $(this).attr('title');
    if(typeof title === "undefined") {
      title = $(this).html();
    }
    $('#modal .modal-header h3').html(title);
  });
  
  /* --- PAGES EVENTS THAT AFFECT THE LIGHTBOX --- */
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
  // Place a Hold
  $('.placehold').click(function() {
    var params = deparam($(this).attr('href'));
    params.hashKey = params.hashKey.split('#')[0]; // Remove #tabnav
    return getLightbox('Record', 'Hold', params, {}, function(op) {
      document.location.href = path+'/MyResearch/Holds';
    }, false);
  });
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
});