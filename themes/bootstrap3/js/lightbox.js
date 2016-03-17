/*global $, document, CustomEvent, VuFind, window */
VuFind.lightbox = (function() {
  // State
  var _originalUrl = false;
  var _currentUrl = false;
  var refreshOnClose = false;
  // Elements
  var _modal, _modalBody, _clickedButton = null;
  // Utilities
  var _storeClickedStatus = function() {
    _clickedButton = this;
  };
  var _html = function(html) {
    _modalBody.html(html);
    _modal.modal('handleUpdate');
  };
  var _emit = function(msg, details) {
    if ('undefined' == typeof details) {
      details = {};
    }
    // Fallback to document.createEvent() if creating a new CustomEvent fails (e.g. IE 11)
    var event;
    try {
       event = new CustomEvent(msg, {
        detail: details,
        bubbles: true,
        cancelable: true
      });
    } catch (e) {
      event = document.createEvent('CustomEvent');
      event.initCustomEvent(msg, true, true, details);  
    }
    return document.dispatchEvent(event);
  };

  /**
   * Reload the page without causing trouble with POST parameters while keeping hash
   */
  var _refreshPage = function() {
    var parts = window.location.href.split('#');
    if (typeof parts[1] === 'undefined') {
      window.location.href = window.location.href;
    } else {
      var href = parts[0];
      // Force reload with a timestamp
      href += href.indexOf('?') == -1 ? '?_=' : '&_=';
      href += new Date().getTime() + '#' + parts[1];
      window.location.href = href;
    }
  };
  // Public: Present an alert
  var showAlert = function(message, type) {
    if ('undefined' == typeof type) {
      type = 'info';
    }
    _html('<div class="alert alert-'+type+'">'+message+'</div><button class="btn btn-default" data-dismiss="modal">' + VuFind.translate('close') + '</button>');
    _modal.modal('show');
  };
  var flashMessage = function(message, type) {
    _modalBody.find('.alert,.fa.fa-spinner').remove();
    _modalBody.find('h2:first-child')
      .after('<div class="alert alert-'+type+'">'+message+'</div>');
  };

  /**
   * Update content
   *
   * Form data options:
   *
   * data-lightbox-ignore = do not submit this form in lightbox
   */
  var _update = function(html) {
    if (!html.match) {
      return;
    }
    // Isolate successes
    var htmlDiv = $('<div/>').html(html);
    var alerts = htmlDiv.find('.flash-message.alert-success');
    if (alerts.length > 0) {
      showAlert(alerts[0].innerHTML, 'success');
      return;
    }
    // Deframe HTML
    if (html.match('<!DOCTYPE html>')) {
      html = htmlDiv.find('.main > .container').html();
    }
    // Fill HTML
    _html(html);
    _modal.modal('show');
    // Attach capturing events
    _modalBody.find('a').click(_constrainLink);
    // Handle submit buttons attached to a form as well as those in a form. Store
    // information about which button was clicked here as checking focused button
    // doesn't work on all browsers and platforms.
    _modalBody.find('[type=submit]').click(_storeClickedStatus);

    var forms = _modalBody.find('form:not([data-lightbox-ignore])');
    for (var i=0;i<forms.length;i++) {
      $(forms[i]).on('submit', _formSubmit);
    }
    // Select all checkboxes
    $('#modal').find('.checkbox-select-all').change(function() {
      $(this).closest('.modal-body').find('.checkbox-select-item').prop('checked', this.checked);
    });
    $('#modal').find('.checkbox-select-item').change(function() {
      $(this).closest('.modal-body').find('.checkbox-select-all').prop('checked', false);
    });
  };

  var _xhr = false;
  // Public: Handle AJAX in the Lightbox
  var ajax = function(obj) {
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
        ? parts[0]+'?'
        : parts[0]+'&';
      obj.url += 'layout=lightbox&lbreferer='+encodeURIComponent(_currentUrl);
      obj.url += parts.length < 2 ? '' : '#'+parts[1];
    }
    _xhr = $.ajax(obj);
    _xhr.always(function() { _xhr = false; })
      .done(function(html, status, jq_xhr) {
        if (jq_xhr.status == 205) {
          _refreshPage();
          return;
        }
        if ( // Close the lightbox after deliberate login
          obj.method                                                                // is a form
          && ((obj.url.match(/MyResearch/) && !obj.url.match(/Bulk/))               // that matches login/create account
            || obj.url.match(/catalogLogin/))                                       // or catalog login for holds
          && $('<div/>').html(html).find('.flash-message.alert-danger').length == 0 // skip failed logins
        ) {
          var eventResult = _emit('VuFind.lightbox.login', {
            originalUrl: _originalUrl,
            formUrl: obj.url
          });
          if (_originalUrl.match(/UserLogin/) || obj.url.match(/catalogLogin/)) {
            if (eventResult) {
              _refreshPage();
            }
            return false;
          } else {
            VuFind.lightbox.refreshOnClose = true;
          }
        }
        _update(html);
      })
      .fail(function() {
        showAlert(VuFind.translate('error_occurred'), 'danger');
      });
    return _xhr;
  };
  var reload = function() {
    ajax({url:_currentUrl || _originalUrl});
  };

  /**
   * Evaluate a callback
   */
  var _evalCallback = function(callback, event, data) {
    if ('function' === typeof window[callback]) {
      return window[callback](event, data);
    } else {
      return eval('(function(event, data) {' + callback + '}())'); // inline code
    }
  };

  /**
   * Modal link data options
   *
   * data-lightbox-href = go to this url instead
   * data-lightbox-ignore = do not open this link in lightbox
   * data-lightbox-post = post data
   */
  var _constrainLink = function(event) {
    if (typeof $(this).data('lightboxIgnore') != 'undefined') {
      return true;
    }
    if (this.href.length > 1) {
      event.preventDefault();
      var obj = {url: $(this).data('lightboxHref') || this.href};
      if("string" === typeof $(this).data('lightboxPost')) {
        obj.type = 'POST';
        obj.data = $(this).data('lightboxPost');
      }
      ajax(obj);
      _currentUrl = this.href;
      VuFind.modal('show');
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
   *
   * Submit button data options:
   *
   * data-lightbox-ignore = do not handle clicking this button in lightbox
   */
  var _formSubmit = function(event) {
    // Gather data
    var form = event.target;
    var data = $(form).serializeArray();
    data.push({'name':'layout', 'value':'lightbox'}); // Return in lightbox, please
    // Add submit button information
    var submit = $(_clickedButton);
    _clickedButton = null;
    var buttonData = {'name':name, 'value':1};
    if (submit.length > 0) {
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
      document.addEventListener('VuFind.lightbox.closed', function(event) {
        _evalCallback($(form).data('lightboxOnclose'), event);
      }, false);
    }
    // Loading
    _modalBody.prepend('<i class="fa fa-spinner fa-spin pull-right"></i>');
    // Prevent multiple submission of submit button in lightbox
    if (submit.closest(_modal).length > 0) {
      submit.attr('disabled', 'disabled');
    }
    // Get Lightbox content
    ajax({
      url: form.action || _currentUrl,
      method: form.method || 'GET',
      data: data
    });

    VuFind.modal('show');
    return false;
  };

  // Public: Attach listeners to the page
  var bind = function(target) {
    if ('undefined' === typeof target) {
      target = document;
    }
    $(target).find('a[data-lightbox]')
      .unbind('click', _constrainLink)
      .on('click', _constrainLink);
    $(target).find('form[data-lightbox]')
      .unbind('submit', _formSubmit)
      .on('submit', _formSubmit);

    // Handle submit buttons attached to a form as well as those in a form. Store
    // information about which button was clicked here as checking focused button
    // doesn't work on all browsers and platforms.
    $('form[data-lightbox] [type=submit]').click(_storeClickedStatus);
  };

  // Reveal
  return {
    // Properties
    refreshOnClose: refreshOnClose,

    // Methods
    ajax: ajax,
    alert: showAlert,
    bind: bind,
    flashMessage: flashMessage,
    reload: reload,
    reset:  function() {
      _html(VuFind.translate('loading') + '...');
      _originalUrl = false;
      _currentUrl = false;
    },

    // Ready
    ready: function() {
      _modal = $('#modal');
      _modalBody = _modal.find('.modal-body');
      _modal.on('hide.bs.modal', function() {
        if (VuFind.lightbox.refreshOnClose) {
          _refreshPage();
        }
        _emit('VuFind.lightbox.closing');
      });
      _modal.on('hidden.bs.modal', function() {
        VuFind.lightbox.reset();
        _emit('VuFind.lightbox.closed');
      });

      VuFind.modal = function(cmd) { _modal.modal(cmd); };
      bind();
    }
  };
})();

$(document).ready(VuFind.lightbox.ready);
