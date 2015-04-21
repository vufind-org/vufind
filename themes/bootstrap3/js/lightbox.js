/*global console, path, vufindString */

/**
 * This is where you add click events to open the lightbox.
 * We do it here so that non-JS users still have a good time.
 */
var lightboxShown = false;
$(document).ready(function() {
  if(lightboxShown) {
    $('#modal .modal-body').html('');
    $('#modal').modal('hide');
  } else {
    $('.modal-link').on('click', constrainLink);
    lightboxShown = false;
  }
  constrainForms('form[data-lightbox]');
  $('#modal').on('hide.bs.modal', function() {
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
  $('#modal').modal('handleUpdate');
  $('#modal .modal-body').on('click', 'a', constrainLink);
  if("undefined" !== typeof link
  && "undefined" !== typeof link.dataset
  && "undefined" !== typeof link.dataset.lightboxClose) {
    var forms = $('#modal .modal-body form');
    for(var i=0;i<forms.length;i++) {
      forms[i].dataset.lightboxClose = 1;
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
      $(forms[i]).unbind('submit').bind('submit', function(event) {
        var data = $(event.target).serializeArray();
        data[data.length] = {'name':'layout', 'value':'lightbox'};
        var clicked = $(event.target).find('[type=submit]:focus');
        if(clicked.length > 0 && clicked.attr('name')) {
          if('undefined' !== typeof clicked.data('lightbox-ignore')) {
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
        $.ajax({
          url: event.target.action || path,
          method: event.target.method || 'GET',
          data: data,
          success: function(html, status) {
            var dataset = 'undefined' !== typeof event.target.dataset;
            if(dataset && 'undefined' !== typeof event.target.dataset.lightboxSuccess
              && "function" === typeof window[event.target.dataset.lightboxSuccess]) {
              window[event.target.dataset.lightboxSuccess](html, status);
            }
            if(dataset && 'undefined' !== typeof event.target.dataset.lightboxClose) {
              $('#modal').modal('hide');
              if("function" === typeof window[event.target.dataset.lightboxClose]) {
                window[event.target.dataset.lightboxClose](html, status);
              }
            } else {
              updateLightbox(html, status);
            }
          },
          error: function(e) {
            $('body').removeClass('modal-open').html('<div>'+e.responseText+'</div>');
            $('#modal').addClass('hidden');
            $('.modal-link').on('click', constrainLink);
          }
        });
        if(!lightboxShown) {
          $('#modal').modal('show');
          lightboxShown = true;
        }
        return false;
      });
    }
  }
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