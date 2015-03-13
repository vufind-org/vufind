// ====== GET VIEWS ====== //
var currentType = 'imaginary';
var currentTab = 'medium-tab';
var updateFunction;
var currentID = false;
var viewLoadAjax = false;
function ajaxGetView(pageObject) {
  pageObject['counts'] = counts;
  if (currentTab == 'master-tab' && currentID == pageObject['id']) {
    // Trigger file download
    //alert('download');
    $('#file-download').submit();
  } else if (currentType != pageObject['filetype']) {
    if(viewLoadAjax) {
      viewLoadAjax.abort();
    }
    viewLoadAjax = $.ajax({
      type: 'POST',
      url : '../VuDL/ajax?method=viewLoad',
      data: pageObject,
      success: function(e) {
        $('#view').html(e.data);
        currentType = pageObject['filetype'];
        var tab = $('#'+currentTab, e.data);
        if(tab.length > 0) {
          tab.click();
        } else {
          currentTab = $('.nav-tabs li a:eq(0)')[0].id;
        }
      },
      error: function(d,e){
        console.log(d.responseText);
        console.log(e);
      },
      dataType: 'json'
    });
  } else {
    updateFunction(pageObject, currentTab);
  }
  updateTechInfo(pageObject);
  currentID = pageObject['id'];
}
function updateTechInfo(record) {
  $.ajax({dataType:'json',
    type:'post',
    url:path+'/VuDL/ajax?method=getTechInfo',
    data:record,
    success:function(d) {
      $('#techinfo').html(d.data.div);
      var downloadSrc = 'MASTER';
      if(typeof d.data.type !== "undefined") {
        if(d.data.type.indexOf('image') > -1) {
          downloadSrc = 'LARGE';
          d.data.type = 'image/png';
        } else if(d.data.type.indexOf('audio') > -1) {
          downloadSrc = 'MP3';
          d.data.type = 'audio/mp3';
        }
        $('#download-button .details').html(d.data.type+' ~ '+d.data.size);
      }
      $('#file-download').attr('action', path+'/files/'+record.id+'/'+downloadSrc+'?download=true');
    },
    error:function(d,e) {
      console.log(d.responseText);
      console.log(e);
    }
  });
}
// ====== GET MORE THUMBNAILS ====== //
var loadWait = false;
// AJAX load all records flagged as on screen
function findVisible() {
  var min = -1,max;
  // Flag pages on screen
  $('.page-link.unloaded').each(function(index, item) {
    var listID = '#collapse'+currentList;
    if($(item).offset().top > $(listID).position().top-vudlSettings.scroll.top
    && $(item).offset().top < $(listID).position().top+$(listID).height()+vudlSettings.scroll.bottom
    && $(item).hasClass('unloaded')) {
      $(item).addClass('loading');
      max = parseInt($(item).attr('title'));
      if(min < 0) min = max;
    }
  });
  if(min > -1) {
    ajaxLoadPages(min, max+2);
  } else {
    loadWait = false;
  }
}
// AJAX Handling
function ajaxLoadPages(min, max) {
  //console.log('ajax', min, max, counts);
  $.ajax({
    url:path+'/VuDL/ajax?method=pageAjax&record='+documentID+'&start='+min+'&end='+max,
    dataType:'json',
    success : function(response) {
      loadWait = false;
      // For each page
      for(var i=0;i<response.data.length;i++) {
        var page = response.data.outline[response.data.start];
        if(page == undefined) continue;
        var img = $('<img src="'+page.thumbnail+'"/>');
        $('.page-link#item'+response.data.start)
          .attr('onClick','ajaxGetView('+JSON.stringify(page).replace(/"/g, "'")+', this)')
          .attr('title',page.id)
          .attr('alt',page.label)
          .attr('id', 'item'+response.data.start)
          .html('<br/>'+page.label)
          .prepend(img)
          .addClass('active')
          .removeClass('loading')
          .removeClass('unloaded');
        response.data.start++;
      }
      findVisible();
    },
    error : function(d,e){
      console.log(d.responseText);
      console.log(e);
    }
  });
}
// Pages
function prevPage() {
  $('.page-link.selected').prev('.page-link').click();
  scrollToSelected();
}
function nextPage() {
  $('.page-link.selected').next('.page-link').click();
  scrollToSelected();
}
function scrollToSelected() {
  var listID = '#collapse'+currentList;
  if($(listID).length > 0 && $(listID+' .selected').length > 0) {
    $(listID).finish();
    scrollAnimation = $(listID).animate({

      scrollTop: $(listID+' .selected').offset().top-$(listID).offset().top+$(listID).scrollTop()-12
    });
  }
}
// Toggle side menu
function toggleSideNav() {
  $('#side-nav').toggle();
  var opener = $('#view .nav-tabs li.opener a');
  opener.toggleClass('hidden');
  $('#view').toggleClass('col-sm-9').toggleClass('col-sm-12');
}

function resizeElements() {
  var $height = $(window).height() + window.scrollY - $('.panel-collapse.in').offset().top - 50;
  $('.panel-collapse').css('max-height', Math.max(300, Math.min($height, $(window).height() - 200)));
}

// Ready? Let's go
$(document).ready(function() {
  $('.page-link').click(function() {
    $('.page-link.selected').removeClass('selected');
    $(this).addClass('selected');
    var list = parseInt($(this).parents('.item-list').attr('list-index'));
    if(counts[list] > 1) {
      $('.sibling-form .turn-button').removeClass('hidden');
    } else {
      $('.sibling-form .turn-button').addClass('hidden');
    }
  });
  // Load clicked items
  $('.unloaded').click(function() {
    scrollToSelected();
    findVisible();
  });
  // Scroll Event
  $('.item-list').parent().scroll(function() {
    if(loadWait) return;
    loadWait = true;
    findVisible();
  });
  // Side nav toggle
  $('#side-nav-toggle').click(toggleSideNav);
  setTimeout(findVisible, 1000);
  ajaxGetView(initPage);
  // Track top item in the scroll bar
  $('.accordion-body').scroll(function(e){
    var pageLinks = $('.page-link');
    for(var i=0;i<pageLinks.length;i++) {
      if($(pageLinks[i]).position().top >= 0) {
        topScrollItem = pageLinks[i].id;
        break;
      }
    }
  });
  $('.panel-title a').click(function() {
    if($(this).attr('href') == "#collapse_details") {
      return;
    }
    currentList = parseInt($(this).attr('href').substring(9));
  });
  scrollToSelected();
  resizeElements();
  $( window ).resize( resizeElements );
  $( window ).scroll( resizeElements );
});
