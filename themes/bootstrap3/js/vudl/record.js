// ====== GET VIEWS ====== //
var currentType = 'imaginary';
var currTab = 'medium-tab';
var updateFunction;
var lastID = false;
function ajaxGetView(pageObject) {
  pageObject['counts'] = counts;
  if (currTab == 'master-tab' && lastID == pageObject['id']) {
    // Trigger file download
    //alert('download');
    $('#file-download').submit();
  } else if (currentType != pageObject['filetype']) {
    $.ajax({
      type: 'POST',
      url : '../VuDL/ajax?method=viewLoad',
      data: pageObject,
      success: function(e) {
        $('#view').html(e.data);
        currentType = pageObject['filetype'];
        var tab = $('#'+currTab, e.data);
        if(tab.length > 0) {
          tab.click();
        } else {
          currTab = $('.nav-tabs li a:eq(0)')[0].id;
        }
        // Accordion size
        resizeAccordions();
      },
      error: function(d,e){
        console.log(d.responseText);
        console.log(e);
      },
      dataType: 'json'
    });
  } else {
    updateFunction(pageObject);
    $('#'+currTab).click();
  }
  updateTechInfo(pageObject);
  lastID = pageObject['id'];
}
function updateTechInfo(record) {
  $.ajax({dataType:'json',
    type:'post',
    url:path+'/VuDL/ajax?method=getTechInfo',
    data:record,
    success:function(d) {
      $('#techinfo').html(d.data.div);
      $('#file-download').attr('action', path+'/files/'+record.id+'/MASTER?download=true');
      $('#download-button .details').html(d.data.type+' ~ '+d.data.size);
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
    if($(item).offset().top > $('#collapse1').position().top-vudlSettings.scroll.top
    && $(item).offset().top < $('#collapse1').position().top+$('#collapse1').height()+vudlSettings.scroll.bottom
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
  $('#collapse1').animate({
    scrollTop: $('#collapse1 .selected').offset().top-$('#collapse1').offset().top+$('#collapse1').scrollTop()-12
  });
}
function scrollAdjust() {
  $('#collapse1').scrollTop($('#'+topScrollItem).offset().top-$('#collapse1').offset().top+$('#collapse1').scrollTop());
}
// Accordion size
var vudlAccordionHeight
function resizeAccordions(offset) {
  vudlAccordionHeight = window.innerHeight // Window height
    // Add scroll distance
    + Math.min($('#side-nav').position().top, document.body.scrollTop)
    // Minus the top of the accordion
    - $('#side-nav').position().top
    // Minus the target distance from the bottom
    - vudlSettings.accordion.bottom
    // Subtract height of the headers
    - ($('#side-nav .accordion-heading').length*vudlSettings.accordion.headerHeight);
  // All accordions
  $('#side-nav .panel-collapse').css({
    'max-height':vudlAccordionHeight,
    'overflow-y':'auto'
  });
  $('#zoom').css({
    'height':vudlAccordionHeight,
    'overflow-y':'auto'
  });
}
// Toggle side menu
function toggleSideNav() {
  $('#side-nav').toggle();
  var opener = $('#view .nav-tabs li.opener a');
  opener.toggleClass('hidden');
  $('#view').toggleClass('col-sm-9').toggleClass('col-sm-12');
}
// Ready? Let's go
$(document).ready(function() {
  $('.page-link').click(function() {
    $('.page-link.selected').removeClass('selected');
    $(this).addClass('selected');
    var list = parseInt($(this).parents('.item-list').attr('list-index'));
    if(counts[list] > 1) {
      $('.siblings-form .turn-button').removeClass('hidden');
    } else {
      $('.siblings-form .turn-button').addClass('hidden');
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
});
// Initial alignment
$( window ).load( scrollToSelected );
// Accordion size
$( window ).resize( resizeAccordions );
$( document ).scroll( resizeAccordions );