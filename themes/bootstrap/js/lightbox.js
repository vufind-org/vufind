/*global checkSaveStatuses, console, deparam, extractSource, hexEncode, htmlEncode, path, rc4Encrypt, refreshCommentList, vufindString */

var Lightbox = {
  /**
   * We save the URL and POST data every time we call getByUrl.
   * If we don't have a target a form submission, we use these variables
   * to replicate empty target behaviour by submitting to the current "page".
   */
  lastURL: false,
  lastPOST: false,
  shown: false, // Is the lightbox deployed?
  XHR: false, // Used for current in-progress XHR lightbox request
  openStack: [],
  closeStack: [],
  formHandlers: [],
  formCallbacks: [],

  /**********************************/
  /* ======    INTERFACE     ====== */
  /**********************************/
  /**
   * Register custom open event handlers
   */
  addOpenAction: function(func) {
    this.openStack.push(func);
  },
  /**
   * Register custom close event handlers
   */
  addCloseAction: function(func) {
    this.closeStack.push(func);
  },
  /**
   * For when you want to handle that form all by yourself
   */
  addFormHandler: function(formName, func) {
    this.formHandlers[formName] = func;
  },
  /**
   * Register a function to be called after a form submission returns
   */
  addFormCallback: function(formName, func) {
    this.formCallbacks[formName] = func;
  },
  /**
   * Cancel the previous call and create a new one
   */
  ajax: function(obj) {
    this.XHR.abort();
    this.XHR = $.ajax(obj);
  },
  /**********************************/
  /* ====== LIGHTBOX ACTIONS ====== */
  /**********************************/
  /**
   * Change the content of the lightbox.
   *
   * Hide the header if it's empty to make more
   * room for content and avoid double headers.
   */
  changeContent: function(html) {
    var header = $('#modal .modal-header');
    if(header.find('h3').html().length == 0) {
      header.css('border-bottom-width', '0');
    } else {
      header.css('border-bottom-width', '1px');
    }
    $('#modal .modal-body').html(html).modal({'show':true,'backdrop':false});
    Lightbox.openActions()
  },

  /**
   * This is the function you call to manually close the lightbox
   */
  close: function(evt) {
    $('#modal').modal('hide');
  },
  /**
   * This function is attached to the lightbox close event,
   * so it always runs when the lightbox is closed.
   */
  closeActions: function() {
    Lightbox.shown = false;
    // Clean out stack
    while(Lightbox.closeStack.length > 0) {
      var f = Lightbox.closeStack.pop();
      f();
    }
    // Abort requests triggered by the lightbox
    if(this.XHR) { this.XHR.abort(); }
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
  },
  /**
   * Call all the functions we need for when the modal loads
   */
  openActions: function() {
    for(var i=0;i<Lightbox.openStack.length;i++) {
      Lightbox.openStack[i]();
    }
  },
  /**
   * This function changes the content of the lightbox to a message.
   */
  confirm: function(message) {
    this.changeContent('<div class="alert alert-info">'+message+'</div><button class="btn" onClick="Lightbox.close()">'+vufindString['close']+'</button>');
  },

  /**
   * Insert an alert element into the top of the lightbox
   */
  displayError: function(message) {
    var alert = $('#modal .modal-body .alert');
    if(alert.length > 0) {
      $(alert).html(message);
    } else {
      $('#modal .modal-body').prepend('<div class="alert alert-error">'+message+'</div>');
    }
    $('.icon-spinner').remove();
  },

  /***********************************/
  /* ====== LIGHTBOX REQUESTS ====== */
  /***********************************/
  /**
   * This function creates an XHR request to the URL
   * and handles the response according to the callback.
   *
   * Unless there's an error, default callback is changeContent
   */
  getByUrl: function(url, post, callback) {
    if(typeof callback == "undefined") {
      // No custom handler: display return in lightbox
      callback = this.changeContent;
    }
    // If the lightbox isn't visible, fix that
    if(this.shown == false) {
      $('#modal').modal('show');
      this.shown = true;
    }
    // Create our AJAX request, store it in case we need to cancel later
    this.XHR = $.ajax({
      type:'POST',
      url:url,
      data:post,
      success:function(html) { // Success!
        callback(html);
      },
      error:function(d,e) {
        console.log(e,d); // Error reporting
        console.log(url,post);
      }
    });
    // Store current "page" context for empty targets
    this.lastURL = url;
    this.lastPOST = post;
    //this.openActions();
    return false;
  },
  /**
   * This is the friendly face to the function above.
   * It converts a Controller and Action into a URL with GET
   * and pushes the data and callback to the getByUrl
   */
  get: function(controller, action, get, post, callback) {
    // Build URL
    var url = path+'/AJAX/JSON?method=getLightbox&submodule='+controller+'&subaction='+action;
    if(typeof get !== "undefined" && get !== {}) {
      url += '&'+$.param(get);
    }
    if(typeof post == "undefined") {
      post = {};
    }
    return this.getByUrl(url, post, callback);
  },

  /**********************************/
  /* ====== FORM SUBMISSIONS ====== */
  /**********************************/
  /**
   * Call this function after a form is submitted
   */
  getFormData: function($form) {
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
          var f = currentName.substring(0,currentName.length-2);
          data[f].push(inputs[i].value);
        } else {
          data[currentName] = inputs[i].value;
        }
      }
    }
    return data;
  },
  submit: function($form, callback) {
    // Default callback is to close
    if(typeof callback == "undefined") {
      callback = this.close;
    }
    var data = this.getFormData($form);
    // If we have an action: parse
    var POST = $form.attr('method') && $form.attr('method').toUpperCase() == 'POST';
    if($form.attr('action')) {
      // Parse action location
      var action = $form.attr('action').substring($form.attr('action').indexOf(path)+path.length+1);
      var params = action.split('?');
      action = action.split('/');
      var get = params.length > 1 ? deparam(params[1]) : data['id'] ? {id:data['id']} : {};
      if(POST) {
        this.get(action[0], action[action.length-1], get, data, callback);
      } else {
        this.get(action[0], action[action.length-1], data, {}, callback);
      }
    // If not: fake context by using the previous action
    } else if(POST) {
      this.getByUrl(this.lastURL, data, callback);
    } else {
      this.getByUrl(this.lastURL, {}, callback);
    }
    $(this).find('.modal-body').html(vufindString.loading + "...");
  },

  /***********************/
  /* ====== SETUP ====== */
  /***********************/
  /**
   * The jQueries add functionality to content in the lightbox.
   *
   * It is called every time the lightbox is finished loading.
   */
  registerEvents: function() {
    var modal = $("#modal");
    // New list
    $('#make-list').click(function() {
      var parts = this.href.split('?');
      var get = deparam(parts[1]);
      get['id'] = 'NEW';
      return Lightbox.get('MyResearch', 'EditList', get);
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
      if(Lightbox.XHR) { Lightbox.XHR.abort(); }
      $('#modal .icon-spinner').remove();
      // Add useful information
      $(this).attr("clicked", "true");
      // Add prettiness
      $(this).after(' <i class="icon-spinner icon-spin"></i> ');
    });
  },
  /**
   * Prevents default submission, reroutes through ajaxSubmit
   * or a specified action based on form name. Please return false.
   *
   * Called everytime the lightbox is loaded.
   */
  registerForms: function() {
    var form = $("#modal").find('form');
    var name = $(form).attr('name');
    // Assign form handler based on name
    if(typeof name !== "undefined" && typeof Lightbox.formHandlers[name] !== "undefined") {
      $(form).submit(Lightbox.formHandlers[name]);
    // Default action, with custom callback
    } else if(typeof Lightbox.formCallbacks[name] !== "undefined") {
      $(form).submit(function(evt){
        Lightbox.submit($(evt.target), Lightbox.formCallbacks[name]);
        return false;
      });
    // Default
    } else {
      $(form).submit(function(evt){
        Lightbox.submit($(evt.target), Lightbox.close);
        return false;
      });
    }
  },
}

/**
 * Action specific form submissions
 */
// Logging in
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
};

/**
 * This is where you add click events to open the lightbox.
 * We do it here so that non-JS users still have a good time.
 */
$(document).ready(function() {
  /* --- LIGHTBOX BEHAVIOUR --- */
  // First things first
  Lightbox.addOpenAction(Lightbox.registerEvents);
  Lightbox.addOpenAction(Lightbox.registerForms);
  Lightbox.addFormHandler('loginForm', function(evt) {
    ajaxLogin(evt.target);
    return false;
  });
  Lightbox.addFormCallback('newList', Lightbox.changeContent);

  // Hijack modal forms
  $('#modal').on('shown', Lightbox.openActions);  
  // Reset Content
  $('#modal').on('hidden', Lightbox.closeActions);
  
  /* --- PAGES EVENTS THAT AFFECT THE LIGHTBOX --- */
  // Modal title
  $('.modal-link,.help-link').click(function() {
    var title = $(this).attr('title');
    if(typeof title === "undefined") {
      title = $(this).html();
    }
    $('#modal .modal-header h3').html(title);
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
  $('#loginOptions a').click(function() {
    return Lightbox.get('MyResearch','Login',{},{'loggingin':true});
  });
  // Place a Hold
  $('.placehold').click(function() {
    var params = deparam($(this).attr('href'));
    params.hashKey = params.hashKey.split('#')[0]; // Remove #tabnav
    Lightbox.addCloseAction(function(op) {
      document.location.href = path+'/MyResearch/Holds';
    });
    return Lightbox.getByUrl('Record', 'Hold', params, {});
    //return Lightbox.get('Record', 'Hold', params, {});
  });
  // Save record links
  $('.save-record').click(function() {
    var parts = this.href.split('/');
    return Lightbox.get(parts[parts.length-3],'Save',{id:$(this).attr('id')});
  });  
  // Tag lightbox
  $('#tagRecord').click(function() {
    var id = $('.hiddenId')[0].value;
    var parts = this.href.split('/');
    return Lightbox.get(parts[parts.length-3],'AddTag',{id:id});
  });
});