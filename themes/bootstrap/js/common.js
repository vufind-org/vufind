/*global path, vufindString */

/* --- GLOBAL FUNCTIONS --- */
function htmlEncode(value){
  if (value) {
    return jQuery('<div />').text(value).html();
  } else {
    return '';
  }
}
function extractClassParams(str) {
  str = $(str).attr('class');
  var params = {};
  var classes = str.split(/\s+/);
  for(var i = 0; i < classes.length; i++) {
    if (classes[i].indexOf(':') > 0) {
      var pair = classes[i].split(':');
      params[pair[0]] = pair[1];
    }
  }
  return params;
}
// Turn GET string into array
function deparam(url) {
  var request = {};
  var pairs = url.substring(url.indexOf('?') + 1).split('&');
  for (var i = 0; i < pairs.length; i++) {
    var pair = pairs[i].split('=');
    var name = decodeURIComponent(pair[0]);
    if(pair[0].substring(pair[0].length-2) == '[]') {
      if(!request[name]) {
        request[name] = [];
      }
      request[name][request[name].length] = pair[1];
    } else {
      request[name] = decodeURIComponent(pair[1]);
    }
  }
  return request;
}

function moreFacets(id) {
  $('.'+id).removeClass('hidden');
  $('#more-'+id).addClass('hidden');
}
function lessFacets(id) {
  $('.'+id).addClass('hidden');
  $('#more-'+id).removeClass('hidden');
}

// Advanced facets
function updateOrFacets(url, op) {
  window.location.assign(url);
  var list = $(op).parents('ul');
  var header = $(list).find('li.nav-header');
  list.html(header[0].outerHTML+'<div class="alert alert-info">'+vufindString.loading+'...</div>');
}
function setupOrFacets() {
  $('.facetOR').find('.icon-check').replaceWith('<input type="checkbox" checked onChange="updateOrFacets($(this).parent().parent().attr(\'href\'), this)"/>');
  $('.facetOR').find('.icon-check-empty').replaceWith('<input type="checkbox" onChange="updateOrFacets($(this).parent().attr(\'href\'), this)"/> ');
}

$(document).ready(function() {
  // Highlight previous links, grey out following
  $('.backlink')
    .mouseover(function() {
      // Underline back
      var t = $(this);
      do {
        t.css({'text-decoration':'underline'});
        t = t.prev();
      } while(t.length > 0);
      // Mute ahead
      t = $(this).next();
      do {
        t.css({'color':'#999'});
        t = t.next();
      } while(t.length > 0);
    })
    .mouseout(function() {
      // Underline back
      var t = $(this);
      do {
        t.css({'text-decoration':'none'});
        t = t.prev();
      } while(t.length > 0);
      // Mute ahead
      t = $(this).next();
      do {
        t.css({'color':''});
        t = t.next();
      } while(t.length > 0);
    });

  // Search autocomplete
  var autoCompleteRequest, autoCompleteTimer;
  $('.autocomplete').typeahead({
    minLength:3,
    source:function(query, process) {
      clearTimeout(autoCompleteTimer);
      if(autoCompleteRequest) {
        autoCompleteRequest.abort();
      }
      var searcher = extractClassParams('.autocomplete');
      autoCompleteTimer = setTimeout(function() {
        autoCompleteRequest = $.ajax({
          url: path + '/AJAX/JSON',
          data: {method:'getACSuggestions',type:$('#searchForm_type').val(),searcher:searcher['searcher'],q:query},
          dataType:'json',
          success: function(json) {
            if (json.status == 'OK' && json.data.length > 0) {
              process(json.data);
            } else {
              process([]);
            }
          }
        });
      }, 600); // Delay request submission
    }
  });

  // Checkbox select all
  $('.checkbox-select-all').change(function() {
    $(this).closest('form').find('.checkbox-select-item').attr('checked', this.checked);
  });
  
  // handle QR code links
  $('a.qrcodeLink').click(function() {
    if ($(this).hasClass("active")) {
      $(this).html(vufindString.qrcode_show).removeClass("active");
    } else {
      $(this).html(vufindString.qrcode_hide).addClass("active");
    }
    $(this).next('.qrcode').toggle();
    return false;
  });

  // Print
  var url = window.location.href;
  if(url.indexOf('?' + 'print' + '=') != -1  || url.indexOf('&' + 'print' + '=') != -1) {
    $("link[media='print']").attr("media", "all");
    window.print();
  }
    
  // Collapsing facets
  $('.sidebar .collapsed .nav-header').click(function(){$(this).parent().toggleClass('open');});
  
  // Advanced facets
  setupOrFacets();
});