/*global $, document, Event, VuFind, window */
VuFind.lightbox = (function() {
  // State
  var _originalUrl = false;
  var _currentUrl = false;
  var refreshOnClose = false;
  // Elements
  var _modal, _modalBody;
  // Utilities
  var _html = function(html) {
    _modalBody.html(html);
    _modal.modal('handleUpdate');
  }
  // Public: Present an alert
  var showAlert = function(message, type) {
    if ('undefined' == typeof type) {
      type = 'info';
    }
    _html('<div class="alert alert-'+type+'">'+message+'</div>\
    <button class="btn btn-default" data-dismiss="modal">' + VuFind.translate('close') + '</button>');
    _modal.modal('show');
  };
  var flashMessage = function(message, type) {
    _modalBody.find('.alert,.fa.fa-spinner').remove();
    _modalBody.find('h2:first-child')
      .after('<div class="alert alert-'+type+'">'+message+'</div>');
  };

  // Update content
  var _update = function(html) {
    if (!html.match) return;
    // Deframe HTML
    if(html.match('<!DOCTYPE html>')) {
      html = $('<div>'+html+'</div>').find('.main > .container').html();
    }
    // Isolate successes
    var testDiv = $('<div>'+html+'</div>');
    var alerts = testDiv.find('.alert-success');
    if (alerts.length > 0) {
      showAlert(alerts[0].innerHTML, 'success');
      return;
    }
    // Fill HTML
    _html(html);
    _modal.modal('show');
    // Attach capturing events
    _modalBody.find('a').on('click', _constrainLink);
    var forms = _modalBody.find('form');
    for(var i=0;i<forms.length;i++) {
      $(forms[i]).on('submit', _formSubmit);
    }
    // Select all checkboxes
    $('#modal').find('.checkbox-select-all').change(function() {
      $(this).closest('.modal-body').find('.checkbox-select-item').prop('checked', this.checked);
    });
    $('#modal').find('.checkbox-select-item').change(function() {
      $(this).closest('.modal-body').find('.checkbox-select-all').prop('checked', false);
    });
  }

  var _xhr = false;
  // Public: Handle AJAX in the Lightbox
  var ajax = function(obj) {
    if (_xhr !== false) return;
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
          // No reload since any post params would cause a prompt
          window.location.href = window.location.href;
          return;
        }
        if ( // Close the lightbox after deliberate login
          obj.method                           // is a form
          && !html.match(/alert alert-danger/) // skip failed logins
          && (obj.url.match(/MyResearch/)      // that matches login/create account
          || obj.url.match(/catalogLogin/))    // catalog login for holds
        ) {
          if (_originalUrl.match(/UserLogin/) || obj.url.match(/catalogLogin/)) {
            window.location.reload();
            return false;
          } else {
            VuFind.lightbox.refreshOnClose = true;
          }
        }
        _update(html);
      });
    return _xhr;
  };
  var reload = function() {
    ajax({url:_currentUrl || _originalUrl});
  };

  /**
   * Modal link data options
   *
   * data-lightbox-href = go to this url instead
   * data-lightbox-ignore = do not open this link in lightbox
   * data-lightbox-post = post data
   */
  var _constrainLink = function(event) {
    if (typeof this.dataset.lightboxIgnore != 'undefined') {
      return true;
    }
    if (this.href.length > 1) {
      event.preventDefault();
      var obj = {url: this.dataset.lightboxHref || this.href};
      if("string" === typeof this.dataset.lightboxPost) {
        obj.type = 'POST';
        obj.data = this.dataset.lightboxPost;
      }
      ajax(obj);
      _currentUrl = this.href;
      VuFind.modal('show');
      return false;
    }
  }

  /**
   * Form data options
   *
   * data-lightbox-onsubmit = on submit, run named function
   * data-lightbox-onclose  = on close, run named function
   */
  var _formSubmit = function(event) {
    // Gather data
    var form = event.target;
    var dataset = form.dataset;
    var data = $(form).serializeArray();
    data.push({'name':'layout', 'value':'lightbox'}); // Return in lightbox, please
    // Add submit button information
    var submit = $(form).find('[type=submit]');
    if (submit.length > 0) {
      submit.attr('disabled', 'disabled');
      var name = submit.attr('name') ? submit.attr('name') : 'submit';
      data.push({'name':name, 'value':submit.attr('value') || 1});
    }
    // Special handlers
    if ('undefined' !== typeof dataset) {
      // On submit behavior
      if("string" === typeof dataset.lightboxOnsubmit) {
        var ret = null;
        if ("function" === typeof window[dataset.lightboxOnsubmit]) {
          ret = window[dataset.lightboxOnsubmit](event, data);
        } else {
          ret = eval('(function(event, data) {' + dataset.lightboxOnsubmit + '}())'); // inline code
        }
        // return true or false to send that to the form
        // return null or anything else to continue to the ajax
        if (ret === false || ret === true) {
          return ret;
        }
      }
      // onclose behavior
      if("string" === typeof dataset.lightboxOnclose && "function" === typeof window[dataset.lightboxOnclose]) {
        document.addEventListener('VuFind.lightbox.closed', function() {
          window[dataset.lightboxOnclose]();
        }, false);
      }
    }
    // Loading
    _modalBody.prepend('<i class="fa fa-spinner fa-spin pull-right"></i>');
    // Get Lightbox content
    ajax({
      url: form.action || _currentUrl,
      method: form.method || 'GET',
      data: data
    });

    VuFind.modal('show');
    return false;
  }

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
          window.location.reload();
        }
        document.dispatchEvent(new Event('VuFind.lightbox.closing'));
      });
      _modal.on('hidden.bs.modal', function() {
        document.dispatchEvent(new Event('VuFind.lightbox.closed'));
        VuFind.lightbox.reset();
      });

      VuFind.modal = function(cmd) { _modal.modal(cmd); }
      bind();
    }
  };
})();

$(document).ready(VuFind.lightbox.ready);
