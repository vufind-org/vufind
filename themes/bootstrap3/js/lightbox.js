/*global VuFind */
function Lightbox() {
  // State
  this.shown = false;
  this.lastUrl = false;
  this.loginCallback = false;
  this.refreshOnClose = false;
  this.closeTimeout = false;
  // Elements
  this.modal = $('#modal');
  this.modalBody = this.modal.find('.modal-body');
  // Utilities
  this.find = function(sel) { return this.modal.find(sel); }
  this.html = function(html) { this.modalBody.html(html); }
  VuFind.modal = function(cmd) { VuFind.lightbox.modal.modal(cmd); }

  this.update = function(html, link, checkForAlert) {
    //console.log('VuFind.lightbox.update');
    var doConstrainLinks = !!link && !$(link).hasClass('help-link');
    if(html.match('<!DOCTYPE html>')) {
      html = $('<div>'+html+'</div>').find('.main > .container').html();
    }
    if(true === checkForAlert) {
      var alerts = $('<div>'+html+'</div>').find('.alert').not('form .alert');
      if (alerts.length > 0) {
        html = alerts[0].outerHTML;
        console.log('reduced to alert');
        doConstrainLinks = false;
      }
    }
    this.html(html);
    if(this.shown) { VuFind.modal('handleUpdate'); }
    if(doConstrainLinks) { this.modalBody.find('a').click(VuFind.lightbox.constrainLink); }
    if("undefined" !== typeof link && null !== link
    && "undefined" !== typeof link.dataset
    && "undefined" !== typeof link.dataset.lightboxClose) {
      var forms = this.modalBody.find('form');
      for(var i=0;i<forms.length;i++) {
        forms[i].dataset.lightboxClose = link.dataset.lightboxClose;
        $(forms[i]).on('submit', this.formSubmit);
      }
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
    //console.log('constrainLink');
    if('undefined' !== typeof this.dataset.lightboxIgnore) {
      return true;
    }
    if('undefined' !== typeof this.dataset.lightboxHref) {
      this.href = this.dataset.lightboxHref;
    }
    if('undefined' === typeof this.href) {
      this.href = VuFind.getPath();
    }
    if('undefined' !== typeof event.target.dataset.lightboxAfterLogin) {
      if('hide' === event.target.dataset.lightboxAfterLogin) {
        VuFind.lightbox.loginCallback = true;
      } else {
        eval('VuFind.lightbox.loginCallback = ' + event.target.dataset.lightboxAfterLogin);
      }
    }
    if(this.href.length > 1) {
      event.preventDefault();

      VuFind.lightbox.lastUrl = this.href;

      var parts = this.href.split('#');
      parts[1] = parts.length < 2 ? '' : '#'+parts[1];
      var ajaxObj = {
        url: parts[0].indexOf('?') < 0
          ? parts[0]+'?layout=lightbox'+parts[1]
          : parts[0]+'&layout=lightbox'+parts[1],
        success: function(d){VuFind.lightbox.update(d, event.target);}
      };
      if('undefined' !== typeof this.dataset.lightboxPost) {
        ajaxObj.method = 'POST';
        ajaxObj.data = this.dataset.lightboxPost;
      }
      $.ajax(ajaxObj);
      if(!VuFind.lightbox.shown) {
        VuFind.modal('show');
        clearTimeout(VuFind.lightbox.closeTimeout);
        lightboxShown = true;
      }
      return false;
    }
  }

  /**
   * Form data options
   *
   * data-lightbox         = looked for at page ready, handles form response in Lightbox
   * data-lightbox-close   = close after success (present to close, set to function name to run)
   * data-lightbox-success = on success, run named function
   * data-lightbox-after-login = on success, run named function
   *
   * Submit button data options
   *
   * data-lightbox-ignore = show form return outside lightbox
   */
  this.formSubmit = function(event) {
    //console.log('lightboxFormSubmit', event);
    event.preventDefault();
    // Loading
    VuFind.lightbox.modalBody.prepend('<i class="fa fa-spinner fa-spin pull-right"></i>');
    // Gather data
    var form = event.target;
    var dataset = form.dataset;
    var data = $(form).serializeArray();
    data.push({'name':'layout', 'value':'lightbox'}); // Return in lightbox, please
    var clicked = $(form).find('[type=submit]:focus');
    // Add clicked button name and value to form data
    if(clicked.length > 0 && clicked.attr('name')) {
      if('undefined' !== typeof clicked.data('lightbox-ignore')) { // Ignore escape
        $(form).append(
          $('<input>')
            .attr('type', 'hidden')
            .attr('name', clicked.attr('name'))
            .val(clicked.attr('value') || 1)
        );
        return true;
      }
      data.push({'name':clicked.attr('name'), 'value':clicked.attr('value') || 1});
    }
    // Overwritten behavior
    var dataset = 'undefined' !== typeof dataset;
    if(dataset && "string" === typeof dataset.lightboxSubmit
      && "function" === typeof window[dataset.lightboxSubmit]) {
      // console.log(dataset.lightboxSubmit+"(event, data)");
      return window[dataset.lightboxSubmit](event, data);
    }
    if(dataset && "undefined" !== typeof dataset.lightboxAfterLogin) {
      if('hide' === dataset.lightboxAfterLogin) {
        VuFind.lightbox.loginCallback = true;
      } else {
        eval('VuFind.lightbox.loginCallback = ' + dataset.lightboxAfterLogin);
      }
    }
    var dataset = 'undefined' !== typeof dataset;
    if(typeof form.action !== 'undefined') {
      VuFind.lightbox.lastUrl = form.action;
    }
    $.ajax({
      url: VuFind.lightbox.lastUrl,
      method: form.method || 'GET',
      data: data,
      success: function(html, status) {
        if(dataset && 'undefined' !== typeof dataset.lightboxSuccess) {
          if("function" === typeof window[dataset.lightboxSuccess]) {
            window[dataset.lightboxSuccess](html, status);
          } else {
            VuFind.lightbox.update('<div class="alert alert-success">'+dataset.lightboxSuccess+'</div>');
            VuFind.lightbox.closeTimeout = setTimeout("VuFind.modal('hide');", 2500);
            return false;
          }
        }
        if(dataset && 'undefined' !== typeof dataset.lightboxClose) {
          VuFind.modal('hide');
          if("function" === typeof window[dataset.lightboxClose]) {
            window[dataset.lightboxClose](html, status);
          }
        } else {
          VuFind.lightbox.update(html, null, true);
        }
      },
      error: function(e) {
        $('body').removeClass('modal-open').html('<div>'+e.responseText+'</div>');
        VuFind.lightbox.modal.addClass('hidden');
      }
    });

    if(!VuFind.lightbox.shown) {
      VuFind.modal('show');
      VuFind.lightbox.shown = true;
    }
    return false;
  }

  // Ready actions
  $('a[data-lightbox]').on('click', this.constrainLink);
  $('form[data-lightbox]').on('submit', this.formSubmit);
  this.modal.on('hidden.bs.modal', function() {
    if (VuFind.lightbox.refreshOnClose) {
      window.location.reload();
    } else {
      VuFind.lightbox.html(VuFind.translate('loading') + '...');
      VuFind.lightbox.shown = false;
    }
  });
}
/**
 * This is where you add click events to open the lightbox.
 * We do it here so that non-JS users still have a good time.
 */
$(document).ready(function() {
  VuFind.lightbox = new Lightbox();
});
