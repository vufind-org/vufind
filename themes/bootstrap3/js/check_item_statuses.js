/*global Element, Hunt, VuFind */
/*exported checkItemStatuses, itemStatusFail */

function linkCallnumbers(callnumber, callnumber_handler) {
  if (callnumber_handler) {
    var cns = callnumber.split(',\t');
    for (var i = 0; i < cns.length; i++) {
      cns[i] = '<a href="' + VuFind.path + '/Alphabrowse/Home?source=' + encodeURI(callnumber_handler) + '&amp;from=' + encodeURI(cns[i]) + '">' + cns[i] + '</a>';
    }
    return cns.join(',\t');
  }
  return callnumber;
}
function displayItemStatus(result, $item) {
   console.log("displayItemStatus"+result.full_status);
  $item.removeClass('js-item-pending');
  $item.find('.status').empty().append(result.availability_message);
  $item.find('.ajax-availability').removeClass('ajax-availability hidden');
  if (typeof(result.full_status) != 'undefined'
    && result.full_status.length > 0
    && $item.find('.callnumAndLocation').length > 0
  ) {
      
      console.log("full status:"+result.full_status);
    // Full status mode is on -- display the HTML and hide extraneous junk:
    $item.find('.callnumAndLocation').empty().append(result.full_status);
    $item.find('.callnumber,.hideIfDetailed,.location,.status').addClass('hidden');
  } else if (typeof(result.missing_data) != 'undefined'
    && result.missing_data
  ) {
    // No data is available -- hide the entire status area:
    $item.find('.callnumAndLocation,.status').addClass('hidden');
  } else if (result.locationList) {
    // We have multiple locations -- build appropriate HTML and hide unwanted labels:
    $item.find('.callnumber,.hideIfDetailed,.location').addClass('hidden');
    var locationListHTML = "";
    for (var x = 0; x < result.locationList.length; x++) {
      locationListHTML += '<div class="groupLocation">';
      if (result.locationList[x].availability) {
        locationListHTML += '<span class="text-success"><i class="fa fa-ok" aria-hidden="true"></i> '
          + result.locationList[x].location + '</span> ';
      } else if (typeof(result.locationList[x].status_unknown) !== 'undefined'
          && result.locationList[x].status_unknown
      ) {
        if (result.locationList[x].location) {
          locationListHTML += '<span class="text-warning"><i class="fa fa-status-unknown" aria-hidden="true"></i> '
            + result.locationList[x].location + '</span> ';
        }
      } else {
        locationListHTML += '<span class="text-danger"><i class="fa fa-remove" aria-hidden="true"></i> '
          + result.locationList[x].location + '</span> ';
      }
      locationListHTML += '</div>';
      locationListHTML += '<div class="groupCallnumber">';
      locationListHTML += (result.locationList[x].callnumbers)
           ? linkCallnumbers(result.locationList[x].callnumbers, result.locationList[x].callnumber_handler) : '';
      locationListHTML += '</div>';
    }
    $item.find('.locationDetails').removeClass('hidden');
    $item.find('.locationDetails').html(locationListHTML);
  } else {
    // Default case -- load call number and location into appropriate containers:
    $item.find('.callnumber').empty().append(linkCallnumbers(result.callnumber, result.callnumber_handler) + '<br/>');
    $item.find('.location').empty().append(
      result.reserve === 'true'
        ? result.reserve_message
        : result.location
    );
  }
}

/*
var itemStatusIds = [];
var itemStatusEls = {};
var itemStatusTimer = null;
var itemStatusDelay = 200;
var itemStatusRunning = false;

function runItemAjaxForQueue() {
  // Only run one item status AJAX request at a time:
  if (itemStatusRunning) {
    itemStatusTimer = setTimeout(runItemAjaxForQueue, itemStatusDelay);
    return;
  }
  
  itemStatusRunning = true;
  $.ajax({
    dataType: 'json',
    method: 'POST',
    url: VuFind.path + '/AJAX/JSON?method=getItemStatuses',
    data: { 'id': itemStatusIds }
  })
  .done(function checkItemStatusDone(response) {
    for (var j = 0; j < response.data.length; j++) {
      displayItemStatus(response.data[j], itemStatusEls[response.data[j].id]);
      itemStatusIds.splice(itemStatusIds.indexOf(response.data[j].id), 1);
    }
    itemStatusRunning = false;
  })
  .fail(function checkItemStatusFail(response, textStatus) {
    itemStatusFail(response, textStatus);
    itemStatusRunning = false;
  });
}

function itemQueueAjax(id, el) {
  if (el.hasClass('js-item-pending')) {
    return;
  }
  clearTimeout(itemStatusTimer);
  itemStatusIds.push(id);
  itemStatusEls[id] = el;
  itemStatusTimer = setTimeout(runItemAjaxForQueue, itemStatusDelay);
  el.addClass('js-item-pending').removeClass('hidden');
  el.find('.status').removeClass('hidden');
}
*/
/*
function checkItemStatus(el) {
  var $item = $(el);
  if ($item.find('.hiddenId').length === 0) {
    return false;
  }
  var id = $item.find('.hiddenId').val();
  itemQueueAjax(id + '', $item);
}
*/
var itemStatusObserver = null;

var ItemStatusHandler = {
    name: "default",
    //array to hold IDs and elements
    itemStatusIds: [], itemStatusEls: [],
    url: '/AJAX/JSON?method=getItemStatuses',
    itemStatusRunning : false,
    dataType: 'json',
    method: 'POST',
    itemStatusTimer : null,
    itemStatusDelay : 200,
    
    checkItemStatusDone: function(response) {    
        console.log("status:"+response.status);
        var data = response.data;
        for (var j = 0; j < data.length; j++) {
          displayItemStatus(data[j], this.itemStatusEls[data[j].id]);
          this.itemStatusIds.splice(this.itemStatusIds.indexOf(data[j].id), 1);
        }
      },
    itemStatusFail: function(response,textStatus) {
console.log("failed: "+ response.statusText);
console.log(response.responseText);
          if (textStatus === 'error' ||textStatus === 'abort' || typeof response.responseJSON === 'undefined') {
            return;
          }
          // display the error message on each of the ajax status place holder
          $('.js-item-pending').addClass('text-danger').append(response.responseJSON.data);
        },
    itemQueueAjax: function(id, el){

      clearTimeout(this.itemStatusTimer);
      this.itemStatusIds.push(id);
      this.itemStatusEls[id] = el;
      this.itemStatusTimer = setTimeout(this.runItemAjaxForQueue.bind(this), this.itemStatusDelay);
      el.addClass('js-item-pending').removeClass('hidden');
      el.find('.status').removeClass('hidden');
      console.log("after this"+this.name);
      console.log(this.itemStatusIds);
    },
    
    runItemAjaxForQueue : function(){
       if (this.itemStatusRunning) {
          this.itemStatusTimer = setTimeout(this.runItemAjaxForQueue.bind(this), this.itemStatusDelay);
         return;
       }

       console.log("ajax call: "+VuFind.path+this.url);

      $.ajax({
        dataType: this.dataType,
        method: this.method,
        url: VuFind.path+this.url,
        context: this,
        data: { 'id': this.itemStatusIds }
      })
      .done(this.checkItemStatusDone)
      .fail( this.itemStatusFail)
      .always(function(){
          this.itemStatusRunning = false;
      });
    }//end runItemAjax
}

//extend default handler and override any properties
var OdItemStatusHandler=Object.create(ItemStatusHandler);
  OdItemStatusHandler.url = '/Overdrive/getStatus';
  OdItemStatusHandler.itemStatusDelay = 200;
  OdItemStatusHandler.name = "odh";
  OdItemStatusHandler.itemStatusIds = [];
  OdItemStatusHandler.itemStatusEls = [];

//store the handlers in a "hash" obj
var handlers = {
    'ils':ItemStatusHandler,
    'overdrive':OdItemStatusHandler}

console.log(VuFind);
console.log(VuFind.path);

function checkItemStatus(el) {
console.log("checkITemStatus");
    var $item = $(el);
    var handlerName='ils';
    if ($item.hasClass('js-item-pending')) {
      return;
    }
    if ($item.find('.hiddenId').length === 0) {
      return false;
    }
    var id = $item.find('.hiddenId').val();
    if ($item.find('.hiddenHandler').length > 0) {
      handlerName=$item.find('.hiddenHandler').val();
    }
console.log("id:"+id);
console.log("here:"+$item.data("handler-name"));

    if($item.data("handlerName")) {
      handlerName=$item.data("handlerName");
    }
console.log("hdler:"+handlerName);
    //queue the element into the handler
    handlers[handlerName].itemQueueAjax(id, $item);
      /*
      clearTimeout(handlers[handlerName].itemStatusTimer);
      handlers[handlerName].itemStatusIds.push(id);
      handlers[handlerName].itemStatusEls[id] = el;
      handlers[handlerName].itemStatusTimer = setTimeout(runItemAjaxForQueue, itemStatusDelay);
      el.addClass('js-item-pending').removeClass('hidden');
      el.find('.status').removeClass('hidden');
      */
    }    
    
function checkItemStatuses(_container) {
  var container = _container instanceof Element
    ? _container
    : document.body;

  var ajaxItems = $(container).find('.ajaxItem');
  for (var i = 0; i < ajaxItems.length; i++) {
    var id = $(ajaxItems[i]).find('.hiddenId').val();
    var handlerName = $(ajaxItems[i]).find('.handler').val();
    if(!handlerName){handlerName='ils';}
    handlers[handlerName].itemQueueAjax(id, $(ajaxItems[i]));
  }
  // Stop looking for a scroll loader
  if (itemStatusObserver) {
    itemStatusObserver.disconnect();
  }
}
$(document).ready(function checkItemStatusReady() {
  if (typeof Hunt === 'undefined') {
    checkItemStatuses();
  } else {
    itemStatusObserver = new Hunt(
      $('.ajaxItem').toArray(),
      { enter: checkItemStatus }
    );
  }
});
