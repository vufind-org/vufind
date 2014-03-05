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
        $('.tab-content').css('min-height',tabMinHeight-60);
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
    success:function(d){
      $('#techinfo').html(d.data.div);
      $('#file-download').attr('action', path+'/files/'+record.id+'/MASTER?download=true');
      $('#download-button small').html(d.data.type+' ~ '+d.data.size);
      $('#allFiles').addClass('in');
    },
    error:function(d,e){
      console.log(d.responseText);
      console.log(e);
    }
  });
}

// ====== GET MORE THUMBNAILS ====== //
var loadWait = false;
// AJAX load all records flagged as on screen
function findVisible() {
  var chunk = true,min = -1,max;
  // Flag pages on screen
  $('.page-link').each(function(index, value) {
    if($(value).offset().top > 0
    && $(value).offset().top < $('#list0').height()+300
    && $(value).hasClass('loading')) {
      $(value).removeClass('muted');
      $(value).removeClass('loading');
      max = parseInt($(value).attr('title'));
      if(min < 0) min = max;
    } else {
      if(min > -1) chunk = false;
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
          .addClass('pointer')
          .removeClass('loading')
          .removeClass('onscreen')
          .removeClass('muted');
        response.data.start++;
      }
      if($('.onscreen').length > 0) {
         //console.log('go again');
         findVisible();
      }
    },
    error : function(d,e){
      console.log(d.responseText);
      console.log(e);
    }
  });
}
// Pages
function prevPage() {
  $('.page-link.alert-info').prev('.page-link').click();
  scrollToSelected();
}
function nextPage() {  
  $('.page-link.alert-info').next('.page-link').click();
  scrollToSelected();
}
function scrollToSelected() {
  $('#collapse1').scrollTop($('#collapse1').scrollTop()+$('#collapse1 .alert-info').offset().top-320);
}
// Accordion size
var tabMinHeight = 1;
function resizeAccordions(offset) {
  var height = $(window).innerHeight()-40;
  $('.tab-content').css('min-height',height-$('.tab-content').position().top-46);
  var accordionHeight = height-179-($('#side-nav .accordion-heading').length-2)*30;
  // All accordions
  $('#side-nav .accordion-body').css({
    'max-height':accordionHeight,
    'overflow-y':'auto'
  });
  // Set height in the open one
  $('#side-nav .accordion-body.in').css({
    'height':accordionHeight
  });
  $('.zoomy-container').css('height',height-$('.zoomy-container').parent().position().top+2);
}
$(document).ready(function() {
  $('.page-link').click(function() {
    $('.page-link.alert-info').removeClass('alert-info');
    $(this).addClass('alert-info');
  });  
  // Load clicked items
  $('.loading').click(function() {
    scrollToSelected();
    findVisible();
    });
  // Scroll Event
  $('.item-list').parent().scroll(function() {
    if(loadWait) return;
    loadWait = true;
    findVisible();
  });
  // Initial events
  ajaxGetView(initPage);
  findVisible();
});
  // Accordion size
$( window ).resize( function(){resizeAccordions()} );
// Toggle side menu
function toggleSideNav() {
  $('#side-nav').toggle();
  var opener = $('#view .nav-tabs li.opener a');
  if(opener.is(":visible")) {
    opener.hide();
  } else {
    opener.css('display','inherit');
  }
  $('#view').toggleClass('wide');
  $('#zoom').zoomyResize();
}