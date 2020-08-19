/*global VuFind, finna, L */
finna.linkedEvents = (function finnaLinkedEvents() {
  function getEvents(params, callback, append, container, showSpinner) {
    var limit = {'page_size': container.data('limit')};
    var lang = {};
    if ($('.linked-events-tabs-container').data('lang')) {
      lang = {'language': $('.linked-events-tabs-container').data('lang')};
    } else if ($('.linked-event-content').data('lang')) {
      lang = {'language': $('.linked-event-content').data('lang')};
    }
    params.query = $.extend(params.query, limit, lang);
    var spinner = null;
    if (typeof showSpinner === 'undefined' || showSpinner) {
      spinner = $('<i>').addClass('fa fa-spinner fa-spin');
      if (append) {
        container.find($('.linked-events-content')).append(spinner);
      } else {
        container.find($('.linked-events-content')).html(spinner);
      }
    } else {
      spinner = container.find('.fa-spinner');
    }
    var url = VuFind.path + '/AJAX/JSON?method=getLinkedEvents';
    $.ajax({
      url: url,
      dataType: 'json',
      data: {'params': params.query, 'url': params.url}
    })
      .done(function onGetEventsDone(response) {
        if (response.data) {
          callback(response.data, append, container);
        } else {
          var err = $('<div></div>').attr('class', 'linked-events-noresults infobox').text(VuFind.translate('nohit_heading'));
          container.find($('.linked-events-content')).html(err);
          container.find($('.linked-events-next')).addClass('hidden');
        }
        spinner.remove();
      })
      .fail(function getEventsFail(response/*, textStatus, err*/) {
        var err = '';
        if (typeof response.responseJSON !== 'undefined') {
          err = $('<div></div>').attr('class', 'alert alert-danger').text(response.responseJSON.data);
        }
        $('.linked-events-content').html(err);
      });
  }

  function initEventMap(coordinates) {
    var mapCanvas = $('.linked-events-map');
    var map = finna.map.initMap(mapCanvas, false, {'center': coordinates});
    var icon = L.divIcon({
      className: 'mapMarker',
      iconSize: null,
      html: '<div class="leaflet-marker-icon leaflet-zoom-animated leaflet-interactive"><i class="fa fa-map-marker open" style="position: relative; font-size: 35px;"></i></div>',
      iconAnchor: [10, 35],
      popupAnchor: [0, -36],
      labelAnchor: [-5, -86]
    });
    L.marker(
      [coordinates.lat, coordinates.lng],
      {icon: icon}
    ).addTo(map.map);
    map.map.setZoom(15);
  }

  var handleSingleEvent = function handleSingleEvent(data) {
    var events = data.events;
    $.each(events, function handleEvent(field, value) {
      if (value) {
        if (field === 'location') {
          initEventMap(value);
        }
        if (field === 'title') {
          document.title = value + ' | ' + document.title;
        }
        if (field === 'xcal') {
          $.each(value, function initXcal(k, v) {
            if (v) {
              if (k === 'endDate' && events.xcal.startDate === events.xcal.endDate) {
                $('.linked-event-endDate').addClass('hidden');
                return true;
              }
              if ((k === 'startTime' || k === 'endTime') && (events.xcal.endDate && events.xcal.startDate !== events.xcal.endDate)) {
                return true;
              }
              $('.linked-event-' + k).append(v);
              $('.linked-event-' + k).closest('.linked-event-field').removeClass('hidden');
              $('.linked-event-' + k).removeClass('hidden');
              return true;
            }
          });
        }
        if (field === 'providerLink') {
          $('.linked-event-providerLink').attr('href', value);
          $('.linked-event-' + field).closest('.linked-event-field').removeClass('hidden');
          return true;
        }
        if (field === 'image') {
          $('.linked-event-image').attr('src', value.url);
        } if (field === 'keywords') {
          $.each(value, function initKeywords(key, val) {
            var html = '<span class="linked-event-keyword">#' + val + '</span>';
            $('.linked-event-keywords').append(html);
          });
        } else {
          $('.linked-event-' + field).append(value);
          $('.linked-event-' + field).closest('.linked-event-field').removeClass('hidden');
        }
      }
    });
    if (data.relatedEvents) {
      $('.related-events').append(data.relatedEvents).removeClass('hidden');
      $('.related-events .grid-item').css('flex-basis', '100%');
    }
  };

  var handleMultipleEvents = function handleMultipleEvents(data, append, container) {
    var content = container.find($('.linked-events-content'));
    if (append) {
      content.append(data.html);
    } else {
      content.html(data.html);
    }
    if (data.next !== '') {
      container.find($('.linked-events-next')).removeClass('hidden');
      container.find($('.linked-events-next')).off('click').click(function onNextClick() {
        var params = {};
        params.url = data.next;
        getEvents(params, handleMultipleEvents, true, container);
      });
    } else {
      container.find($('.linked-events-next')).addClass('hidden');
    }
  };

  function getEventContent(id) {
    var params = {};
    params.query = {'id': id};
    var container = $('.linked-event-content');
    getEvents(params, handleSingleEvent, false, container, false);
  }

  function keyHandler(e/*, cb*/) {
    if (e.which === 13 || e.which === 32) {
      $(e.target).click();
      e.preventDefault();
      return false;
    }
    return true;
  }

  function toggleAccordion(container, accordion) {
    var tabContent = container.find('.linked-events-content').detach();
    var searchTools = container.find('.events-searchtools-container').detach();
    var moreButtons = container.find('.linked-events-buttons').detach();
    var toggleSearch = container.find('.events-searchtools-toggle').detach();
    var loadContent = false;
    var accordions = container.find('.event-accordions');
    if (!accordion.hasClass('active') || accordion.hasClass('initial-active')) {
      accordions.find('.accordion')
        .removeClass('active')
        .attr('aria-selected', false);

      container.find('.event-tab.active')
        .removeClass('active')
        .attr('aria-selected', false);

      accordion
        .addClass('active')
        .attr('aria-selected', true);

      container.find('.event-tab[id="' + accordion.data('id') + '"]')
        .addClass('active')
        .attr('aria-selected', true);

      loadContent = true;
    }
    moreButtons.insertAfter(accordion);
    tabContent.insertAfter(accordion);
    searchTools.insertAfter(accordion);
    toggleSearch.insertAfter(accordion);
    accordion.removeClass('initial-active');

    return loadContent;
  }

  function initAccordions(container) {
    container.find($('.event-accordions .accordion')).click(function accordionClicked(/*e*/) {
      var accordion = $(this);
      var tabParams = {};
      tabParams.query = accordion.data('params');
      var tabs = accordion.closest('.event-tabs');
      tabs.find('.event-tab').removeClass('active');
      if (toggleAccordion(container, accordion)) {
        getEvents(tabParams, handleMultipleEvents, false, container);
      }
      return false;
    }).keyup(function onKeyUp(e) {
      return keyHandler(e);
    });
    container.find('.accordion.active').click();
  }

  function initEventsTabs(id) {
    var container = $('.linked-events-tabs-container[id="' + id + '"]');
    var initial = container.find($('li.nav-item.event-tab.active'));
    var initialParams = {};
    initialParams.query = initial.data('params');
    getEvents(initialParams, handleMultipleEvents, false, container);
    container.find($('li.nav-item.event-tab')).click(function eventTabClick() {
      if ($(this).hasClass('active')) {
        return false;
      }
      var params = {};
      params.query = $(this).data('params');
      container.find($('li.nav-item.event-tab')).removeClass('active').attr('aria-selected', 'false');
      $(this).addClass('active').attr('aria-selected', 'true');
      var accordion = container.find('.accordion[data-id="' + $(this).attr('id') + '"');
      container.find('.accordion').removeClass('active');
      accordion.addClass('active');
      toggleAccordion(container, accordion);
      getEvents(params, handleMultipleEvents, false, container);
    }).keyup(function onKeyUp(e) {
      return keyHandler(e);
    });

    var toggleSearchTools = container.find($('.events-searchtools-toggle'));
    if (toggleSearchTools[0]) {
      toggleSearchTools.click(function onToggleSeachTools() {
        if (container.find($('.events-searchtools-toggle')).hasClass('open')) {
          container.find($('.events-searchtools-container')).removeClass('open');
          container.find($('.events-searchtools-toggle')).removeClass('open');
        } else {
          container.find($('.events-searchtools-container')).addClass('open');
          container.find($('.events-searchtools-toggle')).addClass('open');      
        }
      });
    }
    var datepickerLang = container.data('lang');
    $('.event-datepicker').datepicker({
      'language': datepickerLang,
      'format': 'dd.mm.yyyy',
      'weekStart': 1,
      'autoclose': true
    });

    if (container.find($('.events-searchtools-container'))[0]) {
      var searchClick = function onSearchClick() {
        var activeParams = container.find($('.event-tab.active')).data('params');
        var startDate = container.find($('.event-date-start'))[0].value
          ? {'start': container.find($('.event-date-start'))[0].value.replace(/\./g, '-')}
          : '';
        var endDate = container.find($('.event-date-end'))[0].value
          ? {'end': container.find($('.event-date-end'))[0].value.replace(/\./g, '-')}
          : '';
        var textSearch = container.find($('.event-text-search'))[0].value
          ? {'text': container.find($('.event-text-search'))[0].value}
          : '';

        var newParams = {};
        newParams.query = $.extend(newParams.query, activeParams, startDate, endDate, textSearch);
        getEvents(newParams, handleMultipleEvents, false, container);
      };
      container.find($('.linked-event-search')).click(searchClick);
      container.find($('.event-text-search')).keyup(function onKeyUp(e) {
        if (e.which === 13) {
          searchClick();
          e.preventDefault();
          return false;
        }
        return true;
      });
    }
    initAccordions(container);
  }

  var my = {
    initEventsTabs: initEventsTabs,
    getEventContent: getEventContent,
  };

  return my;
})();
