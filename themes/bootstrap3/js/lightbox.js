/*global VuFind */
function Lightbox() {
  // State
  this.originalUrl = false;
  this.currentUrl = false;
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
    obj.success = function(html, status) {
      VuFind.lightbox.update(html);
      VuFind.lightbox.xhr = false;
    },
    obj.error = function(e) {
      $('body').html('<div>'+e.responseText+'</div>');
      VuFind.lightbox.modal.addClass('hidden');
    }
    if (!obj.url.match(/layout=lightbox/)) {
      var parts = obj.url.split('#');
      obj.url = parts[0].indexOf('?') < 0
        ? parts[0]+'?'
        : parts[0]+'&';
      obj.url += 'layout=lightbox&lbreferer='+encodeURIComponent(this.currentUrl);
      obj.url += parts.length < 2 ? '' : '#'+parts[1];
    }
    $.ajax(obj);
    return false;
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
      html = alerts[0].outerHTML + '<button class="btn btn-default" data-dismiss="modal">close</button>';
      this.html(html);
      return;
    }
    // Close after login
    if (this.currentUrl.indexOf('UserLogin') > -1
        && testDiv.find('#loginForm').length == 0
        && testDiv.find('.alert-danger').length == 0
    ) {
      VuFind.modal('hide');
      return false;
    }
    // Fill HTML
    this.html(html);
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
    if(this.href.length > 1) {
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
   * data-lightbox-submit = on success, run named function
   */
  this.formSubmit = function(event) {
    // Gather data
    var form = event.target;
    var dataset = form.dataset;
    var data = $(form).serializeArray();
    var clicked = $(form).find('[type=submit]:focus');
    if(clicked.length > 0 && clicked.attr('name')) {
      data.push({'name':clicked.attr('name'), 'value':clicked.attr('value') || 1});
    }
    data.push({'name':'layout', 'value':'lightbox'}); // Return in lightbox, please
    if ('undefined' !== typeof dataset) {
      // Overwritten behavior
      if("string" === typeof dataset.lightboxHandler && "function" === typeof window[dataset.lightboxHandler]) {
        var ret = window[dataset.lightboxHandler](event, data);
        if (ret === false) {
          return true;
        }
      }
      // On submit behavior
      if("string" === typeof dataset.lightboxOnsubmit && "function" === typeof window[dataset.lightboxOnsubmit]) {
        window[dataset.lightboxOnsubmit]();
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
    if (form.method) {
      console.log({
        url: form.action || VuFind.lightbox.currentUrl,
        method: form.method || 'GET',
        data: data
      });
    }
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
