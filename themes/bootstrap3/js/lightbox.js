/*global checkSaveStatuses, console, deparam, isset, path, Recaptcha, vufindString */

var Lightbox = {
  /**
   * We save the URL and POST data every time we call getByUrl.
   * If we don't have a target a form submission, we use these variables
   * to replicate empty target behaviour by submitting to the current "page".
   */
  shown: false,      // Is the lightbox deployed?
  XHR: false,        // Used for current in-progress XHR lightbox request
  formHandlers: [],  // Full custom handlers for forms; by name
  formCallbacks: [], // Custom functions for forms, called after .submit(); by name
  callOptions: false,

  init: function() {
    this.elem = $('#modal');
    this.body = $('#modal .modal-body');
    this.header = $('#modal .modal-title');
    this.dispatch('Lightbox.init');
  },
  open: function(options) {
    //console.log(options);
    if(isset(options.title)) {
      this.header.text(options.title);
    }
    if(isset(options.url)) {
      this.callOptions = options;
      this.getByUrl(options.url, options);
    } else if(isset(options.controller) && isset(options.action)) {
      options.url = this.convertToUrl(options.controller, options.action, options.get);
      this.open(options);
    } else if(isset(options.confirm)) {
      Lightbox.changeContent(this.formatMessage(options.confirm, 'info', options), options.onOpen);
    } else if(isset(options.error) || isset(options.flash)) {
      if(isset(options.flash)) {
        options.error = options.flash;
        options.html = true;
      }
      Lightbox.changeContent(this.formatMessage(options.error, 'danger', options), options.onOpen);
    } else if(isset(options.html)) {
      Lightbox.changeContent(options.html, options.onOpen);
    } else {
      return true;
    }
    if(this.shown == false) {
      this.elem.modal('show');
      this.shown = true;
    }
    return false;
  },
  close: function() {
    Lightbox.shown = false;
    Lightbox.elem.removeClass('in');
    Lightbox.body.html('Loading...');
    Lightbox.header.html('');
    if(isset(Lightbox.callOptions.onClose)) {
      Lightbox.callOptions.onClose();
      delete Lightbox.callOptions.onClose;
    }
    Lightbox.dispatch('Lightbox.close');
    Lightbox.callOptions = false;
  },
  formatMessage: function(message, type, options) {
    var content = '<div class="alert alert-'+type+'">'+message+'</div>';
    if(isset(options.html) && !(this.body.html() == vufindString.loading + "...")) {
      if(true == options.html) {
        content += this.body.html();
      } else {
        content += options.html;
      }
    } else {
      content += '<button class="btn btn-default" onClick="Lightbox.close()">'+vufindString['close']+'</button>';
    }
    return content;
  },

  /**
   * We store all the ajax calls in case we need to cancel.
   * This function cancels the previous call and creates a new one.
   */
  ajax: function(obj) {
    if(this.XHR) {
      this.XHR.abort();
    }
    this.XHR = $.ajax(obj);
  },
  /**
   * Change the content of the lightbox.
   *
   * Hide the header if it's empty to make more
   * room for content and avoid double headers.
   */
  titleSet: true,
  changeContent: function(html, callback) {
    if(!(Lightbox.titleSet || isset(Lightbox.callOptions.title))) {
      var h2 = html.match(/<h2>([^<]*)<\/h2>/);
      var pLead = html.match(/<p class="lead[^>]*>([^<]*)<\/p>/);
      if(h2) {
        Lightbox.header.html(h2[1]);
      } else if(pLead) {
        Lightbox.header.html(pLead[1]);
      }
      Lightbox.titleSet = false;
    }
    if(Lightbox.header.text().length == 0) {
      Lightbox.header.css('border-bottom-width', '0');
    } else {
      Lightbox.header.css('border-bottom-width', '1px');
    }
    Lightbox.body.html(html);
    if(isset(callback)) {
      callback(html);
    }
    Lightbox.registerForms();
    Lightbox.dispatch('Lightbox.open');
  },
  convertToUrl: function(controller, action, get) {
    var url = path+'/AJAX/JSON?method=getLightbox&submodule='+controller+'&subaction='+action;
    if(isset(get)) {
      url += '&'+$.param(get);
    }
    return url;
  },
  dispatch: function(str) {
    var evt = document.createEvent("Event");
    evt.initEvent(str, true, false);
    document.dispatchEvent(evt);
  },
  /**
   * This function creates an XHR request to the URL
   * and handles the response according to the callback.
   *
   * Default callback is changeContent
   */
  getByUrl: function(url, options) {
    // Create our AJAX request, store it in case we need to cancel later
    this.ajax({
      type:'POST',
      url:url,
      data:isset(options.post) ? options.post : {},
      success:function(html) { // Success!
        if(isset(options.onResponse)) {
          options.onResponse(html, options.onOpen);
        } else {
          Lightbox.changeContent(html, options.onOpen);
        }
        Lightbox.body.find('.fa.fa-spinner').remove();
      },
      error:function(d,e) {
        var error = "";
        if (d.status == 200) {
          try {
            error = JSON.parse(d.responseText).data;
          } catch(x) {
            error = d.responseText;
          }
        } else if(d.status > 0) {
          error = d.statusText+' ('+d.status+')';
        }
        Lightbox.open({error:error, html:true});
        console.log(url, options.post);
      }
    });
    // Store current "page" context for empty targets
    if(this.openingURL === false) {
      this.openingURL = url;
    }
    this.lastURL = url;
    this.lastPOST = options.post;
    //this.openActions();
    return false;
  },

  /**********************************/
  /* ====== LIGHTBOX ACTIONS ====== */
  /**********************************/
  /**
   * Regexes a piece of html to find an error alert
   * If one is found, display it
   *
   * If one is not found, return html to a success callback function
   */
  checkForError: function(html, success, type) {
    if(typeof type === "undefined") {
      type = "danger";
    }
    var divPattern = '<div class="alert alert-'+type+'">';
    var fi = html.indexOf(divPattern);
    if(fi > -1) {
      var li = html.indexOf('</div>', fi+divPattern.length);
      Lightbox.open({flash:html.substring(fi+divPattern.length, li).replace(/^[\s<>]+|[\s<>]+$/g, '')});
    } else {
      success(html);
    }
  },
  /**********************************/
  /* ====== FORM SUBMISSIONS ====== */
  /**********************************/
  /**
   * Returns all the input values from a form as an associated array
   *
   * This function takes a jQuery wrapped form
   * $(event.target) for example
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
  /**
   * This function adds submission events to forms loaded inside the lightbox
   *
   * First, it will check for custom handlers, for those who want to handle everything.
   *
   * Then, it will check for custom form callbacks. These will be added to an anonymous
   * function that will call Lightbox.submit with the form and the callback.
   *
   * Finally, if nothing custom is setup, it will add the default function which
   * calls Lightbox.submit with a callback to close if there are no errors to display.
   *
   * This is a default open action, so it runs every time changeContent
   * is called and the 'shown' lightbox event is triggered
   */
  registerForms: function() {
    var $form = Lightbox.elem.find('form');
    $form.validator();
    var name = $form.attr('name');
    // Assign form handler based on name
    if(isset(name) && isset(Lightbox.formHandlers[name])) {
      $form.submit(Lightbox.formHandlers[name]);
    // Default action, with custom callback
    } else if(isset(Lightbox.formCallbacks[name])) {
      $form.submit(function(evt){
        if(evt.isDefaultPrevented()) {
          $('.fa.fa-spinner', evt.target).remove();
          return false;
        }
        Lightbox.submit($(evt.target), Lightbox.formCallbacks[name]);
        return false;
      });
    // Default
    } else {
      $form.unbind('submit').submit(function(evt){
        if(evt.isDefaultPrevented()) {
          $('.fa.fa-spinner', evt.target).remove();
          return false;
        }
        Lightbox.submit($(evt.target), function(html){
          Lightbox.checkForError(html, Lightbox.close);
        });
        return false;
      });
    }
  },
  /**
   * The default, automatic form submission
   *
   * This function gleans all the information in a form from the function above
   * Then it uses the action="" attribute of the form to figure out where to send the data
   * and the method="" attribute to send it the proper way
   *
   * In the wild, forms without an action="" are submitted to the current URL.
   * In the case where we have a form with no action in the lightbox,
   * we emulate that behaviour by submitting the last URL loaded through
   * .getByUrl, stored in lastURL in the Lightbox object.
   */
  submit: function($form, callback) {
    // Default callback is to close
    if(!isset(callback)) {
      callback = this.close;
    }
    var data = this.getFormData($form);
    // If we have an action: parse
    var POST = $form.attr('method') && $form.attr('method').toUpperCase() == 'POST';
    var options = POST ? {post:data, onResponse:callback} : {onResponse:callback};
    if($form.attr('action')) {
      // Parse action location
      var action = $form.attr('action').substring($form.attr('action').indexOf(path)+path.length+1);
      var params = action.split('?');
      action = action.split('/');
      var get = params.length > 1 ? deparam(params[1]) : data['id'] ? {id:data['id']} : {};
      this.getByUrl(this.convertToUrl(action[0], action[action.length-1], get), options);
    // If not: fake context by using the previous action
    } else {
      this.getByUrl(this.callOptions.url, options);
    }
    this.body.html(vufindString.loading + "...");
  },
  /**
   * For when you want to handle that form all by yourself
   *
   * We recommend using the getLightbox action in the AJAX Controller
   * to ensure you get a lightbox formatted page.
   *
   * If your handler doesn't return false, the form will submit the normal way,
   * with all normal behavior that goes with: redirection, etc.
   */
  addFormHandler: function(formName, func) {
    this.formHandlers[formName] = func;
  },
  /**
   * Register a function to be called when a form submission succeeds
   *
   * We add error checking by default, you never know when error blocks will strike.
   * Passing false to expectsError turns this off. Errors are inserted above *current* content.
   */
  addFormCallback: function(formName, func, expectsError) {
    if(!isset(expectsError) || expectsError) {
      this.formCallbacks[formName] = function(html) {
        Lightbox.checkForError(html, func);
      };
    } else {
      this.formCallbacks[formName] = func;
    }
  },
};

/**
 * This is where you add click events to open the lightbox.
 * We do it here so that non-JS users still have a good time.
 */
$.fn.ready(function() {
  /**
   * Hook into the Bootstrap close event
   *
   * Yes, the secret's out, our beloved Lightbox is a modal
   */
  Lightbox.init();
  $('#modal').on('hidden.bs.modal', Lightbox.close);
  /**
   * If a link with the class .modal-link triggers the lightbox,
   * look for a title="" to use as our lightbox title.
   */
  $('.modal-link,.help-link').click(function() {
    var title = $(this).attr('title');
    if(!isset(title)) {
      title = $(this).html();
    }
    $('#modal .modal-title').html(title);
    Lightbox.titleSet = true;
  });
});