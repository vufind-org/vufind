/* global path, vufindString */

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
      if(!request[name]) request[name] = [];
      request[name][request[name].length] = pair[1];
    } else {
      request[name] = decodeURIComponent(pair[1]);
    }
  }
  return request;
}

function moreFacets(id) {
  $('#narrowGroupHidden_'+id).removeClass('hidden');
  $('#more'+id).addClass('hidden');
}
function lessFacets(id) {
  $('#narrowGroupHidden_'+id).addClass('hidden');
  $('#more'+id).removeClass('hidden');
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
  $('.autocomplete').typeahead({
    source:function(query, process) {
      var searcher = extractClassParams($('.autocomplete').attr('class'));
      $.ajax({
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
});