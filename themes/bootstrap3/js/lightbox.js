/*global grecaptcha, recaptchaOnLoad, resetCaptcha, VuFind */
VuFind.register('lightbox', function Lightbox() {
  // State
  var _originalUrl = false;
  var _currentUrl = false;
  var _lightboxTitle = '';
  var refreshOnClose = false;
  var _modalParams = {};
  // Elements
  var _modal, _modalBody, _clickedButton = null;
  // Utilities
  function _storeClickedStatus() {
    _clickedButton = this;
  }
  function _html(content) {
    _modalBody.html(content);
    // Set or update title if we have one
    if (_lightboxTitle !== '') {
      var h2 = _modalBody.find('h2:first-child');
      if (h2.length === 0) {
        h2 = $('<h2/>').prependTo(_modalBody);
      }
      h2.text(_lightboxTitle);
      _lightboxTitle = '';
    }
    _modal.modal('handleUpdate');
  }
  function _emit(msg, _details) {
    var details = _details || {};
    var event;
    try {
      event = new CustomEvent(msg, {
        detail: details,
        bubbles: true,
        cancelable: true
      });
    } catch (e) {
      // Fallback to document.createEvent() if creating a new CustomEvent fails (e.g. IE 11)
      event = document.createEvent('CustomEvent');
      event.initCustomEvent(msg, true, true, details);
    }
    return document.dispatchEvent(event);
  }

  // Public: Present an alert
  function showAlert(message, _type) {
    var type = _type || 'info';
    _html('<div class="flash-message alert alert-' + type + '">' + message + '</div>'
        + '<button class="btn btn-default" data-dismiss="modal">' + VuFind.translate('close') + '</button>');
    _modal.modal('show');
  }
  function flashMessage(message, _type) {
    var type = _type || 'info';
    _modalBody.find('.flash-message,.fa.fa-spinner').remove();
    _modalBody.find('h2:first-of-type')
      .after('<div class="flash-message alert alert-' + type + '">' + message + '</div>');
  }
  function close() {
    _modal.modal('hide');
  }

  /**
   * Update content
   *
   * Form data options:
   *
   * data-lightbox-ignore = do not submit this form in lightbox
   */
  // function declarations to avoid style warnings about circular references
  var _constrainLink;
  var _formSubmit;
  function render(content) {
    if (!content.match) {
      return;
    }
    // Isolate successes
    var htmlDiv = $('<div/>').html(content);
    var alerts = htmlDiv.find('.flash-message.alert-success:not([data-lightbox-ignore])');
    if (alerts.length > 0) {
      var msgs = alerts.toArray().map(function getSuccessHtml(el) {
        return el.innerHTML;
      }).join('<br/>');
      var href = alerts.find('.download').attr('href');
      if (typeof href !== 'undefined') {
        location.href = href;
        close();
      } else {
        showAlert(msgs, 'success');
      }
      return;
    }
    // Deframe HTML
    var finalHTML = content;
    if (content.match('<!DOCTYPE html>')) {
      finalHTML = htmlDiv.find('.main > .container').html();
    }
    // Fill HTML
    _html(finalHTML);
    VuFind.modal('show');
    // Attach capturing events
    _modalBody.find('a').click(_constrainLink);
    // Handle submit buttons attached to a form as well as those in a form. Store
    // information about which button was clicked here as checking focused button
    // doesn't work on all browsers and platforms.
    _modalBody.find('[type=submit]').click(_storeClickedStatus);

    var forms = _modalBody.find('form:not([data-lightbox-ignore])');
    for (var i = 0; i < forms.length; i++) {
      $(forms[i]).on('submit', _formSubmit);
    }
    // Select all checkboxes
    $('#modal').find('.checkbox-select-all').change(function lbSelectAllCheckboxes() {
      $(this).closest('.modal-body').find('.checkbox-select-item').prop('checked', this.checked);
    });
    $('#modal').find('.checkbox-select-item').change(function lbSelectAllDisable() {
      $(this).closest('.modal-body').find('.checkbox-select-all').prop('checked', false);
    });
    // Recaptcha
    recaptchaOnLoad();
  }

  var _xhr = false;
  // Public: Handle AJAX in the Lightbox
  function ajax(obj) {
    if (_xhr !== false) {
      return;
    }
    if (_originalUrl === false) {
      _originalUrl = obj.url;
    }
    // Add lightbox GET parameter
    if (!obj.url.match(/layout=lightbox/)) {
      var parts = obj.url.split('#');
      obj.url = parts[0].indexOf('?') < 0
        ? parts[0] + '?'
        : parts[0] + '&';
      obj.url += 'layout=lightbox&lbreferer=' + encodeURIComponent(_currentUrl);
      obj.url += parts.length < 2 ? '' : '#' + parts[1];
    }
    _xhr = $.ajax(obj);
    _xhr.always(function lbAjaxAlways() { _xhr = false; })
      .done(function lbAjaxDone(content, status, jq_xhr) {
        var errorMsgs = [];
        if (jq_xhr.status !== 205) {
          var testDiv = $('<div/>').html(content);
          errorMsgs = testDiv.find('.flash-message.alert-danger:not([data-lightbox-ignore])');
          // Place Hold error isolation
          if (obj.url.match(/\/Record\/.*(Hold|Request)\?/)) {
            if (errorMsgs.length && testDiv.find('.record').length) {
              var msgs = errorMsgs.toArray().map(function getAlertHtml(el) {
                return el.innerHTML;
              }).join('<br/>');
              showAlert(msgs, 'danger');
              return false;
            }
          }
        }
        // Close the lightbox after deliberate login provided that:
        // - is a form
        // - catalog login for holds
        // - or that matches login/create account
        // - not a failed login
        if (
          obj.method && (
            obj.url.match(/catalogLogin/)
            || obj.url.match(/MyResearch\/(?!Bulk|Delete|Recover)/)
          ) && errorMsgs.length === 0
        ) {

          var eventResult = _emit('VuFind.lightbox.login', {
            originalUrl: _originalUrl,
            formUrl: obj.url
          });
          if (_originalUrl.match(/UserLogin/) || obj.url.match(/catalogLogin/)) {
            if (eventResult) {
              VuFind.refreshPage();
            }
            return false;
          } else {
            VuFind.lightbox.refreshOnClose = true;
          }
          _currentUrl = _originalUrl; // Now that we're logged in, where were we?
        }
        if (jq_xhr.status === 205) {
          VuFind.refreshPage();
          return;
        }
        render(content);
      })
      .fail(function lbAjaxFail(deferred, errorType, msg) {
        showAlert(VuFind.translate('error_occurred') + '<br/>' + msg, 'danger');
      });
    return _xhr;
  }
  function reload() {
    ajax({ url: _currentUrl || _originalUrl });
  }

  /**
   * Evaluate a callback
   */
  function _evalCallback(callback, event, data) {
    if ('function' === typeof window[callback]) {
      return window[callback](event, data);
    }
    var parts = callback.split('.');
    if (typeof window[parts[0]] === 'object') {
      var obj = window[parts[0]];
      for (var i = 1; i < parts.length; i++) {
        if (typeof obj[parts[i]] === 'undefined') {
          obj = false;
          break;
        }
        obj = obj[parts[i]];
      }
      if ('function' === typeof obj) {
        return obj(event, data);
      }
    }
    console.error('Lightbox callback function not found.');
    return null;
  }

  /**
   * Modal link data options
   *
   * data-lightbox-href = go to this url instead
   * data-lightbox-ignore = do not open this link in lightbox
   * data-lightbox-post = post data
   * data-lightbox-title = Lightbox title (overrides any title the page provides)
   */
  _constrainLink = function constrainLink(event) {
    if (typeof $(this).data('lightboxIgnore') != 'undefined'
      || typeof this.attributes.href === 'undefined'
      || this.attributes.href.value.charAt(0) === '#'
      || this.href.match(/^[a-zA-Z]+:[^/]/) // ignore resource identifiers (mailto:, tel:, etc.)
    ) {
      return true;
    }
    if (this.href.length > 1) {
      event.preventDefault();
      var obj = {url: $(this).data('lightboxHref') || this.href};
      if ("string" === typeof $(this).data('lightboxPost')) {
        obj.type = 'POST';
        obj.data = $(this).data('lightboxPost');
      }
      _lightboxTitle = $(this).data('lightboxTitle') || '';
      _modalParams = $(this).data();
      VuFind.modal('show');
      ajax(obj);
      _currentUrl = this.href;
      return false;
    }
  };

  /**
   * Handle form submission.
   *
   * Form data options:
   *
   * data-lightbox-onsubmit = on submit, run named function
   * data-lightbox-onclose  = on close, run named function
   * data-lightbox-title = Lightbox title (overrides any title the page provides)
   *
   * Submit button data options:
   *
   * data-lightbox-ignore = do not handle clicking this button in lightbox
   */
  _formSubmit = function formSubmit(event) {
    // Gather data
    var form = event.target;
    var data = $(form).serializeArray();
    // Check for recaptcha
    if (typeof grecaptcha !== 'undefined') {
      var recaptcha = $(form).find('.g-recaptcha');
      if (recaptcha.length > 0) {
        data.push({ name: 'g-recaptcha-response', value: grecaptcha.getResponse(recaptcha.data('captchaId')) });
      }
    }
    // Force layout
    data.push({ name: 'layout', value: 'lightbox' }); // Return in lightbox, please
    // Add submit button information
    var submit = $(_clickedButton);
    _clickedButton = null;
    var buttonData = { name: 'submit', value: 1 };
    if (submit.length > 0) {
      if (typeof submit.data('lightbox-close') !== 'undefined') {
        close();
        return false;
      }
      if (typeof submit.data('lightbox-ignore') !== 'undefined') {
        return true;
      }
      buttonData.name = submit.attr('name') || 'submit';
      buttonData.value = submit.attr('value') || 1;
    }
    data.push(buttonData);
    // Special handlers
    // On submit behavior
    if ('string' === typeof $(form).data('lightboxOnsubmit')) {
      var ret = _evalCallback($(form).data('lightboxOnsubmit'), event, data);
      // return true or false to send that to the form
      // return null or anything else to continue to the ajax
      if (ret === false || ret === true) {
        return ret;
      }
    }
    // onclose behavior
    if ('string' === typeof $(form).data('lightboxOnclose')) {
      document.addEventListener('VuFind.lightbox.closed', function lightboxClosed(e) {
        this.removeEventListener('VuFind.lightbox.closed', arguments.callee);
        _evalCallback($(form).data('lightboxOnclose'), e, form);
      }, false);
    }
    // Loading
    _modalBody.prepend('<i class="modal-loading fa fa-spinner fa-spin" title="' + VuFind.translate('loading') + '"></i>');
    // Prevent multiple submission of submit button in lightbox
    if (submit.closest(_modal).length > 0) {
      submit.attr('disabled', 'disabled');
    }
    // Store custom title
    _lightboxTitle = submit.data('lightboxTitle') || $(form).data('lightboxTitle') || '';
    // Get Lightbox content
    ajax({
      url: $(form).attr('action') || _currentUrl,
      method: $(form).attr('method') || 'GET',
      data: data
    }).done(function recaptchaReset() {
      resetCaptcha($(form));
    });

    VuFind.modal('show');
    return false;
  };

  // Public: Attach listeners to the page
  function bind(el) {
    var target = el || document;
    $(target).find('a[data-lightbox]')
      .unbind('click', _constrainLink)
      .on('click', _constrainLink);
    $(target).find('form[data-lightbox]')
      .unbind('submit', _formSubmit)
      .on('submit', _formSubmit);

    // Handle submit buttons attached to a form as well as those in a form. Store
    // information about which button was clicked here as checking focused button
    // doesn't work on all browsers and platforms.
    $('form[data-lightbox]').each(function bindFormSubmitsLightbox(i, form) {
      $(form).find('[type=submit]').click(_storeClickedStatus);
      $('[type="submit"][form="' + form.id + '"]').click(_storeClickedStatus);
    });
  }

  function reset() {
    _html(VuFind.translate('loading') + '...');
    _originalUrl = false;
    _currentUrl = false;
    _lightboxTitle = '';
    _modalParams = {};
  }
  function init() {
    _modal = $('#modal');
    _modalBody = _modal.find('.modal-body');
    _modal.on('hide.bs.modal', function lightboxHide() {
      if (VuFind.lightbox.refreshOnClose) {
        VuFind.refreshPage();
      }
      this.setAttribute('aria-hidden', true);
      _emit('VuFind.lightbox.closing');
    });
    _modal.on('hidden.bs.modal', function lightboxHidden() {
      VuFind.lightbox.reset();
      _emit('VuFind.lightbox.closed');
    });

    VuFind.modal = function modalShortcut(cmd) {
      if (cmd === 'show') {
        _modal.modal($.extend({ show: true }, _modalParams)).attr('aria-hidden', false);
      } else {
        _modal.modal(cmd);
      }
    };
    bind();
  }

  // Reveal
  return {
    // Properties
    refreshOnClose: refreshOnClose,

    // Methods
    ajax: ajax,
    alert: showAlert,
    bind: bind,
    close: close,
    flashMessage: flashMessage,
    reload: reload,
    render: render,
    // Reset
    reset: reset,
    // Init
    init: init
  };
});
