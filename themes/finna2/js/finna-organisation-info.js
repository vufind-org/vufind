/*global VuFind, finna, Donut, finnaSelectA11y */
finna.organisationInfo = (function finnaOrganisationInfo() {
  let params = null;
  let container = null;
  let map = null;

  let mapTileUrl = 'https://map-api.finna.fi/v1/rendered/{z}/{x}/{y}.png?v=2';

  /**
   * Reset search field
   */
  function resetSearch() {
    $(container).find('.js-location-select').val('').trigger('change');
  }

  /**
   * Update URL hash ensuring that the change event is triggered
   * @param {string} hash New hash
   */
  function updateURLHash(hash) {
    if (('#' + hash) === decodeURIComponent(window.location.hash)) {
      // Set hash first to empty value, so that the change event is triggered when
      // the same menu item is re-selected.
      window.location.hash = '';
    }
    window.location.hash = '#' + hash;
  }

  /**
   * Get location from URL hash
   * @returns {string} Current hash
   */
  function getLocationFromURLHash() {
    if (window.location.hash !== '') {
      return window.location.hash.replace('#', '');
    }
    return '';
  }

  /**
   * Get current location from local storage
   * @param {string} id Organisation ID
   * @returns {string} Location id
   */
  function getStoredLocation(id) {
    return localStorage.getItem('location-info-' + id) || '';
  }

  /**
   * Remember current location in local storage
   * @param {string} id Organisation ID
   * @param {string} locationId Location ID
   */
  function storeCurrentLocation(id, locationId) {
    localStorage.setItem('location-info-' + id, decodeURIComponent(locationId));
  }

  // Forward declaration
  let showLocationDetails = function () {};

  /**
   * Update selected location
   * @param {string | null} locationId Location ID
   * @param {boolean} clearSearch Should search be cleared
   */
  function updateSelectedLocation(locationId, clearSearch) {
    showLocationDetails(locationId);
    if (clearSearch) {
      resetSearch();
    }
  }

  /**
   * Get location from URL hash and display it
   */
  function updateLocationFromURLHash() {
    let location = getLocationFromURLHash();
    if (location) {
      updateSelectedLocation(location, false);
    }
  }

  /**
   * Hide location search dropdown
   */
  function hideLocationSearch() {
    // Hide search:
    const searchContainer = container.querySelector('.js-location-search-container');
    let searchToggle = searchContainer ? searchContainer.querySelector('.js-location-search-toggle') : null;
    if (searchToggle) {
      searchToggle.setAttribute('aria-expanded', 'false');
      searchToggle.focus();
    }
  }

  /**
   * Initialize location search
   */
  function initLocationSearch() {
    const searchContainer = container.querySelector('.js-location-search-container');
    if (!searchContainer) {
      return;
    }
    let searchEl = searchContainer.querySelector('.js-location-search-fields');
    let resultsEl = searchContainer.querySelector('.js-location-search-results');
    if (!searchEl || !resultsEl) {
      console.error('Search fields or results element not found');
      return;
    }
    if (!('geolocation' in navigator)) {
      let geoField = searchEl.querySelector('[name="service_nearest"]');
      if (geoField) {
        geoField.remove();
      }
    }
    let searchIndicatorEl = searchContainer.querySelector('.js-results-loader');
    searchEl.querySelectorAll('select, input').forEach((field) => {
      field.addEventListener('change', () => {
        resultsEl.textContent = '';
        let searchParams = new URLSearchParams({
          method: 'getOrganisationInfo',
          element: 'location-search',
          id: params.id,
          sectors: params.sectors || '',
          buildings: params.buildings || ''
        });
        searchEl.querySelectorAll('select, input[type="checkbox"]').forEach((field2) => {
          if (field2.tagName === 'INPUT' && field2.type === 'checkbox') {
            searchParams.append(field2.name, field2.checked ? '1' : '0');
          } else {
            searchParams.append(field2.name, field2.value);
          }
        });
        if (!searchParams.get('service_type')) {
          return;
        }
        if (searchIndicatorEl) {
          searchIndicatorEl.classList.remove('hidden');
        }
        let doSearch = function (searchParams2) {
          fetch(VuFind.path + '/AJAX/JSON?' + searchParams2)
            .then(response => {
              if (searchIndicatorEl) {
                searchIndicatorEl.classList.add('hidden');
              }
              if (!response.ok) {
                resultsEl.textContent = VuFind.translate('error_occurred');
              } else {
                response.json().then((result) => {
                  resultsEl.innerHTML = result.data.results;
                  resultsEl.querySelectorAll('a').forEach((resultEl) => {
                    resultEl.addEventListener('click', hideLocationSearch);
                  });
                });
              }
            });
        };
        if (searchParams.get('service_nearest') === '1') {
          navigator.geolocation.getCurrentPosition(
            function success(position) {
              searchParams.append('lat', position.coords.latitude);
              searchParams.append('lon', position.coords.longitude);
              doSearch(searchParams);
            },
            function error(err) {
              var errorString = 'geolocation_other_error';
              var additionalInfo = '';
              if (err) {
                switch (err.code) {
                case err.POSITION_UNAVAILABLE:
                  errorString = 'geolocation_position_unavailable';
                  break;
                case err.PERMISSION_DENIED:
                  errorString = 'geolocation_inactive';
                  break;
                case err.TIMEOUT:
                  errorString = 'geolocation_timeout';
                  break;
                default:
                  additionalInfo = err.message;
                  break;
                }
              }
              errorString = VuFind.translate(errorString);
              if (additionalInfo) {
                errorString += ' -- ' + additionalInfo;
              }
              let div = document.createElement('div');
              div.className = 'location-search-result';
              div.textContent = errorString;
              resultsEl.append(div);
              if (searchIndicatorEl) {
                searchIndicatorEl.classList.add('hidden');
              }
            }
          );
        } else {
          doSearch(searchParams);
        }
      });
    });

    const selectServiceLocation = document.querySelector('.js-location-search-service-location');
    if (selectServiceLocation) {
      new finnaSelectA11y(selectServiceLocation, {
        useLabelAsButton: true,
      });
    }

    const selectServiceType = document.querySelector('.js-location-search-service-type');
    if (selectServiceType) {
      new finnaSelectA11y(selectServiceType, {
        useLabelAsButton: true,
      });
    }
    // Trigger DOM event for the jQuery change event:
    $('.js-location-search-service-type').on(
      'change',
      function triggerChange(ev) {
        // Trigger only if this is the original event to avoid endless loop:
        if (typeof ev.originalEvent === 'undefined') {
          this.dispatchEvent(new Event('change'));
        }
      }
    );

    // Add listeners that close the search dropdown as necessary:
    document.addEventListener('mousedown', (e) => {
      const searchLocationToggle = searchContainer.querySelector('.js-location-search-toggle');
      const searchLocationIsActive = searchLocationToggle.getAttribute('aria-expanded');
      if (searchLocationToggle && searchLocationIsActive) {
        if (searchLocationIsActive === "true" && !searchContainer.contains(e.target)) {
          hideLocationSearch();
        }
      }
    });
    searchContainer.addEventListener('keydown', (e) => {
      const searchLocationEl = searchContainer.querySelector('.js-location-search');
      if (e.code === "Escape" && searchLocationEl) {
        hideLocationSearch();
      }
    });
  }

  /**
   * Initialize map
   * @param {object} data Organisation info response for info-location-selection request
   */
  function initMap(data) {
    let mapData = Object.fromEntries(Object.entries(data.locationData).filter((loc) => null !== loc[1].lat && null !== loc[1].lon));
    if (mapData.length === 0) {
      return;
    }

    const mapContainer = container.querySelector('.js-location-info-map');
    if (!mapContainer) {
      return;
    }
    let mapWidget = mapContainer.querySelector('.js-map-widget');
    let mapTooltip = mapContainer.querySelector('.js-marker-tooltip');
    let mapAttributionTemplate = mapContainer.querySelector('.js-map-attribution');
    let mapBubbleTemplate = mapContainer.querySelector('.js-map-bubble-template');
    if (!mapWidget || !mapTooltip || !mapAttributionTemplate || !mapBubbleTemplate) {
      console.error('Map element(s) not found');
      return;
    }

    mapContainer.classList.remove('hidden');

    map = finna.organisationMap;
    map.init(mapWidget, mapTileUrl, mapAttributionTemplate.innerHTML);

    /**
     * Hide map marker from mapTooltip
     */
    function hideMapMarker() {
      mapTooltip.classList.add('hidden');
    }

    $(map).on('marker-click', function onClickMarker(ev, id) {
      window.location.hash = id;
      hideMapMarker();
    });

    $(map).on('marker-mouseout', function onMouseOutMarker(/*ev*/) {
      hideMapMarker();
    });

    $(map).on('marker-mouseover', function onMouseOverMarker(ev, mapItem) {
      if (mapItem.id in mapData) {
        var name = mapData[mapItem.id].name;
        mapTooltip.classList.remove('hidden');
        mapTooltip.textContent = name;
        mapTooltip.style.left = mapWidget.offsetLeft + mapItem.x + 35 + 'px';
        mapTooltip.style.top = mapWidget.offsetTop + mapItem.y + 'px';
      }
    });

    let showLocationEl = mapContainer.querySelector('.js-map-controls .js-show-location');
    if (showLocationEl) {
      showLocationEl.addEventListener('click', (ev) => {
        let id = getLocationFromURLHash();
        if (id && id in mapData) {
          map.reset();
          map.selectMarker(id);
        }
        ev.preventDefault();
      });
    }

    let showAllEl = mapContainer.querySelector('.js-map-controls .js-show-all');
    if (showAllEl) {
      showAllEl.removeAttribute('disabled');
      showAllEl.addEventListener('click', (ev) => {
        map.resize();
        map.reset();
        updateSelectedLocation(null, true);
        ev.preventDefault();
      });
    }

    for (const id in mapData) {
      if (!Object.hasOwn(mapData, id)) {
        continue;
      }
      // Map data (info bubble, icon)
      let locationData = mapData[id];
      let bubble = mapBubbleTemplate.cloneNode(true);
      let nameEl = bubble.content.querySelector('.js-name');
      if (nameEl) {
        nameEl.textContent = locationData.name;
      }
      let addressEl = bubble.content.querySelector('.js-address');
      if (addressEl) {
        addressEl.textContent = '';
        let streetEl = document.createElement('span');
        streetEl.textContent = locationData.address.street;
        addressEl.append(streetEl);
        let cityEl = document.createElement('span');
        cityEl.textContent = locationData.address.zipcode + ' ' + locationData.address.city;
        addressEl.append(document.createElement('br'));
        addressEl.append(cityEl);
      }

      mapData[id].map = {
        info: bubble.innerHTML
      };
    }

    map.draw(mapData);
  }

  /**
   * Initialize location selection
   * @param {object} data Organisation info response for info-location-selection request
   */
  function initLocationSelection(data) {
    // Setup location selection
    const selectLocation = document.querySelector('.js-location-select');
    if (selectLocation) {
      new finnaSelectA11y(selectLocation, {
        useLabelAsButton: true,
      });
      selectLocation.addEventListener('change', event => {
        updateURLHash(encodeURIComponent(event.target.value || 'undefined'));
      });
    }

    initLocationSearch();
    initMap(data);
  }

  /**
   * Initialize opening times week navigation
   * @param {HTMLElement} _container Container
   * @param {object} _params Organisation info params
   * @param {string} locationId Location ID
   */
  function initWeekNavi(_container, _params, locationId) {
    _container.querySelectorAll('.js-week-navi-btn').forEach((btn) => {
      if (!btn.dataset.dir) {
        return;
      }
      btn.addEventListener('click', () => {
        let widgetContainer = btn.closest('.js-schedules');
        if (!widgetContainer) {
          console.error('Schedule widget not found');
          return;
        }
        let indicatorEl = widgetContainer.querySelector('.js-loader');
        let weekNaviEl = btn.closest('.js-week-navi');
        let timesEl = widgetContainer.querySelector('.js-opening-times-week');
        if (!indicatorEl || !weekNaviEl || !timesEl) {
          console.error('Week navi, times or loading indicator not found');
          return;
        }
        let weekTextEl = weekNaviEl.querySelector('.js-week-text');
        if (!weekTextEl) {
          console.error('Week text not found');
          return;
        }
        let weekNumEl = weekTextEl.querySelector('.js-num');
        if (!weekNumEl) {
          console.error('Week num not found');
          return;
        }
        let isoDate = weekNaviEl.dataset.date;
        if (!isoDate) {
          console.error('Current date not found');
          return;
        }
        let date = new Date(isoDate + 'T00:00:00Z');
        let delta = parseInt(btn.dataset.dir) < 0 ? -7 : 7;
        date.setUTCDate(date.getUTCDate() + delta);
        let newIsoDate = date.toISOString().substring(0, 10);
        indicatorEl.classList.remove('hidden');
        fetch(VuFind.path + '/AJAX/JSON?' + new URLSearchParams({
          method: 'getOrganisationInfo',
          element: 'schedule',
          id: _params.id,
          locationId: locationId,
          sectors: _params.sectors || '',
          buildings: _params.buildings || '',
          date: newIsoDate
        }))
          .then(response => {
            indicatorEl.classList.add('hidden');
            if (!response.ok) {
              timesEl.textContent = VuFind.translate('error_occurred');
            } else {
              response.json().then((result) => {
                weekNaviEl.dataset.date = newIsoDate;
                weekNumEl.textContent = result.data.weekNum;
                weekTextEl.setAttribute('aria-live', 'polite');
                let prevBtnEl = weekNaviEl.querySelector('.js-week-navi-btn.prev-week');
                if (prevBtnEl) {
                  prevBtnEl.disabled = result.data.currentWeek;
                }
                timesEl.outerHTML = result.data.widget;
              });
            }
          });
      });
    });
  }

  /**
   * Initialize coverage display
   */
  function initCoverageGauge() {
    let gaugeEl = container.querySelector('.js-finna-coverage-gauge');
    if (gaugeEl) {
      let opts = {
        lines: 0,
        angle: 0.1,
        lineWidth: 0.09,
        limitMax: 'true',
        colorStart: '#00A2B5',
        colorStop: '#00A2B5',
        strokeColor: '#e5e5e5',
        generateGradient: true
      };

      var gauge = new Donut(gaugeEl).setOptions(opts);
      gauge.maxValue = 100;
      gauge.animationSpeed = 20;
      gauge.set(gaugeEl.dataset.coverage);
    }
  }

  /**
   * Initialize location details container
   * @param {string} locationId Location ID
   */
  function initLocationDetails(locationId) {
    const detailsEl = container.querySelector('.js-location-details-container');
    if (!detailsEl) {
      console.error('Location details element not found');
      return;
    }
    detailsEl.querySelectorAll('[data-truncate]').forEach((elem) => {
      VuFind.truncate.initTruncate(elem);
    });
    finna.layout.initToolTips($(detailsEl));
    initWeekNavi(container, params, locationId);
    if (map) {
      map.selectMarker(locationId);
    }
    finna.common.trackContentImpressions(detailsEl);
  }

  /**
   * Show location details
   * @param {string} locationId Location ID
   */
  showLocationDetails = function showLocationDetailsImpl(locationId) {
    const detailsEl = container.querySelector('.js-location-details-container');
    if (!detailsEl) {
      console.error('Location details element not found');
      return;
    }
    const indicatorEl = container.querySelector('.js-location-loader');
    if (!indicatorEl) {
      console.error('Location load indicator element not found');
      return;
    }
    if (!indicatorEl.classList.contains('hidden')) {
      // Already loading
      return;
    }

    const notificationEl = container.querySelector('.js-location-unavailable');
    if (notificationEl) {
      notificationEl.classList.add('hidden');
    }
    detailsEl.innerHTML = '';
    const infoEl = container.querySelector('.js-location-quick-information');
    if (infoEl) {
      infoEl.innerHTML = '&nbsp;';
      infoEl.classList.toggle('hidden', null === locationId);
    }

    const mapContainer = container.querySelector('.js-location-info-map');
    if (mapContainer) {
      let showLocationEl = mapContainer.querySelector('.js-map-controls .js-show-location');
      if (showLocationEl) {
        showLocationEl.toggleAttribute('disabled', null === locationId);
      }
    }
    if (map) {
      map.resize();
    }
    if (null === locationId) {
      return;
    }

    storeCurrentLocation(params.id, locationId);

    indicatorEl.classList.remove('hidden');
    fetch(VuFind.path + '/AJAX/JSON?' + new URLSearchParams({
      method: 'getOrganisationInfo',
      element: 'location-details',
      id: params.id,
      locationId: locationId,
      sectors: params.sectors || '',
      buildings: params.buildings || ''
    }))
      .then(response => {
        indicatorEl.classList.add('hidden');
        if (!response.ok) {
          detailsEl.textContent = VuFind.translate('error_occurred');
        } else {
          response.json().then((result) => {
            if (!result.data.found) {
              if (notificationEl) {
                notificationEl.classList.remove('hidden');
              }
              resetSearch();
              if (map) {
                map.reset();
              }
              return;
            }
            detailsEl.innerHTML = result.data.details;
            if (infoEl) {
              infoEl.innerHTML = result.data.info;
            }
            initLocationDetails(locationId);
          });
        }
      });
  };

  /**
   * Initialize organisation info page
   * @param {object} _params Organisation info page params
   */
  function init(_params) {
    params = _params;

    container = document.querySelector('.js-organisation-info-container');
    if (!container) {
      console.error('Organisation info container element not found');
      return;
    }
    const infoEl = container.querySelector('.js-consortium-info-container');
    if (!infoEl) {
      console.error('Consortium info element not found');
      return;
    }
    const selectionEl = container.querySelector('.js-location-selection-container');
    if (!selectionEl) {
      console.error('Location selection element not found');
      return;
    }
    const loadIndicatorEl = container.querySelector('.js-location-selection-loader');
    if (!loadIndicatorEl) {
      console.error('Location load indicator element not found');
      return;
    }

    addEventListener('hashchange', () => { updateLocationFromURLHash(); });

    loadIndicatorEl.classList.remove('hidden');
    fetch(VuFind.path + '/AJAX/JSON?' + new URLSearchParams({
      method: 'getOrganisationInfo',
      element: 'info-location-selection',
      id: params.id,
      locationId: getLocationFromURLHash() || getStoredLocation(params.id),
      sectors: params.sectors || '',
      buildings: params.buildings || '',
      consortiumInfo: params.consortiumInfo
    }))
      .then((response) => {
        loadIndicatorEl.classList.add('hidden');
        if (!response.ok) {
          selectionEl.textContent = VuFind.translate('error_occurred');
        } else {
          response.json().then((result) => {
            infoEl.innerHTML = result.data.consortiumInfo;
            selectionEl.innerHTML = result.data.locationSelection;
            initLocationSelection(result.data);
            initCoverageGauge();
            if (result.data.defaultLocationId) {
              updateURLHash(result.data.defaultLocationId);
            }
          });
        }
      });
  }

  /**
   * Load location into the widget
   * @param {HTMLElement} _container Widget container
   * @param {object} _params Widget parameters
   * @param {string} locationId Location id
   */
  function loadWidgetLocation(_container, _params, locationId) {
    let openStatusEl = _container.querySelector('.js-open-status');
    let scheduleEl = _container.querySelector('.js-opening-times');
    let selectedLocationEl = _container.querySelector('.js-location-dropdown .js-selected');
    if (!openStatusEl || !scheduleEl || !selectedLocationEl) {
      console.error('Organisation info widget open status, schedule or selected location element not found');
      return;
    }
    const loadIndicatorEl = _container.querySelector('.js-loader');
    if (!loadIndicatorEl) {
      console.error('Organisation info widget load indicator element not found');
      return;
    }

    storeCurrentLocation(_params.id, locationId);

    loadIndicatorEl.classList.remove('hidden');
    fetch(VuFind.path + '/AJAX/JSON?' + new URLSearchParams({
      method: 'getOrganisationInfo',
      element: 'widget-location',
      id: _params.id,
      locationId: locationId,
      buildings: _params.buildings || '',
      details: _params.details || '1'
    }))
      .then((response) => {
        loadIndicatorEl.classList.add('hidden');
        if (!response.ok) {
          scheduleEl.textContent = VuFind.translate('error_occurred');
        } else {
          response.json().then((result) => {
            selectedLocationEl.textContent = result.data.locationName;
            let ariaEl = _container.querySelector('.js-location-dropdown .js-aria');
            if (ariaEl) {
              ariaEl.setAttribute('aria-live', 'polite');
            }
            openStatusEl.innerHTML = result.data.openStatus;
            scheduleEl.innerHTML = result.data.schedule;
            const detailsEl = _container.querySelector('.js-details');
            if (result.data.details) {
              if (detailsEl) {
                detailsEl.innerHTML = result.data.details;
                finna.layout.initToolTips($(detailsEl));
              }
            }
            initWeekNavi(_container, _params, result.data.locationId);
            finna.common.trackContentImpressions(_container);
          });
        }
      });
  }

  /**
   * Initialize organisation info widget
   * @param {object} _params Widget parameters
   */
  function initWidget(_params) {
    const _container = document.querySelector(_params.container);
    if (!_container) {
      console.error('Organisation info widget element (' + _params.container + ') not found');
      return;
    }

    let contentEl = _container.querySelector('.js-content');
    if (!contentEl) {
      console.error('Organisation info widget content element not found');
      return;
    }
    const loadIndicatorEl = _container.querySelector('.js-loader');
    if (!loadIndicatorEl) {
      console.error('Organisation info widget load indicator element not found');
      return;
    }

    loadIndicatorEl.classList.remove('hidden');
    fetch(VuFind.path + '/AJAX/JSON?' + new URLSearchParams({
      method: 'getOrganisationInfo',
      element: 'widget',
      id: _params.id,
      locationId: getStoredLocation(_params.id),
      buildings: _params.buildings || '',
      details: _params.details || '1'
    }))
      .then((response) => {
        loadIndicatorEl.classList.add('hidden');
        if (!response.ok) {
          contentEl.textContent = VuFind.translate('error_occurred');
        } else {
          response.json().then((result) => {
            contentEl.innerHTML = result.data.widget;
            initWeekNavi(_container, _params, result.data.locationId);
            finna.layout.initToolTips($(contentEl));
            contentEl.querySelectorAll('.js-location-dropdown ul.dropdown-menu li').forEach((el) => {
              el.addEventListener('click', () => {
                loadWidgetLocation(_container, _params, el.dataset.id);
              });
            });
            contentEl.querySelectorAll('.js-location-dropdown li').forEach((el) => {
              el.addEventListener('keydown', (ev) => {
                if (ev.key === 'Enter') {
                  el.click();
                }
              });
            });
          });
        }
      });
  }

  return {
    init: init,
    initWidget: initWidget
  };
})();
