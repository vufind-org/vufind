/*global Hunt, VuFind */

VuFind.register('itemStatuses', function ItemStatuses() {
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
    $item.addClass('js-item-done').removeClass('js-item-pending');
    $item.find('.status').empty().append(result.availability_message);
    $item.find('.ajax-availability').removeClass('ajax-availability hidden');
    if (typeof(result.error) != 'undefined'
          && result.error.length > 0
    ) {
      $item.find('.callnumAndLocation').empty().addClass('text-danger').append(result.error);
      $item.find('.callnumber,.hideIfDetailed,.location').addClass('hidden');
    } else if (typeof(result.full_status) != 'undefined'
          && result.full_status.length > 0
          && $item.find('.callnumAndLocation').length > 0
    ) {
      // Full status mode is on -- display the HTML and hide extraneous junk:
      $item.find('.callnumAndLocation').empty().append(result.full_status);
      $item.find('.callnumber,.hideIfDetailed,.location,.status').addClass('hidden');
    } else if (typeof(result.missing_data) !== 'undefined'
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

  var ItemStatusHandler = {
    name: "default",
    // Object that holds item IDs, states and elements
    items: {},
    url: '/AJAX/JSON?method=getItemStatuses',
    itemStatusRunning: false,
    dataType: 'json',
    method: 'POST',
    itemStatusTimer: null,
    itemStatusDelay: 200,

    checkItemStatusDone: function checkItemStatusDone(response) {
      var data = response.data;
      for (var j = 0; j < data.statuses.length; j++) {
        var status = data.statuses[j];
        this.items[status.id].result = status;
        this.items[status.id].state = 'done';
        for (var e = 0; e < this.items[status.id].elements.length; e++) {
          displayItemStatus(status, this.items[status.id].elements[e]);
        }
      }
    },
    itemStatusFail: function itemStatusFail(response, textStatus) {
      if (textStatus === 'error' || textStatus === 'abort' || typeof response.responseJSON === 'undefined') {
        return;
      }
      // display the error message on each of the ajax status place holder
      $('.js-item-pending .callnumAndLocation').addClass('text-danger').empty().removeClass('hidden')
        .append(typeof response.responseJSON.data === 'string' ? response.responseJSON.data : VuFind.translate('error_occurred'));
    },
    itemQueueAjax: function itemQueueAjax(id, el) {
      el.addClass('js-item-pending').removeClass('hidden');
      el.find('.callnumAndLocation').removeClass('hidden');
      el.find('.callnumAndLocation .ajax-availability').removeClass('hidden');
      el.find('.status').removeClass('hidden');
      // If this id has already been queued, just add it to the elements or display a
      // cached result.
      if (typeof this.items[id] !== 'undefined') {
        if ('done' === this.items[id].state) {
          displayItemStatus(this.items[id].result, el);
        } else {
          this.items[id].elements.push(el);
        }
        return;
      }
      clearTimeout(this.itemStatusTimer);
      this.items[id] = {
        id: id,
        state: 'queued',
        elements: [el]
      };
      this.itemStatusTimer = setTimeout(this.runItemAjaxForQueue.bind(this), this.itemStatusDelay);
    },

    runItemAjaxForQueue: function runItemAjaxForQueue() {
      if (this.itemStatusRunning) {
        this.itemStatusTimer = setTimeout(this.runItemAjaxForQueue.bind(this), this.itemStatusDelay);
        return;
      }
      var ids = [];
      var self = this;
      $.each(this.items, function selectQueued() {
        if ('queued' === this.state) {
          self.items[this.id].state = 'running';
          ids.push(this.id);
        }
      });
      $.ajax({
        dataType: this.dataType,
        method: this.method,
        url: VuFind.path + this.url,
        context: this,
        data: { 'id': ids }
      })
        .done(this.checkItemStatusDone)
        .fail( this.itemStatusFail)
        .always(function queueAjaxAlways() {
          this.itemStatusRunning = false;
        });
    }//end runItemAjaxForQueue
  };

  //add you own overridden handler here
  var OdItemStatusHandler = Object.create(ItemStatusHandler);
  OdItemStatusHandler.url = '/Overdrive/getStatus';
  OdItemStatusHandler.itemStatusDelay = 200;
  OdItemStatusHandler.name = "overdrive";
  OdItemStatusHandler.items = {};

  //store the handlers in a "hash" obj
  var checkItemHandlers = {
    'ils': ItemStatusHandler,
    'overdrive': OdItemStatusHandler,
  };

  function checkItemStatus(el) {
    var $item = $(el);
    if ($item.hasClass('js-item-pending') || $item.hasClass('js-item-done')) {
      return;
    }
    if ($item.find('.hiddenId').length === 0) {
      return false;
    }
    var id = $item.find('.hiddenId').val();
    var handlerName = 'ils';
    if ($item.data("handler-name")) {
      handlerName = $item.data("handler-name");
    } else if ($item.find('.handler-name').length > 0) {
      handlerName = $item.find('.handler-name').val();
    }

    //queue the element into the handler
    checkItemHandlers[handlerName].itemQueueAjax(id, $item);
  }

  function checkItemStatuses(_container) {
    var container = typeof _container === 'undefined'
      ? document.body
      : _container;

    var ajaxItems = $(container).find('.ajaxItem');
    for (var i = 0; i < ajaxItems.length; i++) {
      checkItemStatus($(ajaxItems[i]));
    }
  }
  function init(_container) {
    if (typeof Hunt === 'undefined') {
      checkItemStatuses(_container);
    } else {
      var container = typeof _container === 'undefined'
        ? document.body
        : _container;
      new Hunt(
        $(container).find('.ajaxItem').toArray(),
        { enter: checkItemStatus }
      );
    }
  }

  return { init: init, check: checkItemStatuses, checkRecord: checkItemStatus };
});
