/**
 * The page loading, recursive function
 */
var loading_pages = true;
function ajaxLoadPages(response) {
  if(!loading_pages) return;
  // If we got any pages (length is set in the controller)
  if(response.data !== false && response.data.length > 0) {
    // For each page
    var trueStart = response.data.start < 0 ? 0 : response.data.start;
    for(var i=0;i<response.data.length;i++) {
      var page = response.data.outline[response.data.start];
      if(page == undefined) continue;
      $('.page-grid#item'+response.data.start)
        .attr('href','../Item/'+page.id)
        .attr('title',page.id)
        .attr('id', 'item'+response.data.start)
        .html('<div class="imgc"><img src="'+page.thumbnail+'"/></div>'+page.label)
        .removeClass('loading')
        .removeClass('onscreen');
      response.data.start++;
    }
    // Call the next round of page loading
    if(loading_pages) {
      //console.log(trueStart,response.data.start-1);
      var target = Math.min(counts[0], Math.floor($('#list0').scrollTop()/159));
      $.ajax({
        url:'../VuDL/ajax?method=pageAjax&record='+documentID+'&start='+response.data.start+'&end='+(response.data.start+response.data.length),
        dataType:'json',
        success :ajaxLoadPages,
        error   :function(d,e){
          console.log(d.responseText);
          console.log(e);
        }
      });
    }
  // When we're done (no pages returned)
  } else {
    //console.log('done');
    loading_pages = false;
  }
};
