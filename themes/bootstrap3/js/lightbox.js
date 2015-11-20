/*global path, vufindString */

/**
 * This is where you add click events to open the lightbox.
 * We do it here so that non-JS users still have a good time.
 */
var lightboxShown = false;
var lightboxLastUrl = false;
var lightboxLoginCallback = false;
var lightboxRefreshOnClose = false;
var lightboxCloseTimeout = false;
$(document).ready(function() {
  if(lightboxShown) {
    $('#modal .modal-body').html('');
    $('#modal').modal('hide');
  } else {
    $('a[data-lightbox]').on('click', constrainLink);
    lightboxShown = false;
  }
  constrainForms('form[data-lightbox]');
  // $('#modal .modal-body').on('click', 'a', constrainLink);
  $('#modal').on('hidden.bs.modal', function() {
    if (lightboxRefreshOnClose) {
      window.location.reload();
    } else {
      $('#modal .modal-body').html(vufindString.loading+'...');
      lightboxShown = false;
    }
  });
});

function updateLightbox(html, link, checkForAlert) {
  //console.log('updateLightbox');
  if(html.match('<!DOCTYPE html>')) {
    html = $('<div>'+html+'</div>').find('.main > .container').html();
  }
  $('#modal .modal-body').html(html);
  if(typeof link === 'undefined' || !$(link).hasClass('help-link')) {
    $('#modal .modal-body a').click(constrainLink);
  }
  constrainForms('#modal form');
  // Select all checkboxes
  $('#modal').find('.checkbox-select-all').change(function() {
    $(this).closest('.modal-body').find('.checkbox-select-item').prop('checked', this.checked);
  });
  $('#modal').find('.checkbox-select-item').change(function() {
    $(this).closest('.modal-body').find('.checkbox-select-all').prop('checked', false);
  });
  /*
  if(true === checkForAlert) {
    var $newContent = $('<div>'+html+'</div>');
    if ($newContent.find('[name="loginForm"], [name="accountForm"]').length == 0) {
      var alerts = $newContent.find('.alert').not('form .alert');
      if (alerts.length > 0) {
        html = alerts[0].outerHTML;
        console.log('reduced to alert');
        doConstrainLinks = false;
      }
    }
  }
  if(lightboxShown) { $('#modal').modal('handleUpdate'); }
  if(doConstrainLinks) { $('#modal .modal-body a').click(constrainLink); }
  if("undefined" !== typeof link && null !== link
  && "undefined" !== typeof link.dataset
  && "undefined" !== typeof link.dataset.lightboxClose) {
    var forms = $('#modal .modal-body form');
    for(var i=0;i<forms.length;i++) {
      forms[i].dataset.lightboxClose = link.dataset.lightboxClose;
    }
  }*/
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
  //console.log('constrainLink');
  if('undefined' !== typeof this.dataset.lightboxIgnore) {
    return true;
  }
  if('undefined' !== typeof this.dataset.lightboxHref) {
    this.href = this.dataset.lightboxHref;
  }
  if('undefined' === typeof this.href) {
    this.href = path;
  }
  if('undefined' !== typeof event.target.dataset.lightboxAfterLogin) {
    if('hide' === event.target.dataset.lightboxAfterLogin) {
      lightboxLoginCallback = true;
    } else {
      eval('lightboxLoginCallback = ' + event.target.dataset.lightboxAfterLogin);
    }
  }
  if(this.href.length > 1) {
    event.preventDefault();

    lightboxLastUrl = this.href;

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
      clearTimeout(lightboxCloseTimeout);
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
function constrainForms(selector) {
  var forms = $(selector);
  for(var i=forms.length;i--;) {
    if(lightboxLastUrl && ('undefined' === typeof forms[i].action || forms[i].action.length == 0)) {
      $(forms[i]).attr('action', lightboxLastUrl);
    }
    if(forms[i].action.length > 1) { // #
      $(forms[i]).unbind('submit').bind('submit', lightboxFormSubmit);
    }
  }
}

function lightboxFormSubmit(event) {
  //console.log('lightboxFormSubmit', event);
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
    // console.log(event.target.dataset.lightboxSubmit+"(event, data)");
    return window[event.target.dataset.lightboxSubmit](event, data);
  }
  if(dataset && "undefined" !== typeof event.target.dataset.lightboxAfterLogin) {
    if('hide' === event.target.dataset.lightboxAfterLogin) {
      lightboxLoginCallback = true;
    } else {
      eval('lightboxLoginCallback = ' + event.target.dataset.lightboxAfterLogin);
    }
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
  if(typeof event.target.action !== 'undefined') {
    lightboxLastUrl = event.target.action;
  }
  $.ajax({
    url: event.target.action || lightboxLastUrl,
    method: event.target.method || 'GET',
    data: data,
    success: function(html, status) {
      if(dataset && 'undefined' !== typeof event.target.dataset.lightboxSuccess) {
        if("function" === typeof window[event.target.dataset.lightboxSuccess]) {
          if (!window[event.target.dataset.lightboxSuccess](html, status)) {
            return false;
          }
        } else {
          updateLightbox('<div class="alert alert-success">'+event.target.dataset.lightboxSuccess+'</div>');
          lightboxCloseTimeout = setTimeout("$('#modal').modal('hide');", 2500);
          return false;
        }
      }
      if(dataset && 'undefined' !== typeof event.target.dataset.lightboxClose) {
        $('#modal').modal('hide');
        if("function" === typeof window[event.target.dataset.lightboxClose]) {
          window[event.target.dataset.lightboxClose](html, status);
        }
      } else {
        updateLightbox(html, null, true);
      }
    },
    error: function(e) {
      $('body').removeClass('modal-open').html('<div>'+e.responseText+'</div>');
      $('#modal').addClass('hidden');
    }
  });
}
