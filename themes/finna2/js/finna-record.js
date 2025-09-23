/*global VuFind, finna, removeHashFromLocation, getNewRecordTab, ajaxLoadTab */
finna.record = (function finnaRecord() {
  var accordionTitleHeight = 64;

  /**
   * Initialize description for record
   */
  function initDescription() {
    var description = $('#description_text');
    if (description.length) {
      let params = new URLSearchParams({
        id: description.data('id'),
        source: description.data('source')
      });
      var url = VuFind.path + '/AJAX/JSON?method=getDescription&' + params;
      $.getJSON(url)
        .done(function onGetDescriptionDone(response) {
          if (response.data.html.length > 0) {
            description.html(VuFind.updateCspNonce(response.data.html));

            // Make sure any links open in a new window
            description.find('a').attr('target', '_blank');

            description.wrapInner('<div class="truncate-field wide"><p class="summary"></p></div>');
            finna.layout.initTruncate(description);
            if (!$('.hide-details-button').hasClass('hidden')) {
              $('.record-information .description').addClass('too-long');
              $('.record-information .description .more-link.wide').trigger("click");
            }
          } else {
            description.hide();
          }
        })
        .fail(function onGetDescriptionFail() {
          description.hide();
        });
    }
    const more = $('.show-hide-button').html();
    const less = $('.hide-info').html();
    $('.cc-info').on('show.bs.collapse', function changeText() {
      $(this).parents('.fulltextField').find('.show-hide-button').html(less);
      $(this).parents('ul').siblings('button.more-link').trigger("click");
    }).on('hidden.bs.collapse', function changeText() {
      $(this).parents('.fulltextField').find('.show-hide-button').html(more);
    });
    $('.hide-info').on('click', function handleClick() {
      $(this).trigger("blur");
      $(this).parents('.fulltextField').find('.show-hide-button').trigger("focus");
    });
  }
  /**
   * Show details handler for record
   */
  function showDetails() {
    $('.record-information .record-details-more').removeClass('hidden');
    $('.show-details-button').addClass('hidden');
    $('.hide-details-button').removeClass('hidden');
    $('.record .description .more-link.wide').trigger("click");
    sessionStorage.setItem('finna_record_details', '1');
  }
  /**
   * Hide details handler for record
   */
  function hideDetails() {
    $('.record-information .record-details-more').addClass('hidden');
    $('.hide-details-button').addClass('hidden');
    $('.show-details-button').removeClass('hidden');
    $('.record .description .less-link.wide').trigger("click");
    sessionStorage.removeItem('finna_record_details');
  }
  /**
   * Initialize details handlers for record buttons
   */
  function initHideDetails() {
    $('.show-details-button').on('click', function onClickShowDetailsButton() {
      showDetails();
      $(this).trigger("blur");
      $(this).siblings('table.table').trigger("focus");
    });
    $('.hide-details-button').on ("click", function onClickHideDetailsButton() {
      hideDetails();
      $(this).trigger("blur");
      $(this).siblings('.show-details-button').trigger("focus");
    });
    if ($('.record-information').height() > 350 && $('.show-details-button')[0]) {
      $('.record-information .description').addClass('too-long');
      if (sessionStorage.getItem('finna_record_details')) {
        showDetails();
      } else {
        hideDetails();
      }
    }
  }

  /**
   * Get requested link data
   * @param {HTMLAnchorElement} element Anchor element to parse params from
   * @param {string} recordId Record id to add into returned data
   * @returns {object} Object containing anchor elements query data as key value pairs
   */
  function getRequestLinkData(element, recordId) {
    var vars = {}, hash;
    var hashes = element.href.slice(element.href.indexOf('?') + 1).split('&');

    for (var i = 0; i < hashes.length; i++) {
      hash = hashes[i].split('=');
      var x = hash[0];
      var y = hash[1];
      vars[x] = y;
    }
    vars.id = recordId;
    return vars;
  }

  /**
   * Check if record request is valid
   * @param {Array} elements Array containing anchor links
   * @param {string} requestType Type of the request
   */
  function checkRequestsAreValid(elements, requestType) {
    if (!elements[0]) {
      return;
    }
    var recordId = elements[0].href.match(/\/Record\/([^/]+)\//)[1];

    var vars = [];
    $.each(elements, function handleElement(idx, element) {
      vars.push(getRequestLinkData(element, recordId));
    });

    var url = VuFind.path + '/AJAX/JSON?method=checkRequestsAreValid';
    $.ajax({
      dataType: 'json',
      data: {id: recordId, requestType: requestType, data: vars},
      method: 'POST',
      cache: false,
      url: url
    })
      .done(function onCheckRequestDone(responses) {
        $.each(responses.data, function handleResponse(idx, response) {
          var element = elements[idx];
          if (response.status) {
            $(element).removeClass('disabled')
              .removeClass('request-check')
              .html(VuFind.updateCspNonce(response.msg));
          } else {
            $(element).remove();
          }
        });
      });
  }

  /**
   * Fetch holdings details for record
   * @param {Array} elements Array containing holdings containers
   */
  function fetchHoldingsDetails(elements) {
    if (!elements[0]) {
      return;
    }

    $.each(elements, function handleElement(idx, element) {
      $(element).removeClass('hidden');
      var url = VuFind.path + '/AJAX/JSON?method=getHoldingsDetails';
      $.ajax({
        dataType: 'json',
        data: $(element).data(),
        method: 'POST',
        cache: false,
        url: url
      })
        .done(function onGetDetailsDone(response) {
          $(element).addClass('hidden');
          var $group = $(element).parents('.holdings-group');
          $group.find('.load-more-indicator-ajax').addClass('hidden');
          // This can be called several times to load more items. Only update the hidden element.
          $group.find('.holdings-details-ajax.hidden').html(VuFind.updateCspNonce(response.data.details)).removeClass('hidden');
          var $itemsContainer = $group.find('.holdings-items-ajax.hidden');
          $itemsContainer.html(VuFind.updateCspNonce(response.data.items)).removeClass('hidden');
          checkRequestsAreValid($group.find('.expandedCheckRequest').removeClass('expandedCheckRequest'), 'Hold');
          checkRequestsAreValid($group.find('.expandedCheckStorageRetrievalRequest').removeClass('expandedCheckStorageRetrievalRequest'), 'StorageRetrievalRequest');
          checkRequestsAreValid($group.find('.expandedCheckILLRequest').removeClass('expandedCheckILLRequest'), 'ILLRequest');
          VuFind.lightbox.bind($itemsContainer);
          $group.find('.load-more-items-ajax').on('click', function loadMore() {
            var $elem = $(this);
            $elem.addClass('hidden');
            $elem.siblings('.load-more-indicator-ajax').removeClass('hidden');
            fetchHoldingsDetails($elem.parent());
          });
        })
        .fail(function onGetDetailsFail() {
          $(element).text(VuFind.translate('error_occurred'));
        });
    });
  }

  /**
   * Fetch wayfinder markers
   * @param {Array} markers Array containing containers to fetch markers into
   */
  function fetchWayfinderMarkers(markers) {
    const locationMap = {};
    markers.forEach((element) => {
      if (element.dataset.initialized) {
        return;
      }
      element.dataset.initialized = true;
      const location = element.dataset.location;
      if (!(location in locationMap)) {
        locationMap[location] = [element];
      } else {
        locationMap[location].push(element);
      }
      const spinner = document.createElement('span');
      spinner.innerHTML = VuFind.icon('spinner');
      element.append(spinner);
    });
    if (Object.entries(locationMap).length === 0) {
      return;
    }

    fetch(VuFind.path + '/AJAX/JSON?method=wayfinderPlacementLinkLookup', { method: 'POST', body: JSON.stringify(Object.keys(locationMap)) })
      .then(response => response.json())
      .then(responseJSON => {
        Object.entries(locationMap).forEach(([location, elements]) => {
          if (typeof responseJSON.data.locations[location] === 'undefined') {
            Object.entries(elements).forEach(([, element]) => {
              element.remove();
            });
          } else {
            Object.entries(elements).forEach(([, element]) => {
              const linkTemplate = element.querySelector('.js-wayfinder-link');
              if (!linkTemplate) {
                element.remove();
                return;
              }
              let linkContainer = linkTemplate.cloneNode(true);
              let link = linkContainer.content.querySelector('a');
              if (!link) {
                element.remove();
                return;
              }
              link.setAttribute('href', responseJSON.data.locations[location].markerUrl);
              element.innerHTML = linkContainer.innerHTML;
            });
          }
        });
      });
  }

  /**
   * Initial setup for checking requests on tab load
   */
  function setUpCheckRequest() {
    checkRequestsAreValid($('.expandedCheckRequest').removeClass('expandedCheckRequest'), 'Hold');
    checkRequestsAreValid($('.expandedCheckStorageRetrievalRequest').removeClass('expandedCheckStorageRetrievalRequest'), 'StorageRetrievalRequest');
    checkRequestsAreValid($('.expandedCheckILLRequest').removeClass('expandedCheckILLRequest'), 'ILLRequest');
    fetchHoldingsDetails($('.expandedGetDetails').removeClass('expandedGetDetails'));
    fetchWayfinderMarkers(document.querySelectorAll('.holdings-container-heading > .location-link .js-wayfinder-placeholder, .copy-details:not(.collapsed) .js-wayfinder-placeholder'));
  }

  /**
   * Initialize controls for holdings
   */
  function initHoldingsControls() {
    $('.record-holdings-table:not(.electronic-holdings) .holdings-container-heading').on('keydown', function onClickHeading(e) {
      if (e.keyCode === 13 || e.keyCode === 32) {
        if ($(e.target).hasClass('location-service') || $(e.target).parents().hasClass('location-service')
          || $(e.target).parents().hasClass('location-service-qrcode')
        ) {
          return;
        }
        e.preventDefault();
        $('.record-holdings-table:not(.electronic-holdings) .holdings-container-heading').trigger("click");
      }
    });
    $('.record-holdings-table:not(.electronic-holdings) .holdings-container-heading').on('click', function onClickHeading(e) {
      $(this).attr('aria-expanded', function changeAria(i, attr) { return attr === 'false' ? 'true' : 'false'; });
      if ($(e.target).hasClass('location-service') || $(e.target).parents().hasClass('location-service')
        || $(e.target).parents().hasClass('location-service-qrcode')
      ) {
        return;
      }
      $(this).toggleClass('open');
      $(this).nextUntil('.holdings-container-heading').toggleClass('collapsed');
      if ($(this).hasClass('open')) {
        var rows = $(this).nextUntil('.holdings-container-heading');
        checkRequestsAreValid(rows.find('.collapsedCheckRequest').removeClass('collapsedCheckRequest'), 'Hold', 'holdBlocked');
        checkRequestsAreValid(rows.find('.collapsedCheckStorageRetrievalRequest').removeClass('collapsedCheckStorageRetrievalRequest'), 'StorageRetrievalRequest', 'StorageRetrievalRequestBlocked');
        checkRequestsAreValid(rows.find('.collapsedCheckILLRequest').removeClass('collapsedCheckILLRequest'), 'ILLRequest', 'ILLRequestBlocked');
        fetchHoldingsDetails(rows.filter('.collapsedGetDetails').removeClass('collapsedGetDetails'));
        fetchWayfinderMarkers(document.querySelectorAll('.copy-details:not(.collapsed) .js-wayfinder-placeholder'));
      }
    });
  }

  /**
   * Augment online links from holdings into record urls
   */
  function augmentOnlineLinksFromHoldings() {
    $('.electronic-holdings a').each(function handleLink() {
      var $a = $(this);
      var href = $a.attr('href');
      var $recordUrls = $('.recordURLs');
      var $existing = $recordUrls.find('a[href="' + href + '"]');
      var desc = $a.text();
      if ($existing.length === 0 || $existing.text() !== desc) {
        // No existing link, prepend to the list
        var newLink = $('.recordURLs .url-template').html();
        newLink = newLink
          .replace('HREF', href)
          .replace('DESC', desc)
          .replace('SOURCE', $('.record-holdings-table:not(.electronic-holdings) .holdings-title').text());

        var $newLink = $(newLink)
          .removeClass('url-template')
          .removeClass('hidden');

        if ($existing.length === 0) {
          $newLink.prependTo($recordUrls);
        } else {
          $existing.replaceWith($newLink);
        }
      }
    });

  }

  /**
   * Set up holdings tab
   */
  function setupHoldingsTab() {
    initHoldingsControls();
    setUpCheckRequest();
    augmentOnlineLinksFromHoldings();
    finna.layout.initLocationService();
    finna.layout.initJumpMenus($('.holdings-tab'));
    VuFind.lightbox.bind($('.holdings-tab'));
    finna.common.initQrCodeLink($('.holdings-tab'));
  }

  /**
   * Set up locations tab for ead3
   */
  function setupLocationsEad3Tab() {
    $('.holdings-container-heading').on('click', function onClickHeading() {
      $(this).nextUntil('.holdings-container-heading').toggleClass('collapsed');
      if ($('.location .fa', this).hasClass('fa-arrow-down')) {
        $('.location .fa', this).removeClass('fa-arrow-down');
        $('.location .fa', this).addClass('fa-arrow-right');
      }
      else {
        $('.location .fa', this).removeClass('fa-arrow-right');
        $('.location .fa', this).addClass('fa-arrow-down');
      }
    });
  }

  /**
   * Set up holdings archive tab
   */
  function setupHoldingsArchiveTab() {
    $('.external-data-heading').on('click', function onClickHeading() {
      $(this).toggleClass('collapsed');
    });
  }

  /**
   * Initialize record navigation hash update event listener when window hash changes
   */
  function initRecordNaviHashUpdate() {
    $(window).on('hashchange', function onHashChange() {
      $('.pager a').each(function updateHash(i, a) {
        a.hash = window.location.hash;
      });
    });
    $(window).trigger('hashchange');
  }

  /**
   * Initialize audio accordions
   */
  function initAudioAccordion() {
    $('.audio-accordion .audio-item-wrapper').first().addClass('active');
    $('.audio-accordion .audio-title-wrapper').on('click', function audioAccordionClicker() {
      $('.audio-accordion .audio-item-wrapper.active').removeClass('active');
      $(this).parent().addClass('active');
    });
  }


  /**
   * Toggle an accordion
   * The accordion has a delicate relationship with the tabs. Handle with care!
   * @param {jQuery} accordion Accordion container
   * @param {boolean} _initialLoad Should this accordion be initially loaded
   * @returns {boolean} Keep looking for next tab
   */
  function _toggleAccordion(accordion, _initialLoad) {
    var initialLoad = typeof _initialLoad === 'undefined' ? false : _initialLoad;
    var tabid = accordion.find('.accordion-toggle a').data('tab');
    var $recordTabs = $('.record-tabs');
    var $tabContent = $recordTabs.find('.tab-content');
    if (initialLoad || !accordion.hasClass('active')) {
      // Move tab content under the correct accordion toggle
      $tabContent.insertAfter(accordion);
      if (accordion.hasClass('noajax') && !$recordTabs.find('.' + tabid + '-tab').length) {
        return true;
      }
      $('.record-accordions').find('.accordion.active').removeClass('active');
      accordion.addClass('active');
      $recordTabs.find('.tab-pane.active').removeClass('active');
      if (!initialLoad && $('.record-accordions').is(':visible')) {
        $('html, body').animate({scrollTop: accordion.offset().top - accordionTitleHeight}, 150);
      }

      if ($recordTabs.find('.' + tabid + '-tab').length > 0) {
        $recordTabs.find('.' + tabid + '-tab').addClass('active');
        if (accordion.hasClass('initiallyActive')) {
          removeHashFromLocation();
        } else {
          window.location.hash = tabid;
        }
        return false;
      } else {
        var newTab = getNewRecordTab(tabid).addClass('active');
        $recordTabs.find('.tab-content').append(newTab);
        return ajaxLoadTab(newTab, tabid, !$(this).parent().hasClass('initiallyActive'));
      }
    }
    return false;
  }

  /**
   * Initialize a record accordion
   */
  function initRecordAccordion() {
    $('.record-accordions .accordion-toggle').on('click', function accordionClicked(e) {
      return _toggleAccordion($(e.target).closest('.accordion'));
    });
    if ($('.mobile-toolbar').length > 0 && $('.accordion-holdings').length > 0) {
      $('.mobile-toolbar .library-link li').removeClass('hidden');
      $('.mobile-toolbar .library-link li').on('click', function onLinkClick(e) {
        e.stopPropagation();
        $('html, body').animate({scrollTop: $('#tabnav').offset().top - accordionTitleHeight}, 150);
        _toggleAccordion($('.accordion-holdings'));
      });
    }
  }

  /**
   * Apply record accordion hash
   * @param {Function} callback Callback to call for accordion if set
   */
  function applyRecordAccordionHash(callback) {
    var newTab = typeof window.location.hash !== 'undefined'
      ? window.location.hash.toLowerCase() : '';

    // Open tab in url hash
    var $tab = $("a:not(.feed-tab-anchor,.feed-accordion-anchor)[data-tab='" + newTab.substr(1) + "']");
    var accordion = (newTab.length <= 1 || newTab === '#tabnav' || $tab.length === 0)
      ? $('.record-accordions .accordion.initiallyActive')
      : $tab.closest('.accordion');
    if (accordion.length > 0) {
      //onhashchange is an object, so we avoid that later
      if (typeof callback === 'function') {
        callback(accordion);
      } else {
        var mobile = $('.mobile-toolbar');
        var initialLoad = mobile.length > 0 ? !mobile.is(':visible') : true;
        _toggleAccordion(accordion, initialLoad);
      }
    }
  }

  /**
   * Toggle accordion at the start so the accordions work properly
   * @param {jQuery} accordion Accordion to toggle
   * @returns {boolean|void} True if not found or none
   */
  function initialToggle(accordion) {
    var $recordTabs = $('.record-tabs');
    var $tabContent = $recordTabs.find('.tab-content');
    var tabid = accordion.find('.accordion-toggle a').data('tab');
    $tabContent.insertAfter(accordion);
    if (accordion.hasClass('noajax') && !$recordTabs.find('.' + tabid + '-tab').length) {
      return true;
    }

    $('.record-accordions').find('.accordion.active').removeClass('active');
    accordion.addClass('active');
    $recordTabs.find('.tab-pane.active').removeClass('active');
    if ($recordTabs.find('.' + tabid + '-tab').length > 0) {
      $recordTabs.find('.' + tabid + '-tab').addClass('active');
      if (accordion.hasClass('initiallyActive')) {
        removeHashFromLocation();
      }
    }
  }

  /**
   * Load recommended records
   * @param {jQuery} container Container to load records to
   * @param {string} method Method for ajax call
   */
  function loadRecommendedRecords(container, method)
  {
    if (container.length === 0) {
      return;
    }
    var spinner = container.find('.fa-spinner').removeClass('hide');
    var data = {
      method: method,
      id: container.data('id')
    };
    if ('undefined' !== typeof container.data('source')) {
      data.source = container.data('source');
    }
    $.getJSON(VuFind.path + '/AJAX/JSON', data)
      .done(function onGetRecordsDone(response) {
        if (response.data.html.length > 0) {
          container.html(VuFind.updateCspNonce(response.data.html));
        }
        spinner.addClass('hidden');
      })
      .fail(function onGetRecordsFail() {
        spinner.addClass('hidden');
        container.text(VuFind.translate('error_occurred'));
      });
  }

  /**
   * Load similar records support function
   */
  function loadSimilarRecords()
  {
    loadRecommendedRecords($('.sidebar .similar-records'), 'getSimilarRecords');
  }

  /**
   * Load record related records support function
   */
  function loadRecordDriverRelatedRecords()
  {
    loadRecommendedRecords($('.sidebar .record-driver-related-records'), 'getRecordDriverRelatedRecords');
  }

  /**
   * Initialize record versions support function
   * @param {jQuery} _holder Container to init
   */
  function initRecordVersions(_holder) {
    VuFind.recordVersions.init(_holder);
  }

  /**
   * Handle redirect history
   * @param {string} oldId Old id to check
   * @param {string} newId New id to apply
   */
  function handleRedirect(oldId, newId) {
    if (window.history.replaceState) {
      var pathParts = window.location.pathname.split('/');
      pathParts.forEach(function handlePathPart(part, i) {
        if (decodeURIComponent(part) === oldId) {
          pathParts[i] = encodeURIComponent(newId);
        }
      });
      window.history.replaceState(null, document.title, pathParts.join('/') + window.location.search + window.location.hash);
    }
  }

  /**
   * Initialize popovers for record
   */
  function initPopovers() {
    var closeField = function (field, setFocus = false) {
      field.classList.remove('open');
      let link = field.querySelector('a.show-info');
      link.setAttribute('aria-expanded', 'false');
      if (setFocus) {
        link.focus();
      }
    };
    var fixPosition = function (container) {
      // Check container position and move to the left as necessary:
      let infoBounds = container.getBoundingClientRect();
      let maxWidth = window.innerWidth - 36;
      if (infoBounds.width > window.innerWidth - 36) {
        container.style.width = Math.max(200, maxWidth) + 'px';
      }
      infoBounds = container.getBoundingClientRect();
      if (infoBounds.right > window.innerWidth - 8) {
        let marginLeft = window.innerWidth - infoBounds.right - 8;
        container.style.marginLeft = marginLeft + 'px';
      }
    };

    document.addEventListener('mouseup', function onMouseUp(e) {
      document.querySelectorAll('.inline-linked-field.open').forEach((element) => {
        if (!element.contains(e.target)) {
          closeField(element);
        }
      });
    });
    document.addEventListener('keyup', function onKeyUp(e) {
      const keyName = e.code;
      if ( keyName === "Escape") {
        document.querySelectorAll('.inline-linked-field.open').forEach((element) => {
          closeField(element, true);
        });
      }
    });
    document.addEventListener('click', (event) => {
      let field = event.target.closest('.inline-linked-field');
      if (null === field) {
        return;
      }
      let parentLink = event.target.closest('a');
      if (!parentLink) {
        return;
      }
      if (parentLink.classList.contains('hide-info')) {
        closeField(field, true);
        event.preventDefault();
        return;
      }
      if (!parentLink.classList.contains('show-info')) {
        return;
      }
      if (field.classList.contains('open')) {
        closeField(field);
        event.preventDefault();
        return;
      }

      event.preventDefault();
      field.classList.add('open');
      parentLink.setAttribute('aria-expanded', 'true');
      fixPosition(field.querySelector('.field-info'));
      let header = field.querySelector('.field-info h2');
      if (header) {
        header.focus();
      }

      let fieldInfo = field.querySelector('.field-info .dynamic-content');
      if (!fieldInfo || fieldInfo.classList.contains('loaded')) {
        return;
      }
      fieldInfo.classList.add('loaded');
      let params = new URLSearchParams(
        {
          method: 'getFieldInfo',
          ids: field.dataset.ids,
          authIds: field.dataset.authIds,
          type: field.dataset.type,
          source: field.dataset.recordSource,
          recordId: field.dataset.recordId,
          label: field.querySelector('.field-label').textContent
        }
      );
      fetch(VuFind.path + '/AJAX/JSON?' + params)
        .then(data => data.json())
        .then((response) => {
          fieldInfo.textContent = '';
          var desc = typeof response.data.html !== 'undefined' ? response.data.html : null;
          if (desc && desc.trim()) {
            fieldInfo.innerHTML = VuFind.updateCspNonce(desc);
            finna.layout.initTruncate(fieldInfo);
          }
          fixPosition(field.querySelector('.field-info'));
          if (typeof response.data.isAuthority !== 'undefined' && !response.data.isAuthority) {
            // No authority record; hide any links that require it:
            field.querySelectorAll('.authority-page').forEach(el => {
              el.remove();
            });
          }
        }).catch(function handleError() {
          fieldInfo.textContent = VuFind.translate('error_occurred');
        });
    });
  }

  /**
   * Initialize similar carousels
   */
  function initSimilarCarousel()
  {
    var container = document.querySelector('.similar-carousel .splide');
    if (container === null) {
      return;
    }
    var settings = {
      height: 300,
      width: 200,
      omitEnd: true,
      pagination: false,
      gap: '2px',
      focus: 0
    };
    finna.carouselManager.createCarousel(container, settings);
    VuFind.observerManager.observe(
      'LazyImages',
      container.querySelectorAll('img[data-src]')
    );
    container.querySelectorAll('img').forEach(el => {
      el.onload = function onCarouselImageLoad() {
        if (this.naturalWidth === 10 && this.naturalHeight === 10) {
          el.nextElementSibling.classList.remove('hidden');
          el.classList.add('hidden');
        }
      };
    });
  }

  /**
   * Initialize finna record module
   */
  function init() {
    initHideDetails();
    initDescription();
    initRecordNaviHashUpdate();
    initRecordAccordion();
    initAudioAccordion();
    applyRecordAccordionHash(initialToggle);
    $(window).on('hashchange', applyRecordAccordionHash);
    loadSimilarRecords();
    loadRecordDriverRelatedRecords();
    finna.authority.initAuthorityResultInfo();
    initPopovers();
  }

  var my = {
    checkRequestsAreValid: checkRequestsAreValid,
    init: init,
    setupHoldingsTab: setupHoldingsTab,
    setupLocationsEad3Tab: setupLocationsEad3Tab,
    setupHoldingsArchiveTab: setupHoldingsArchiveTab,
    initRecordVersions: initRecordVersions,
    handleRedirect: handleRedirect,
    initSimilarCarousel: initSimilarCarousel
  };

  return my;
})();
