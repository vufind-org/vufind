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
  $('#modal').on('hide.bs.modal', function() {
    $('#modal .modal-body').html(vufindString.loading+'...');
    lightboxShown = false;
    refreshTags();
  });
});

function updateLightbox(html, link) {
  console.log('updateLightbox');
  $('#modal .modal-body').html(html);
  $('#modal').modal('handleUpdate');
  $('#modal .modal-body').on('click', '.modal-link', constrainLink);
  if("undefined" !== typeof link
  && "undefined" !== typeof link.dataset
  && "undefined" !== typeof link.dataset.lightboxClose) {
    var forms = $('#modal .modal-body form');
    for(var i=0;i<forms.length;i++) {
      forms[i].dataset.lightboxClose = 1;
    }
  }
  constrainForms();
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
 * data-lightbox-close   = close after success (present to close, set to function name to run)
 * data-lightbox-success = on success, run named function
 */
function constrainForms() {
  var forms = $('#modal form');
  for(var i=forms.length;i--;) {
    if('undefined' === typeof forms[i].action) {
      forms[i].action = path;
    }
    if(forms[i].action.length > 1) {
      forms[i].innerHTML += '<input type="hidden" name="layout" value="lightbox"/>';
      $(forms[i]).unbind('submit').bind('submit', function(event) {
        event.preventDefault();
        var data = $(event.target).serializeArray();
        var clicked = $(event.target).find('[clicked]');
        console.log(clicked);
        if(clicked.length > 0 && clicked.attr('name')) {
          data[data.length] = {'name':clicked.attr('name'), 'value':clicked.attr('value') || 1};
          if(clicked.data('lightbox-ignore')) {
            return true;
          }
        }
        console.log("Submit", {
          url: event.target.action || path,
          method: event.target.method || 'GET',
          data: data
        });
        $.ajax({
          url: event.target.action || path,
          method: event.target.method || 'GET',
          data: data,
          success: function(html, status) {
            console.log('success');
            var dataset = 'undefined' !== typeof event.target.dataset;
            if(dataset && 'undefined' !== typeof event.target.dataset.lightboxClose) {
              $('#modal').modal('hide');
              if("function" === typeof window[event.target.dataset.lightboxSuccess]) {
                window[event.target.dataset.lightboxSuccess](html, status);
              }
              if("function" === typeof window[event.target.dataset.lightboxClose]) {
                window[event.target.dataset.lightboxClose](html, status);
              }
            } else {
              if(dataset && 'undefined' !== typeof event.target.dataset.lightboxSuccess
                && "function" === typeof window[event.target.dataset.lightboxSuccess]) {
                window[event.target.dataset.lightboxSuccess](html, status);
              }
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
    // Highlight which submit button clicked
    $(forms[i]).find("input[type=submit]").click(function() {
      // Remove other clicks
      $(modal).find('input[type="submit"][clicked=true]').attr('clicked', false);
      // Add useful information
      $(this).attr("clicked", "true");
      // Add prettiness
      $('#modal .fa-spinner').remove();
      $(this).after(' <i class="fa fa-spinner fa-spin"></i> ');
    });
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