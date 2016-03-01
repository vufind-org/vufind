/*global VuFind */
function Lightbox() {
  // State
  this.originalUrl = false;
  this.currentUrl = false;
  this.refreshOnClose = false;
  // Elements
  this.modal = $('#modal');
  this.modalBody = this.modal.find('.modal-body');
  // Utilities
  this.find = function(sel) { return this.modal.find(sel); }
  VuFind.modal = function(cmd) { VuFind.lightbox.modal.modal(cmd); }
  this.html = function(html) {
    this.modalBody.html(html);
    this.modal.modal('handleUpdate');
  }

  this.bind = function(target) {
    if ('undefined' === typeof target) {
      target = document;
    }
    $(target).find('a[data-lightbox]')
      .unbind('click', this.constrainLink)
      .on('click', this.constrainLink);
    $(target).find('form[data-lightbox]')
      .unbind('submit', this.formSubmit)
      .on('submit', this.formSubmit);
  };

  this.xhr = false;
  this.ajax = function(obj) {
    if (this.xhr !== false) return;
    if (VuFind.lightbox.originalUrl === false) {
      VuFind.lightbox.originalUrl = obj.url;
    }
    if (!obj.url.match(/layout=lightbox/)) {
      var parts = obj.url.split('#');
      obj.url = parts[0].indexOf('?') < 0
        ? parts[0]+'?'
        : parts[0]+'&';
      obj.url += 'layout=lightbox&lbreferer='+encodeURIComponent(this.currentUrl);
      obj.url += parts.length < 2 ? '' : '#'+parts[1];
    }
    this.xhr = $.ajax(obj)
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
          if (
            VuFind.lightbox.originalUrl.match(/UserLogin/)
            || obj.url.match(/catalogLogin/)
          ) {
            window.location.reload();
            return false;
          } else {
            VuFind.lightbox.refreshOnClose = true;
          }
        }
        VuFind.lightbox.update(html);
        VuFind.lightbox.xhr = false;
      })
      .then().fail(function(e) {
        $('body').html('<div>'+e.responseText+'</div>');
        VuFind.lightbox.modal.addClass('hidden');
      });
    return this.xhr;
  };

  // Present an alert
  this.alert = function(message, type) {
    if ('undefined' == typeof type) {
      type = 'info';
    }
    this.html('<div class="alert alert-'+type+'">'+message+'</div>\
    <button class="btn btn-default" data-dismiss="modal">close</button>');
    this.modal.modal('show');
  };

  // Update content
  this.update = function(html, link) {
    // Deframe HTML
    if(html.match('<!DOCTYPE html>')) {
      html = $('<div>'+html+'</div>').find('.main > .container').html();
    }
    // Isolate successes
    var testDiv = $('<div>'+html+'</div>');
    var alerts = testDiv.find('.alert-success');
    if (alerts.length > 0) {
      this.alert(alerts[0].innerHTML, 'success');
      return;
    }
    // Fill HTML
    this.html(html);
    this.modal.modal('show');
    // Attach capturing events
    this.modalBody.find('a').on('click', VuFind.lightbox.constrainLink);
    var forms = this.modalBody.find('form');
    for(var i=0;i<forms.length;i++) {
      $(forms[i]).on('submit', this.formSubmit);
    }
    // Select all checkboxes
    $('#modal').find('.checkbox-select-all').change(function() {
      $(this).closest('.modal-body').find('.checkbox-select-item').prop('checked', this.checked);
    });
    $('#modal').find('.checkbox-select-item').change(function() {
      $(this).closest('.modal-body').find('.checkbox-select-all').prop('checked', false);
    });
  }
  /**
   * Modal link data options
   *
   * data-lightbox-close  = close lightbox after form success
   * data-lightbox-href   = overwrite href with this value in lightbox
   * data-lightbox-ignore = do not open this link in lightbox
   * data-lightbox-post   = post json for link ajax
   */
  this.constrainLink = function(event) {
    // console.log('constrainLink');
    if (typeof this.dataset.lightboxIgnore != 'undefined') {
      return true;
    }
    if (this.href.length > 1) {
      event.preventDefault();
      VuFind.lightbox.ajax({url: this.href});
      VuFind.lightbox.currentUrl = this.href;
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
  this.formSubmit = function(event) {
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
    VuFind.lightbox.modalBody.prepend('<i class="fa fa-spinner fa-spin pull-right"></i>');
    // Get Lightbox content
    VuFind.lightbox.ajax({
      url: form.action || VuFind.lightbox.currentUrl,
      method: form.method || 'GET',
      data: data
    });

    VuFind.modal('show');
    return false;
  }

  // Ready actions
  this.bind();
  this.modal.on('hide.bs.modal', function() {
    if (VuFind.lightbox.refreshOnClose) {
      window.location.reload();
    }
    document.dispatchEvent(new Event('VuFind.lightbox.closing'));
  });
  this.modal.on('hidden.bs.modal', function() {
    document.dispatchEvent(new Event('VuFind.lightbox.closed'));
    VuFind.lightbox.html(VuFind.translate('loading') + '...');
    VuFind.lightbox.openingUrl = false;
    VuFind.lightbox.currentUrl = false;
  });
}
/**
 * This is where you add click events to open the lightbox.
 * We do it here so that non-JS users still have a good time.
 */
$(document).ready(function() {
  VuFind.lightbox = new Lightbox();
});
