/*global console, path, vufindString */

/**
 * This is where you add click events to open the lightbox.
 * We do it here so that non-JS users still have a good time.
 */
var lightboxShown = false;
var lightboxLoginCallback = false;
$(document).ready(function() {
  if(lightboxShown) {
    $('#modal .modal-body').html('');
    $('#modal').modal('hide');
  } else {
    $('a[data-lightbox]').on('click', constrainLink);
    lightboxShown = false;
  }
  constrainForms('form[data-lightbox]');
  $('#modal').on('hidden.bs.modal', function() {
    $('#modal .modal-body').html(vufindString.loading+'...');
    lightboxShown = false;
    refreshTags();
  });
});

function updateLightbox(html, link) {
  //console.log('updateLightbox');
  if(html.match('<!DOCTYPE html>')) {
    html = $('<div>'+html+'</div>').find('.main > .container').html();
  }
  $('#modal .modal-body').html(html);
  if(lightboxShown) {
    $('#modal').modal('handleUpdate');
  }
  $('#modal .modal-body').on('click', 'a', constrainLink);
  if("undefined" !== typeof link
  && "undefined" !== typeof link.dataset
  && "undefined" !== typeof link.dataset.lightboxClose) {
    var forms = $('#modal .modal-body form');
    for(var i=0;i<forms.length;i++) {
      forms[i].dataset.lightboxClose = link.dataset.lightboxClose;
    }
  }
  constrainForms('#modal form');
  // Select all checkboxes
  $('#modal').find('.checkbox-select-all').change(function() {
    $(this).closest('.modal-body').find('.checkbox-select-item').prop('checked', this.checked);
  });
  $('#modal').find('.checkbox-select-item').change(function() {
    $(this).closest('.modal-body').find('.checkbox-select-all').prop('checked', false);
  });
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
function constrainForms(selector) {
  var forms = $(selector);
  for(var i=forms.length;i--;) {
    if('undefined' === typeof forms[i].action) {
      forms[i].action = path;
    }
    if(forms[i].action.length > 1) {
      $(forms[i]).unbind('submit').bind('submit', lightboxFormSubmit);
    }
  }
}

function lightboxFormSubmit(event) {
  $('#modal .modal-body').prepend('<i class="fa fa-spinner fa-spin pull-right"></i>');
  // Gather data
  var data = $(event.target).serializeArray();
  data[data.length] = {'name':'layout', 'value':'lightbox'}; // Return in lightbox, please
  var clicked = $(event.target).find('[type=submit]:focus');
  // Add clicked button name and value to form data
  if(clicked.length > 0 && clicked.attr('name')) {
    if('undefined' !== typeof clicked.data('lightbox-ignore')) { // Ignore escape
      $(event.target).append(
        $('<input>')
          .attr('type', 'hidden')
          .attr('name', clicked.attr('name'))
          .val(clicked.attr('value') || 1)
      );
      return true;
    }
    data[data.length] = {'name':clicked.attr('name'), 'value':clicked.attr('value') || 1};
  }
  event.preventDefault();
  // Overwritten behavior
  var dataset = 'undefined' !== typeof event.target.dataset;
  if(dataset && "string" === typeof event.target.dataset.lightboxSubmit
    && "function" === typeof window[event.target.dataset.lightboxSubmit]) {
    console.log(event.target.dataset.lightboxSubmit+"(event, data)");
    return window[event.target.dataset.lightboxSubmit](event, data);
  }
  if(dataset && "undefined" !== typeof event.target.dataset.lightboxAfterLogin) {
    eval('lightboxLoginCallback = ' + event.target.dataset.lightboxAfterLogin);
  }
  lightboxAJAX(event, data);
  if(!lightboxShown) {
    $('#modal').modal('show');
    lightboxShown = true;
  }
  return false;
}
function lightboxAJAX(event, data) {
  var dataset = 'undefined' !== typeof event.target.dataset;
  $.ajax({
    url: event.target.action || path,
    method: event.target.method || 'GET',
    data: data,
    success: function(html, status) {
      console.log(status);
      if(dataset && 'undefined' !== typeof event.target.dataset.lightboxSuccess
        && "function" === typeof window[event.target.dataset.lightboxSuccess]) {
        window[event.target.dataset.lightboxSuccess](html, status);
      }
      if(dataset && 'undefined' !== typeof event.target.dataset.lightboxClose) {
        $('#modal').modal('hide');
        if("function" === typeof window[event.target.dataset.lightboxClose]) {
          window[event.target.dataset.lightboxClose](html, status);
        }
      } else if(dataset && 'string' === typeof event.target.dataset.lightboxConfirm) {
        updateLightbox('<div class="alert alert-info">'+event.target.dataset.lightboxConfirm+'</div>');
      } else {
        updateLightbox(html);
      }
    },
    error: function(e) {
      $('body').removeClass('modal-open').html('<div>'+e.responseText+'</div>');
      $('#modal').addClass('hidden');
      //$('a[data-lightbox]').on('click', constrainLink);
    }
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
function constrainLink(event) {
  if('undefined' !== typeof this.dataset.lightboxIgnore) {
    return true;
  }
  if('undefined' !== typeof this.dataset.lightboxHref) {
    this.href = this.dataset.lightboxHref;
  }
  if('undefined' === typeof this.href) {
    this.href = path;
  }
  if(this.href.length > 1) {
    event.preventDefault();
    var parts = this.href.split('#');
    parts[1] = parts.length < 2 ? '' : '#'+parts[1];
    var ajaxObj = {
      url: parts[0].indexOf('?') < 0
        ? parts[0]+'?layout=lightbox'+parts[1]
        : parts[0]+'&layout=lightbox'+parts[1],
      success: function(d){updateLightbox(d, event.target);}
    };
    if('undefined' !== typeof this.dataset.lightboxPost) {
      ajaxObj.method = 'POST';
      ajaxObj.data = this.dataset.lightboxPost;
    }
    $.ajax(ajaxObj);
    if(!lightboxShown) {
      $('#modal').modal('show');
      lightboxShown = true;
    }
    return false;
  }
}