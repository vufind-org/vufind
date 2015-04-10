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
    updatePageForLogin();
    refreshTags();
  });
});

function updateLightbox(html) {
  $('#modal .modal-body').html(html);
  $('#modal').modal('handleUpdate');
  $('#modal .modal-body').on('click', 'a', constrainLink);
  constrainForms();
}

function lightboxAllowedPath(url) {
  var illegal = ['MyResearch/Home', 'Record/View'];
  for(var i=illegal.length;i--;) {
    if(url.match(illegal[i])) {
      return false;
    }
  }
  return true;
}
function constrainForms() {
  var forms = $('.modal form');
  for(var i=forms.length;i--;) {
    if('undefined' === typeof this.action) {
      this.action = path;
    }
    if(this.action.length > 1 && lightboxAllowedPath(this.action)) {
      forms[i].innerHTML += '<input type="hidden" name="layout" value="lightbox"/>';
      $(forms[i]).unbind('submit').bind('submit', function(event) {
        event.preventDefault();
        $.ajax({
          url: this.action || path,
          method: this.method || 'GET',
          data: $(this).serialize(),
          success: 'undefined' === typeof this.dataset.lightboxClose
            ? updateLightbox
            : function() { $('#modal').modal('hide'); },
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
  if(this.href.length > 1 && lightboxAllowedPath(this.href)) {
    event.preventDefault();
    var parts = this.href.split('#');
    parts[1] = parts.length < 2 ? '' : '#'+parts[1];
    var ajaxObj = {
      url: parts[0].indexOf('?') < 0
        ? parts[0]+'?layout=lightbox'+parts[1]
        : parts[0]+'&layout=lightbox'+parts[1],
      success: updateLightbox
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

function refreshTags() {
  var recordId = $('#record_id').val();
  var recordSource = $('.hiddenSource').val();

  // Update tag list (add tag)
  var tagList = $('#tagList');
  if (tagList.length > 0) {
    tagList.empty();
    var url = path + '/AJAX/JSON?' + $.param({method:'getRecordTags',id:recordId,'source':recordSource});
    $.ajax({
      dataType: 'json',
      url: url,
      success: function(response) {
        if (response.status == 'OK') {
          $.each(response.data, function(i, tag) {
            var href = path + '/Tag?' + $.param({lookfor:tag.tag});
            var html = (i>0 ? ', ' : ' ') + '<a href="' + htmlEncode(href) + '">' + htmlEncode(tag.tag) +'</a> (' + htmlEncode(tag.cnt) + ')';
            tagList.append(html);
          });
        } else if (response.data && response.data.length > 0) {
          tagList.append(response.data);
        }
      }
    });
  }
}