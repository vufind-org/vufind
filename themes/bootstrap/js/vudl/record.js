// ====== GET VIEWS ====== //
var currentType = 'imaginary';
var currTab = 'medium-tab';
var updateFunction;
function ajaxGetView(pageObject) {
  pageObject['counts'] = counts;
  if(currentType != pageObject['filetype']) {
    $.ajax({
      type: 'POST',
      url : '../VuDL/ajax?method=viewLoad',
      data: pageObject,
      success: function(e) {
        $('#view').html(e.data);
        currentType = pageObject['filetype'];
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
  updateTechInfo(pageObject['id']);
}
function updateTechInfo(id) {
  $.ajax({dataType:'json',
    url:'../VuDL/ajax?method=getTechInfo&id='+id,        
    success:function(d){
      $('#techinfo').html(d.data.div);
      $('#download-button small').html(d.data.type+' ~ '+d.data.size);
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
  $('.page-link').each(function(index, value) {
    if(chunk && $(value).hasClass('onscreen')) {
      max = parseInt($(value).attr('title'));
      if(min < 0) min = max;
      $(value).removeClass('onscreen');
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
    url:'../VuDL/ajax?method=pageAjax&record='+documentID+'&start='+min+'&end='+max,
    dataType:'json',
    success : function(response) {
      loadWait = false;
      //console.log('return');
      // For each page
      for(var i=0;i<response.data.length;i++) {
        var page = response.data.outline[response.data.start];
        if(page == undefined) continue;
        $('.page-link#item'+response.data.start)
          .attr('onClick','ajaxGetView('+JSON.stringify(page).replace(/"/g, "'")+', this)')
          .attr('title',page.id)
          .attr('alt',page.label)
          .attr('id', 'item'+response.data.start)
          .html('<img class="img-polaroid" src="'+page.thumbnail+'"/><br/>'+page.label)
          .addClass('pointer')
          .removeClass('loading')
          .removeClass('onscreen');
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

function prevPage() {
  $('.page-link.alert-info').prev('.page-link').click();
  scrollToSelected();
}
function nextPage() {  
  $('.page-link.alert-info').next('.page-link').click();
  scrollToSelected();
}
function scrollToSelected() {
  $('#list0').scrollTop($('#list0').scrollTop()+$('#list0 .alert-info').offset().top-320);
}

$(document).ready(function() {
  $('.page-link').click(function() {
    $('.page-link.alert-info').removeClass('alert-info');
    $(this).addClass('alert-info');
  });  
  // Scroll Event
  $('.item-list').scroll(function() {
    // Flag pages on screen    
    $('.page-link').removeClass('onscreen').each(function(index, value) {
      if($(value).offset().top > 0
      && $(value).offset().top < $('#list0').height()+300
      && $(value).hasClass('loading')) {
        $(value).addClass('onscreen').removeClass('muted');
      }
    });
    if(loadWait) return;
    loadWait = true;
    findVisible();
  });
  // Accordion size  
  $('.item-list').css({
    'height':Math.max(200, $('#view').height()-$('#side-nav').position()-2),
    'overflow-y':'auto'
  })
  // Initial events
  ajaxGetView(initPage);
  scrollToSelected();
});