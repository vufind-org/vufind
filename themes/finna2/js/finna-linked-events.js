/*global VuFind, finna, L */
finna.linkedEvents = (function finnaLinkedEvents() {
  /**
   * Get events from providers
   * @param {object} params Object containing params for ajax
   * @param {Function} callback Callback for successful event fetch
   * @param {boolean} append Should data be appended after previous results?
   * @param {jQuery} container Container where the data is handled
   */
  function getEvents(params, callback, append, container) {
    var limit = {'page_size': container.data('limit')};
    params.query = $.extend(params.query, limit);
    var spinner = container[0].querySelector(append ? '.js-loader-more' : '.js-loader');
    if (spinner) {
      spinner.classList.remove("hidden");
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
          VuFind.observerManager.observe(
            'LazyImages',
            container[0].querySelectorAll('img[data-src]')
          );
          finna.layout.initToolTips(container);
        } else {
          var err = $('<div></div>').attr('class', 'linked-events-noresults infobox').attr('aria-live', 'polite').text(VuFind.translate('nohit_heading'));
          container.find($('.linked-events-content')).html(err);
          container.find($('.linked-events-next')).addClass('hidden');
        }
        if (spinner) {
          spinner.classList.add("hidden");
        }
      })
      .fail(function getEventsFail(response/*, textStatus, err*/) {
        var err = '';
        if (typeof response.responseJSON !== 'undefined') {
          err = $('<div></div>').attr('class', 'alert alert-danger').text(response.responseJSON.data);
        }
        $('.linked-events-content').html(err);
        if (spinner) {
          spinner.classList.add("hidden");
        }
      });
  }

  /**
   * Initialize the linked events map
   * @param {object} coordinates Contains latitude and longitude
   */
  function initEventMap(coordinates) {
    var mapCanvas = $('.linked-events-map');
    var map = finna.map.initMap(mapCanvas, false, {center: coordinates, zoom: 15});
    var icon = L.divIcon({
      className: 'mapMarker',
      iconSize: null,
      html: '<div class="leaflet-marker-icon leaflet-zoom-animated leaflet-interactive">' + VuFind.icon('map-marker', 'map-marker-icon open') + '</div>',
      iconAnchor: [10, 35],
      popupAnchor: [0, -36],
      labelAnchor: [-5, -86]
    });
    L.marker(
      [coordinates.lat, coordinates.lng],
      {icon: icon}
    ).addTo(map.map);
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
        if (field === 'phone') {
          $('.linked-event-phone').attr('href', 'tel:' + value);
        }
        if (field === 'email') {
          $('.linked-event-email').attr('href', 'mailto:' + value);
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
      content.append(VuFind.updateCspNonce(data.html));
    } else {
      content.html(VuFind.updateCspNonce(data.html));
    }
    if (data.next !== '') {
      container.find($('.linked-events-next')).removeClass('hidden');
      container.find($('.linked-events-next')).off('click').on('click', function onNextClick() {
        var params = {};
        params.url = data.next;
        getEvents(params, handleMultipleEvents, true, container);
      });
    } else {
      container.find($('.linked-events-next')).addClass('hidden');
    }
  };

  /**
   * Get event content
   * @param {string} id Event id
   */
  function getEventContent(id) {
    var params = {};
    params.query = {'id': id};
    var container = $('.linked-event-content');
    getEvents(params, handleSingleEvent, false, container, false);
  }

  /**
   * Event handler for key press
   * @param {object} e Event object
   * @returns {boolean} Allow event
   */
  function keyHandler(e/*, cb*/) {
    if (e.which === 13 || e.which === 32) {
      $(e.target).trigger("click");
      e.preventDefault();
      return false;
    }
    return true;
  }

  /**
   * Toggle linked events accordion
   * @param {jQuery} container Container containing the linked events elements
   * @param {jQuery} accordion Accordion in the linked events elements
   * @returns {boolean} Should content be loaded
   */
  function toggleAccordion(container, accordion) {
    var tabContent = container.find('.tab-content').detach();
    var searchTools = container.find('.events-searchtools-container').detach();
    var moreButtons = container.find('.linked-events-buttons').detach();
    var toggleSearch = container.find('.events-searchtools-toggle').detach();
    var loadContent = false;
    var accordions = container.find('.event-accordions');
    if (!accordion.hasClass('active') || accordion.hasClass('initial-active')) {
      accordions.find('.accordion')
        .removeClass('active')
        .attr('aria-selected', false);

      container.find('.event-tab .nav-link.active')
        .removeClass('active')
        .attr('aria-selected', false);

      accordion
        .addClass('active')
        .attr('aria-selected', true);

      container.find('.event-tab > .nav-link[id="' + accordion.data('id') + '"]')
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

  /**
   * Initialize accordions in linked events
   * @param {jQuery} container Container to find accordion from
   */
  function initAccordions(container) {
    container.find($('.event-accordions .accordion')).on('click', function accordionClicked(/*e*/) {
      var accordion = $(this);
      var tabParams = {};
      tabParams.query = accordion.data('params');
      var tabs = accordion.closest('.event-tabs');
      tabs.find('.event-tab .nav-link').removeClass('active');
      if (toggleAccordion(container, accordion)) {
        getEvents(tabParams, handleMultipleEvents, false, container);
      }
      return false;
    }).keyup(function onKeyUp(e) {
      return keyHandler(e);
    });
    // Setup accordion but avoid reloading the content (initEventTabs loads the initial content):
    let $activeEl = container.find('.accordion.active');
    if ($activeEl.length) {
      toggleAccordion(container, $activeEl);
    }

  }

  /**
   * Initialize event tabs
   * @param {string} id Id of the event tabs
   */
  function initEventsTabs(id) {
    var container = $('.linked-events-tabs[id="' + id + '"]');
    var initial = container.find($('li.nav-item.event-tab .nav-link.active'));
    var initialParams = {};
    initialParams.query = initial.data('params');
    getEvents(initialParams, handleMultipleEvents, false, container);
    container.find($('li.nav-item.event-tab .nav-link')).on('click', function eventTabClick() {
      if ($(this).hasClass('active')) {
        return false;
      }
      var params = {};
      params.query = $(this).data('params');
      container.find($('li.nav-item.event-tab .nav-link')).removeClass('active').attr('aria-selected', 'false');
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
      toggleSearchTools.on('click', function onToggleSeachTools() {
        if (container.find($('.events-searchtools-toggle')).hasClass('open')) {
          container.find($('.events-searchtools-container')).removeClass('open');
          container.find($('.events-searchtools-toggle')).removeClass('open');
        } else {
          container.find($('.events-searchtools-container')).addClass('open');
          container.find($('.events-searchtools-toggle')).addClass('open');
        }
      });
    }

    if (container.find($('.events-searchtools-container'))[0]) {
      var searchClick = function onSearchClick() {
        var activeParams = container.find($('.event-tab .nav-link.active')).data('params');
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
