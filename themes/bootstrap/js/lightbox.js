/*global deparam, htmlEncode, path, rc4Encrypt, refreshCommentList, vufindString */

var lastLightboxURL,lastLightboxPOST; // Replacement for empty form actions
var lightboxShown = false; // is the lightbox deployed?
var modalXHR; // Used for current in-progress XHR lightbox request

/**********************************/
/* ====== LIGHTBOX ACTIONS ====== */
/**********************************/
// Cart actions based on submission
// Change the content of the lightbox
function changeModalContent(html) {
  $('#modal .modal-body').html(html);
  registerModalEvents($('#modal'));
  registerModalForms($('#modal'));
}
// Close the lightbox and run update functions
function closeLightbox() {
  if(modalXHR) {
    modalXHR.abort();
  }
  lightboxShown = false;
  $('#modal').modal('hide');
  // Reset content
  $('#modal').removeData('modal');
  $('#modal').find('.modal-body').html(vufindString.loading + "...");
  // Perform checks to update the page
  if(checkSaveStatuses) {
    checkSaveStatuses();
  }
  // Update cart items
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
    cartCount.html(cart.length)
  }
}
// Make an error box appear in the lightbox, or insert one
function displayLightboxError(message) {
  var alert = $('#modal .modal-body .alert');
  if(alert.length > 0) {
    $(alert).html(message);
  } else {
    $('#modal .modal-body').prepend('<div class="alert alert-error">'+message+'</div>');
  }
}

/****************************/
/* ====== GET LIGHTBOX ====== */
/****************************/
// AJAX the content and put it into a lightbox
// Callback if necessary
function getLightboxByUrl(url, post, callback) {
  if(!lightboxShown) {
    $('#modal').modal('show');
    lightboxShown = true;
  }
  modalXHR = $.ajax({
    type:'POST',
    url:url,
    data:post,
    success:function(html) {
      // Check for a flash message error
      if(typeof callback === "function" && html.indexOf("alert-error") == -1) {
        callback(html);
      } else {
        changeModalContent(html);
      }
    },
    error:function(d,e) {
      console.log(d,e);
    }
  });
  lastLightboxURL = url;
  lastLightboxPOST = post;
  return false;
}
// Get a template and display it in a lightbox
function getLightbox(controller, action, get, post, callback) {
  var url = path+'/AJAX/JSON?method=getLightbox&submodule='+controller+'&subaction='+action;
  if(get && get !== {}) {
    url += '&'+$.param(get);
  }
  return getLightboxByUrl(url, post, callback);
}

/****************************/
/* ====== AJAX MAGIC ====== */
/****************************/
// Submit a form via AJAX and show the result
function ajaxSubmit($form, callback) {
  // Default callback is to close
  if(!callback) {
    callback = closeLightbox;
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
// AJAX action specifically for logging in (encrypted)
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

              // Update user save statuses if the current context calls for it:
              if (typeof(checkSaveStatuses) == 'function') {
                checkSaveStatuses();
              }

              // refresh the comment list so the "Delete" links will show
              $('.commentList').each(function(){
                var recordId = $('#record_id').val();
                var recordSource = extractSource($('#record'));
                refreshCommentList(recordId, recordSource);
              });

              // and we update the modal
              if(lastLightboxPOST['loggingin']) {
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
// AJAX action specifically for the cart and its many submit buttons
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
          console.log(d,e);
        }
      });
      break;
  }
}

/***********************/
/* ====== SETUP ====== */
/***********************/
// Checkbox actions and link hijacking
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
    $(this).find('*[clicked="true"]').after(' <i class="icon-spinner icon-spin"></i> ');
});
}
// Prevent forms from submitting in the lightbox
// Go through AJAX instead
function registerModalForms(modal) {
  $(modal).find('form').submit(function(){
    ajaxSubmit($(this), closeLightbox);
    return false;
  });
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
// Default lightbox behaviour
// Tell links to open lightboxes
$(document).ready(function() {
  // Hijack modal forms
  $('#modal').on('show', function() {
    registerModalForms(this);
    registerModalEvents(this);
  });  
  // Reset Content
  $('#modal').on('hidden', function() {
    closeLightbox();
  });
  /* --- MODAL LINK EVENTS --- */
  // Save record links
  $('.save-record').click(function() {
    var parts = this.href.split('/');
    return getLightbox(parts[parts.length-3],'Save',{id:$(this).attr('id')});
  });  
  // Cart lightbox
  $('#cartItems').click(function() {
    return getLightbox('Cart','Cart');
  });
  // Hierarchy links
  $('.hierarchyTreeLink a').click(function() {
    var id = $(this).parent().parent().parent().find(".hiddenId")[0].value;
    var hierarchyID = $(this).parent().find(".hiddenHierarchyId")[0].value;
    return getLightbox('Record','AjaxTab',{id:id},{hierarchy:hierarchyID,tab:'HierarchyTree'});
  });
  // Help links
  $('.help-link').click(function() {
    var split = this.href.split('=');
    return getLightbox('Help','Home',{topic:split[1]});
  });
  // Login link
  $('#loginOptions a').click(function() {
    return getLightbox('MyResearch','Login',{},{'loggingin':true});
  });
  // Modal title
  $('.modal-link,.help-link').click(function() {
    $('#modal .modal-header h3').html($(this).attr('title'));
  });
});