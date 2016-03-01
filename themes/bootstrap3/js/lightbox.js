/*global VuFind */
VuFind.lightbox = (function() {
  // State
  var originalUrl = false;
  var currentUrl = false;
  var refreshOnClose = false;
  // Elements
  var modal, modalBody;
  // Utilities
  var applyHTML = function(html) {
    modalBody.html(html);
    modal.modal('handleUpdate');
  }
  // Public: Present an alert
  var showAlert = function(message, type) {
    if ('undefined' == typeof type) {
      type = 'info';
    }
    applyHTML('<div class="alert alert-'+type+'">'+message+'</div>\
    <button class="btn btn-default" data-dismiss="modal">close</button>');
    modal.modal('show');
  };
  var flashMessage = function(message, type) {
    modalBody.find('.alert,.fa.fa-spinner').remove();
    modalBody.find('h2:first-child')
      .after('<div class="alert alert-'+type+'">'+message+'</div>');
  };

  /**
   * Modal link data options
   *
   * data-lightbox-close  = close lightbox after form success
   * data-lightbox-href   = overwrite href with this value in lightbox
   * data-lightbox-ignore = do not open this link in lightbox
   * data-lightbox-post   = post json for link ajax
   */
  var constrainLink = function(event) {
    if (typeof this.dataset.lightboxIgnore != 'undefined') {
      return true;
    }
    if (this.href.length > 1) {
      event.preventDefault();
      ajax({url: this.href});
      currentUrl = this.href;
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
  var formSubmit = function(event) {
    // Gather data
    var form = event.target;
    var dataset = form.dataset;
    var data = $(form).serializeArray();
    data.push({'name':'layout', 'value':'lightbox'}); // Return in lightbox, please
    // Add submit button information
    var clicked = $(form).find('[type=submit]:focus');
    if(clicked.length > 0) {
      var name = clicked.attr('name') ? clicked.attr('name') : 'submit';
      data.push({'name':name, 'value':clicked.attr('value') || 1});
    } else if ($(form).find('[type=submit]').length == 1) {
      clicked = $(form).find('[type=submit]');
      var name = clicked.attr('name') ? clicked.attr('name') : 'submit';
      data.push({'name':name, 'value':$(form).find('[type=submit]').attr('value') || 1});
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
    modalBody.prepend('<i class="fa fa-spinner fa-spin pull-right"></i>');
    // Get Lightbox content
    ajax({
      url: form.action || currentUrl,
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
      .unbind('click', constrainLink)
      .on('click', constrainLink);
    $(target).find('form[data-lightbox]')
      .unbind('submit', formSubmit)
      .on('submit', formSubmit);
  };

  // Update content
  var update = function(html, link) {
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
    applyHTML(html);
    modal.modal('show');
    // Attach capturing events
    modalBody.find('a').on('click', constrainLink);
    var forms = modalBody.find('form');
    for(var i=0;i<forms.length;i++) {
      $(forms[i]).on('submit', formSubmit);
    }
    // Select all checkboxes
    $('#modal').find('.checkbox-select-all').change(function() {
      $(this).closest('.modal-body').find('.checkbox-select-item').prop('checked', this.checked);
    });
    $('#modal').find('.checkbox-select-item').change(function() {
      $(this).closest('.modal-body').find('.checkbox-select-all').prop('checked', false);
    });
  }

  var xhr = false;
  // Public: Handle AJAX in the Lightbox
  var ajax = function(obj) {
    if (xhr !== false) return;
    if (originalUrl === false) {
      originalUrl = obj.url;
    }
    // Add lightbox GET parameter
    if (!obj.url.match(/layout=lightbox/)) {
      var parts = obj.url.split('#');
      obj.url = parts[0].indexOf('?') < 0
        ? parts[0]+'?'
        : parts[0]+'&';
      obj.url += 'layout=lightbox&lbreferer='+encodeURIComponent(currentUrl);
      obj.url += parts.length < 2 ? '' : '#'+parts[1];
    }
    xhr = $.ajax(obj);
    xhr.always(function() { xhr = false; })
      .done(function(html, status, jqXHR) {
        if (jqXHR.status == 205) {
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
          if (originalUrl.match(/UserLogin/) || obj.url.match(/catalogLogin/)) {
            window.location.reload();
            return false;
          } else {
            VuFind.lightbox.refreshOnClose = true;
          }
        }
        update(html);
      });
    return xhr;
  };
  var reload = function() {
    ajax({url:currentUrl || originalUrl});
  };

  // Reveal
  return {
    refreshOnClose: refreshOnClose,

    ajax: ajax,
    alert: showAlert,
    bind: bind,
    flashMessage: flashMessage,
    reload: reload,
    reset:  function() {
      applyHTML(VuFind.translate('loading') + '...');
      openingUrl = false;
      currentUrl = false;
    },

    ready: function() {
      modal = $('#modal');
      modalBody = modal.find('.modal-body');
      modal.on('hide.bs.modal', function() {
        if (VuFind.lightbox.refreshOnClose) {
          window.location.reload();
        }
        document.dispatchEvent(new Event('VuFind.lightbox.closing'));
      });
      modal.on('hidden.bs.modal', function() {
        document.dispatchEvent(new Event('VuFind.lightbox.closed'));
        VuFind.lightbox.reset();
      });

      VuFind.modal = function(cmd) { modal.modal(cmd); }
      bind();
    }
  };
})();

$(document).ready(VuFind.lightbox.ready);
