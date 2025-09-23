/*global VuFind, videojs, finna, priorityNav, bootstrap, unwrapJQuery, Popper */
finna.layout = (function finnaLayout() {
  /**
   * Initialize a throttled resize listener
   */
  function initResizeListener() {
    var intervalId = false;
    $(window).on('resize', function onResizeWindow(/*e*/) {
      clearTimeout(intervalId);
      intervalId = setTimeout(function onTimeout() {
        var data = {
          w: $(window).width(),
          h: $(window).height()
        };
        $(window).trigger('throttled-resize.finna', [data]);
      }, 100);
    });
  }

  /**
   * Is touch screen supported
   * @returns {boolean} Touch screen supported
   */
  function isTouchDevice() {
    return (('ontouchstart' in window)
      || (navigator.maxTouchPoints > 0)
      || (navigator.msMaxTouchPoints > 0)); // IE10, IE11, Edge
  }


  /**
   * Append current anchor (location.hash) to selected links
   * in order to preserve the anchor when the link is clicked.
   * This is used in top header language links.
   */
  function initAnchorNavigationLinks() {
    $('a.preserve-anchor').each(function addAnchors() {
      var hash = location.hash;
      if (hash.length === 0) {
        return;
      }
      $(this).attr('href', $(this).attr('href') + hash);
    });
  }

  /**
   * Initialize location service
   * @param {jQuery} _holder Holder to find location-service-modal elements
   */
  function initLocationService(_holder) {
    var holder = typeof _holder === 'undefined' ? $(document) : _holder;

    holder.find('a.location-service.location-service-modal').on('click', function onClickModalLink(/*e*/) {
      const modalEl = document.getElementById('modal');
      if (!modalEl) {
        return;
      }
      const modalDialogEl = modalEl.querySelector('.modal-dialog');
      if (!modalDialogEl) {
        return;
      }

      modalEl.classList.add('location-service');
      modalDialogEl.classList.add('modal-lg');

      modalEl.addEventListener(
        'hidden.bs.modal',
        () => {
          modalEl.classList.remove('location-service');
          modalEl.classList.remove('location-service-qrcode');
          modalDialogEl.classList.remove('modal-lg');
        },
        {once: true}
      );


      VuFind.loadHtml('#modal .modal-body', this.dataset.lightboxHref + '&layout=lightbox');
      bootstrap.Modal.getOrCreateInstance('#modal').show();
      return false;
    });
  }

  /**
   * Initialize truncating of fields
   * @param {jQuery} _holder Holder to find truncate-field elements to truncate
   */
  function initTruncate(_holder) {
    var holder = typeof _holder === 'undefined' ? $(document) : _holder;

    var truncation = [];
    var rowHeight = [];
    $(holder).find('.truncate-field').parent().attr('tabindex', '-1');
    $(holder).find('.truncate-field').not('.truncate-done').each(function handleTruncate(index) {
      var self = $(this);
      self.addClass('truncate-done');

      if (typeof(self.data('row-height')) !== 'undefined') {
        rowHeight[index] = self.data('row-height');
      } else if (self.children().length > 0) {
        // use first child as the height element if available
        var heightElem = self.children().first();
        var outer = self.hasClass('outer');
        if (heightElem.is('div') || outer) {
          rowHeight[index] = parseFloat(outer ? heightElem.outerHeight(true) : heightElem.height());
        } else {
          rowHeight[index] = parseFloat(heightElem.css('line-height').replace('px', ''));
        }
      } else {
        rowHeight[index] = parseFloat(self.css('line-height').replace('px', ''));
      }

      var rowCount = self.data('rows') || 3;
      // get the line-height of first element to determine each text line height
      truncation[index] = rowHeight[index] * rowCount;
      // truncate only if there's more than one line to hide
      if (self.height() > (truncation[index] + rowHeight[index] + 1)) {
        var topLink = self.height() > (rowHeight[index] * 30);
        self.css('height', truncation[index] - 1 + 'px');
        var moreLabel = self.data('label') || VuFind.translate('show_more');
        var lessLabel = self.data('label') || VuFind.translate('show_less');

        var moreLink = $('<button type="button" class="more-link" aria-hidden="true">' + moreLabel + VuFind.icon('show-more') + '</button>');
        var lessLink = $('<button type="button" class="less-link" aria-hidden="true">' + lessLabel + VuFind.icon('show-less') + '</button>');

        if (self.attr('tabindex') === '-1') {
          moreLink.attr('tabindex', '-1');
          lessLink.attr('tabindex', '-1');
        }
        var linkClass = self.data('button-class') || '';
        if (linkClass) {
          moreLink.addClass(linkClass);
          lessLink.addClass(linkClass);
        }
        lessLink.on('click', function showLess() {
          self.siblings('.less-link').hide();
          self.siblings('.more-link').show();
          self.css('height', truncation[index] - 1 + 'px');
          self.blur();
          self.siblings('.more-link').focus();
        });
        moreLink.on('click', function showMore() {
          self.siblings('.more-link').hide();
          self.siblings('.less-link').show();
          self.css('height', 'auto');
          self.blur();
          self.parent().focus();
        });
        lessLink.hide();

        if (self.data('button-placement') === 'top') {
          self.before([moreLink, lessLink]);
        } else if (topLink) {
          self.before(lessLink.addClass('top-button'));
          self.after([moreLink]);
        } else {
          self.after([moreLink, lessLink]);
        }
      }
    });
  }

  /**
   * Initialize content navigation menu
   */
  function initContentNavigation() {
    if ($('.content-navigation-menu')[0]) {
      $('.content-section').each(function initContentSection(index) {
        var link = '#' + $(this).attr('id');
        var $p = $('<p>')
          .addClass('nav-' + index)
          .appendTo($('.content-navigation-menu'));
        $('<a>')
          .attr('href', link)
          .text($('h2', this).first().text())
          .appendTo($p);
      });
    }
  }


  /**
   * Check and keep focus within the search facet list
   * @param {object} e Event object
   */
  function onFocusOutOfFacetContainer(e) {
    const container = document.querySelector('.side-facets-container-ajax');
    if (!container.contains(e.relatedTarget)) {
      e.stopImmediatePropagation();
      e.preventDefault();
      document.activeElement.blur();
      container.focus();
    }
  }

  /**
   * Toggle visibility of sidebar on mobile
   * @param {object} e Event object
   */
  function toggleMobileSidebar(e) {
    e.stopImmediatePropagation();
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
      sidebar.classList.toggle('open');
      const container = document.querySelector('.side-facets-container-ajax');
      document.querySelectorAll('.mobile-navigation .sidebar-navigation .expand-icon, .mobile-navigation .sidebar-navigation .collapse-icon').forEach(el => {
        el.classList.toggle('hidden');
      });
      document.querySelector('body').classList.toggle('prevent-scroll');
      if (container) {
        if (sidebar.classList.contains('open')) {
          container.addEventListener('focusout', onFocusOutOfFacetContainer, e);
          container.ariaModal = true;
          container.tabIndex = '0';
          container.querySelector('h1').tabIndex = '0';
          document.activeElement.blur();
          container.querySelector('h1').focus();
        } else {
          container.removeEventListener('focusout', onFocusOutOfFacetContainer, e);
          document.activeElement.blur();
          document.querySelector('.finna-search-filter-toggle .btn-search-filter').focus();
          container.removeAttribute('aria-modal');
          container.removeAttribute('tabindex');
          container.querySelector('h1').removeAttribute('tabindex');
        }
      }
    }
  }

  /**
   * On keypress of mobile sidebar
   * @param {object} e Event object
   */
  function onKeyPressMobileSidebar(e) {
    if (e.which === 32 || e.which === 13) {
      e.preventDefault();
      toggleMobileSidebar(e);
    }
  }

  /**
   * Initialize mobile narrow search
   */
  function initMobileNarrowSearch() {
    document.querySelectorAll('.mobile-navigation .sidebar-navigation, .js-mobile-list-navigation').forEach(el => {
      el.addEventListener('click', toggleMobileSidebar);
    });
    const container = document.querySelector(".side-facets-container-ajax");
    if (container) {
      document.querySelectorAll('.finna-search-filter-toggle .btn-search-filter, .sidebar .sidebar-close-btn').forEach(el => {
        el.addEventListener('click', toggleMobileSidebar);
      });
      document.querySelectorAll('.finna-search-filter-toggle .btn-search-filter, .sidebar .sidebar-close-btn').forEach(el => {
        el.addEventListener('keydown', function onKeyDownMobileFacets(e) {
          onKeyPressMobileSidebar(e);
        });
      });
    }
    const filters = document.querySelector('.mobile-navigation .sidebar-navigation .active-filters');
    if (filters) {
      filters.addEventListener('click', function onClickMobileActiveFilters() {
        document.querySelector('.sidebar').scrollTop(0);
      });
    }
    const narrowSearchMobileTrigger = document.querySelector('.finna-search-filter-toggle-trigger');
    const narrowSearchMobile = document.querySelector('.finna-search-filter-toggle');
    if (narrowSearchMobileTrigger && narrowSearchMobile && ('IntersectionObserver' in window)) {
      const narrowSearchMobileObserver = new IntersectionObserver(
        ([e]) => narrowSearchMobile.classList.toggle('sticky', e.intersectionRatio < 1),
        {
          threshold: [1],
          rootMargin: '-' + narrowSearchMobile.offsetHeight + 'px',
        }
      );
      narrowSearchMobileObserver.observe(narrowSearchMobileTrigger);
    }
  }

  /**
   * Set my account header as sticky
   */
  function setStickyMyaccountHeader() {
    const toolbar = document.querySelector('.toolbar-sticky');
    const finnaNavbar = document.querySelector('.finna-navbar');
    const observedElement = document.querySelector('.myaccount-sticky-header');

    if (toolbar && finnaNavbar && observedElement) {
      const observer = new IntersectionObserver(entries => {
        const intersecting = entries[0].isIntersecting;
        toolbar.classList.toggle('isSticky', !intersecting);
      }, {
        rootMargin: `-${finnaNavbar.offsetHeight}px`,
      });

      observer.observe(observedElement);
    }
  }

  /**
   * Initialize mobile cart indicator buttons
   */
  function initMobileCartIndicator() {
    $('.btn-bookbag-toggle a').on('click', function onClickMobileCart() {
      if ($(this).hasClass('cart-add')){
        $('.navbar-toggle').removeClass('activated');
        setTimeout(function triggerAnimation() {
          $('.navbar-toggle').addClass('activated');
        }, 100);
      }
    });
  }

  /**
   * Set scroll links
   */
  function initScrollLinks() {
    $('.library-link').on('click', function onClickLibraryLink() {
      $('html, body').animate({
        scrollTop: $('.recordProvidedBy').offset().top
      }, 500);
    });
    var feedbackBtn = $('.floating-feedback-btn');
    if (feedbackBtn.length) {
      var feedbackBtnOffset = feedbackBtn.offset().top;
      $(window).on("scroll", function onScrollWindow(/*event*/) {
        feedbackBtn.toggleClass('fixed', $(window).scrollTop() > feedbackBtnOffset);
      });
    }
    var backUp = $('.template-dir-record .back-to-up');
    if (backUp.length) {
      $(window).on('scroll', function onScrollWindow(/*event*/) {
        backUp.toggleClass('hidden', $(window).scrollTop() <= 2000);
      });
    }
  }

  /**
   * Initialize search box functions
   */
  function initSearchboxFunctions() {
    var searchForm = document.querySelector('.searchForm.navbar-form');
    if (searchForm) {
      var submitButton = searchForm.querySelector('button[type="submit"]');
      if (submitButton) {
        var mouseUp = function onMouseUp(ev) {
          if (1 === ev.button && ev.target === submitButton || submitButton.contains(ev.target)) {
            searchForm.setAttribute('target', '_blank');
            searchForm.submit();
            searchForm.removeAttribute('target');
          }
          document.removeEventListener('mouseup', mouseUp);
        };
        submitButton.addEventListener('mousedown', function listenToMiddleClick(e) {
          if (1 === e.button) {
            document.removeEventListener('mouseup', mouseUp);
            document.addEventListener('mouseup', mouseUp);
          }
        });
      }
    }

    if ($('.navbar-form .checkbox')[0]) {
      $('.autocomplete-results').addClass('checkbox-active');
    }
    $('.searchForm_lookfor').on('input', function onInputLookfor() {
      var lfor = $(this);
      lfor.closest('.searchForm').find('.clear-button').toggleClass('hidden', lfor.val() === '');
    });

    $('.searchForm_lookfor').on('autocomplete:select', function onAutocompleteSelect() {
      $('.navbar-form').trigger("submit");
    });

    $('.select-type').on('click', function onClickSelectType(event) {
      event.preventDefault();
      var dropdownToggle = $('.type-dropdown .dropdown-toggle');
      var dropdownItems = $('.type-dropdown .dropdown-menu .dropdown-item');

      $('input[name=type]:hidden').val($(this).siblings().val());
      var itemText = $(this).text();
      dropdownToggle.find('span:not(.icon)').text(itemText);
      dropdownToggle.attr('aria-label', VuFind.translate('Narrow Search') + ': ' + (itemText) + ' ' + VuFind.translate('selected'));
      dropdownItems.removeAttr('aria-description');
      $.each (dropdownItems, function changeDescription(index, value) {
        if (itemText === $(value).text()) {
          $(value).attr('aria-description', 'selected');
        }
      });
      dropdownToggle.dropdown('toggle');
      dropdownToggle.focus();
    });

    if (sessionStorage.getItem('vufind_retain_filters')) {
      $('.searchFormKeepFilters').closest('.checkbox').toggleClass('checked', sessionStorage.getItem('vufind_retain_filters') === 'true');
    }
  }

  /**
   * Initialize tooltips and popovers
   * @param {HTMLElement} holder Holder to look for toggletip elements from
   */
  function initToggleTips(holder) {

    /**
     * Close a ToggleTip
     * @param {HTMLElement} tipEl Tip container element
     */
    function closeToggleTip(tipEl) {
      // Reset focus from any active element in the toggletip:
      const activeElement = document.activeElement;
      const parentEl = tipEl.closest('.finna-toggletip');
      if (parentEl) {
        const buttonEl = parentEl.querySelector('.finna-toggletip__button');
        if (buttonEl) {
          buttonEl.setAttribute('aria-expanded', 'false');
          if (parentEl.contains(activeElement)) {
            buttonEl.focus();
          }
        }
      }

      tipEl.classList.remove('show');
      const tipInnerEl = tipEl.querySelector('.js-status-inner');
      if (tipInnerEl) {
        tipInnerEl.innerHTML = '';
      }
      // If focus was in the toggletip, return it to the button:
    }

    /**
     * Click event handler that closes all toggletips not being clicked
     * @param {object} e Event object
     */
    function closeToggleTipsOnClick(e) {
      document.querySelectorAll('.finna-toggletip .js-status.show').forEach((tipEl) => {
        if (tipEl !== e.target && !tipEl.contains(e.target)) {
          closeToggleTip(tipEl);
        }
      });
    }

    /**
     * Keydown event handler that closes all toggletips
     * @param {object} e Event object
     */
    function closeToggleTipsOnEsc(e) {
      if ((e.keyCode || e.which) === 27) {
        document.querySelectorAll('.finna-toggletip .js-status.show').forEach((tipEl) => {
          closeToggleTip(tipEl);
        });
      }
    }

    holder.querySelectorAll('[data-toggle="finna-toggletip"]').forEach(toggletip => {
      if (toggletip.dataset.initialized) {
        return;
      }
      toggletip.dataset.initialized = true;
      // Get the message from the data-content element
      const message = toggletip.dataset.toggletipContent || '';
      const tipEl = toggletip.parentNode.querySelector('.js-status');
      if (!tipEl) {
        return;
      }
      const tipInnerEl = tipEl.querySelector('.js-status-inner');
      if (!tipInnerEl) {
        return;
      }

      const placement = toggletip.dataset.toggletipPlacement || 'bottom';
      const popperInst = Popper.createPopper(
        toggletip,
        tipEl,
        {
          placement: placement,
          modifiers: [
            {
              name: 'flip',
              options: {
                fallbackPlacements: ['top', 'bottom', 'left', 'right'],
              },
            },
            {
              name: 'preventOverflow',
              options: {}
            }
          ],
        }
      );
      toggletip.addEventListener('click', () => {
        if (tipEl.classList.contains('show')) {
          closeToggleTip(tipEl);
        } else {
          window.setTimeout(() => {
            tipInnerEl.innerHTML = message;
            tipEl.classList.add('show');
            popperInst.update();
            toggletip.setAttribute('aria-expanded', 'true');
          }, 100);
        }
      });

      // Close on outside click
      document.addEventListener('click', closeToggleTipsOnClick);

      // Remove toggletip on Esc
      document.addEventListener('keydown', closeToggleTipsOnEsc);
    });
  }

  /**
   * Hide all tooltips when Esc is pressed
   * @param {Event} e Event
   */
  function tooltipKeyDownHandler(e) {
    if (e.which === 27) {
      document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => bootstrap.Tooltip.getOrCreateInstance(el).hide());
    }
  }

  /**
   * Hide all tooltips with a click outside of a tooltip trigger
   */
  function tooltipClickHandler() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => bootstrap.Tooltip.getOrCreateInstance(el).hide());
  }

  /**
   * Initialize tooltips and popovers
   * @param {jQuery} _holder Holder to look for tooltip elements from
   */
  function initToolTips(_holder) {
    const holder = typeof _holder === 'undefined' ? document : unwrapJQuery(_holder);
    // Supports also the old data-toggle attribute
    holder.querySelectorAll('[data-bs-toggle="tooltip"],[data-bs-toggle="tooltip"],[data-toggle="tooltip-hover"]').forEach(el => {
      if (null === el.dataset.bsToggle) {
        el.dataset.bsToggle = 'tooltip';
      }
      if (el.dataset.originalTitle) {
        el.dataset.bsTitle = el.dataset.originalTitle;
        if (el.dataset.html) {
          el.dataset.bsHtml = el.dataset.html;
        }
        if (!el.dataset.bsTrigger) {
          el.dataset.bsTrigger = 'click';
        }
        if (el.dataset.position) {
          el.dataset.bsPosition = el.dataset.position;
        }
      }
      if (el.dataset.toggle === 'tooltip-hover') {
        el.dataset.bsDelay = '{"show": 500, "hide": 200}';
        el.dataset.bsToggle = 'tooltip';
        el.dataset.bsTrigger = 'hover';
      }

      bootstrap.Tooltip.getOrCreateInstance(el);

      // Prevent link from opening if tooltip is placed inside link element:
      el.querySelectorAll(':scope > i, :scope > span').forEach((i) => {
        i.addEventListener('click', (event) => event.preventDefault());
      });
    });

    document.addEventListener('keydown', tooltipKeyDownHandler);
    document.querySelector('html').addEventListener('click', tooltipClickHandler);

    initToggleTips(holder);
  }

  /**
   * Initialize modal tooltips
   */
  function initModalToolTips() {
    const modalEl = document.getElementById('modal');
    if (modalEl) {
      modalEl.addEventListener('shown.bs.modal', () => {
        initToolTips(modalEl);
      });
    }
  }

  /**
   * Initializes additional functionality for condensed styled lists.
   * I.e search condensed, authority records record tab.
   * @param {jQuery|undefined} _holder Element as jQuery to initialize.
   *                                   If uninitialized, defaults to document.
   */
  function initCondensedList(_holder) {
    var holder = typeof _holder === 'undefined' ? $(document) : _holder;
    holder.find('.condensed-collapse-toggle').off('click').on('click', function onClickCollapseToggle(event) {
      if ((event.target.nodeName) !== 'A' && (event.target.nodeName) !== 'MARK') {
        holder = $(this).parent().parent();
        holder.toggleClass('open');
        VuFind.itemStatuses.init(holder);
        var onSlideComplete = null;
        if (holder.hasClass('open') && !holder.hasClass('opened')) {
          holder.addClass('opened');
        }

        $(this).nextAll('.condensed-collapse-data').first().slideToggle(120, 'linear', onSlideComplete);
      }
    });
  }

  /**
   * Initialize touch device galler
   */
  function initTouchDeviceGallery() {
    if ($('.result-view-grid')[0] != null && isTouchDevice()) {
      $('.result-view-grid').addClass('touch-device');
    }
  }

  /**
   * Initialize building filter event on key up
   */
  function initBuildingFilter() {
    $('#building_filter').on('keyup', function onKeyUpFilter() {
      var valThis = this.value.toLowerCase();
      $('#side-collapse-building > ul > li .facet-value').each(function doBuildingSearch() {
        var text = $(this).text().toLowerCase();
        if (text.indexOf(valThis) !== -1) {
          $(this).closest('li').show();
        } else {
          $(this).closest('li').hide();
        }
      });
    });
  }

  /**
   * Initialize jump menus
   * @param {jQuery} _holder Holder to look for jump menus to initialize
   */
  function initJumpMenus(_holder) {
    var holder = typeof _holder === 'undefined' ? $('body') : _holder;
    holder.find('select.jumpMenu').off('change').on('change', function onChangeJumpMenu() { $(this).closest('form').trigger("submit"); });
    holder.find('select.jumpMenuUrl').off('change').on('change', function onChangeJumpMenuUrl(e) { window.location.href = $(e.target).val(); });
  }

  /**
   * Initialize secondary login fields, BC only
   */
  function initSecondaryLoginField() {
    // This function exists for back-compatibility only
  }

  /**
   * Initialize ILS password recovery link
   * @param {object} links Object containing identifier and url for link href
   * @param {string} idPrefix Prepend selector with idPrefix
   * @deprecated Exists for back-compatibility with old implementation only
   */
  function initILSPasswordRecoveryLink(links, idPrefix) {
    VuFind.displayILSPasswordRecoveryLink(links, idPrefix);
  }

  /**
   * Initialize ILS self registration links
   * @param {object} links Object containing identifier and url for link href
   * @param {string} idPrefix Prepend selector with idPrefix
   */
  function initILSSelfRegistrationLink(links, idPrefix) {
    var searchPrefix = idPrefix ? '#' + idPrefix : '#';
    $(searchPrefix + 'target').on('change', function onChangeLoginTargetLink() {
      var target = $(searchPrefix + 'target').val();
      if (links[target]) {
        $('#login_library_card_register').attr('href', links[target]).show();
      } else {
        $('#login_library_card_register').hide();
      }
    }).trigger("change");
  }

  /**
   * Initialize side facets
   */
  function initSideFacets() {
    if (!document.addEventListener) {
      return;
    }
    VuFind.listen('VuFind.sidefacets.loaded', function onSideFacetsLoaded() {
      finna.dateRangeVis.init();
      initToolTips($('.sidebar'));
      initMobileNarrowSearch();
      VuFind.lightbox.bind($('.sidebar'));
    }, {once: true});
  }

  /**
   * Init Matomo(Piwik) popular searches container
   */
  function initPiwikPopularSearches() {
    var $container = $('.piwik-popular-searches');
    if ($container.length === 0) {
      return;
    }
    $container.find('.load-indicator').removeClass('hidden');
    $.getJSON(VuFind.path + '/AJAX/JSON?method=getPiwikPopularSearches')
      .done(function onGetPiwikSearchesDone(response) {
        $container.html(VuFind.updateCspNonce(response.data.html));
      })
      .fail(function onGetPiwikSearchesFail() {
        $container.find('.load-indicator').addClass('hidden');
        $container.find('.load-failed').removeClass('hidden');
      });
  }

  /**
   * Initialize auto scroll touch
   */
  function initAutoScrollTouch() {
    if (!navigator.userAgent.match(/iemobile/i) && isTouchDevice() && $(window).width() < 1025) {
      $('.search-query').on('click', function onClickSearchQuery() {
        $('html, body').animate({
          scrollTop: $(this).offset().top - 75
        }, 200);
      });
    }
  }

  /**
   * Initialize ipad check
   */
  function initIpadCheck() {
    if (navigator.userAgent.match(/iPad/i)) {
      if (navigator.userAgent.match(/OS 6_\d(_\d) like Mac OS X/i)) {
        $('body').addClass('ipad-six');
      }
    }
  }

  /**
   * Initialize record scrolling
   */
  function initScrollRecord() {
    if (!$('section.main').is('.template-name-search, .template-name-results')) {
      return;
    }

    var target = null;
    var identifier = decodeURIComponent(window.location.hash);
    if (identifier === '') {
      // Scroll to search box
      if ($(window).height() < 960 && $(window).scrollTop() === 0) {
        target = $('.search-form-container');
      }
    } else {
      // Scroll to record
      var result = $('.hiddenId[value="' + identifier.substr(1) + '"]');
      if (result.length) {
        target = result.closest('.result');
      }
    }
    if (target && target.length) {
      $('html').animate({scrollTop: target.offset().top}, 100);
    }
  }

  /**
   * Initialize lightbox login events
   */
  function initLightboxLogin() {
    if (!document.addEventListener) {
      return;
    }
    // Lightbox passes an object as an event containing keys: {formUrl, originalUrl}
    VuFind.listen('lightbox.login', function onLightboxLogin(e, cancelRefresh) {
      if ($('body').hasClass('template-name-home') && !e.formUrl.match(/catalogLogin/) && !e.formUrl.match(/\/Save/) && !e.formUrl.match(/%2[fF]Save/)) {
        window.location.href = VuFind.path + '/MyResearch/Home';
        cancelRefresh();
      }
    });
  }

  /**
   * Display content after login from a lightbox
   * @param {string} url Ajax url
   */
  function showPostLoginLightbox(url) {
    VuFind.lightbox.ajax({url: url});
  }

  /**
   * Get organisation info page link for a single organisation, or links for a list of organisations
   * @param {object}       organisation     Single organisation or a list of organisations
   *                                        with keys 'id' and optional 'sector'
   * @param {string | false} organisationName Organisation name, if any (single organisation only)
   * @param {boolean}      renderLinks      Whether to return rendered links in the response
   * @param {Function}     callback         Callback to call when done
   *
   * Note that the return format varies depending on whether a single organsation or multiple organisations
   * were requested. For the single one, the result is just the content for it, but for multiple one it's
   * keyed by organisation id.
   */
  function getOrganisationPageLink(organisation, organisationName, renderLinks, callback) {
    var params = {
      url: VuFind.path + '/AJAX/JSON?method=getOrganisationInfo',
      data: {
        method: 'getOrganisationInfo',
        element: 'organisation-page-link',
        renderLinks: renderLinks ? '1' : '0'
      }
    };
    if (typeof organisation.id === 'undefined') {
      params.data.organisations = JSON.stringify(organisation);
    } else {
      params.data.id = organisation.id;
      params.data.sector = organisation.sector || '';
    }
    if (organisationName) {
      params.data.parentName = String(organisationName);
    }
    $.ajax(params)
      .done(function onGetOrganisationInfoDone(response) {
        // Filter out null values:
        const data = Object.fromEntries(Object.entries(response.data).filter((item) => null !== item[1]));
        callback(data);
      })
      .fail(function onGetOrganisationInfoFail() {
        callback(false);
      });
  }

  /**
   * Initialize organisation page links
   */
  function initOrganisationPageLinks() {
    VuFind.observerManager.createIntersectionObserver(
      'OrganisationPageLinks',
      (element) => {
        const holder = $(element);
        var organisationId = holder.data('organisation');
        var organisationName = holder.data('organisationName');
        var organisationSector = holder.data('organisationSector');
        var organisation = {'id': organisationId, 'sector': organisationSector};
        getOrganisationPageLink(organisation, organisationName, true, function organisationPageCallback(response) {
          holder.toggleClass('done', true);
          if (response && response.found) {
            holder.html(response.html).closest('li.record-organisation').toggleClass('organisation-page-link-visible', true);
          }
        });
      },
      document.querySelectorAll('.organisation-page-link:not(.done)')
    );
  }

  /**
   * Initialize organisation info widgets
   */
  function initOrganisationInfoWidgets() {
    $('.organisation-info[data-init="1"]').each(function setupOrganisationInfo() {
      var widget = finna.organisationInfoWidget;
      widget.init($(this), finna.organisationInfo);
      widget.loadOrganisationList();
    });
  }

  /**
   * Initialize audio buttons
   */
  function initAudioButtons() {
    var scripts = {
      'videojs': 'vendor/video.min.js',
    };
    var subScripts = {
      'videojs-hotkeys': 'vendor/videojs.hotkeys.min.js',
      'videojs-quality': 'vendor/videojs-contrib-quality-levels.js',
      'videojs-airplay': 'vendor/silvermine-videojs-airplay.min.js',
    };
    $('.audio-accordion .audio-item-wrapper').each(function initAudioPlayer() {
      var self = $(this);
      var play = self.find('.play');
      var source = self.find('source');
      play.one('click', function onPlay() {
        finna.scriptLoader.load(
          scripts,
          () => {
            finna.scriptLoader.load(
              subScripts,
              function onVideoJsLoaded() {
                self.find('.audio-player-wrapper').removeClass('hide');
                var audio = self.find('audio');
                audio.removeClass('hide').addClass('video-js');
                source.attr('src', source.data('src'));
                videojs(
                  audio.attr('id'),
                  { controlBar: { volumePanel: false, muteToggle: false } },
                  function onVideoJsInited() {}
                );
                play.remove();
                self.find('.vjs-play-control').focus();
              }
            );
          }
        );
      });
      play.on('keydown', function onKeyDown(e) {
        if (e.which === 13 || e.which === 32) {
          e.preventDefault();
          play.trigger('click');
        }
      });
    });
    $('finna-video').on('keydown', function onKeyDown(e) {
      if (e.which === 13 || e.which === 32) {
        e.preventDefault();
        $(this).trigger('click');
      }
    });
  }

  /**
   * Initialize priority navigation
   */
  function initPriorityNav() {
    const navWrapperEl = document.querySelector('.nav-wrapper');
    if (!navWrapperEl || typeof navWrapperEl.dataset.disablePriorityNav !== 'undefined') {
      return;
    }
    priorityNav.init({
      mainNavWrapper: ".nav-wrapper",
      mainNav: ".nav-ul",
      navDropdownLabel: VuFind.translate('other_records'),
      navDropdownClassName: "dropdown-menu",
      navDropdownBreakpointLabel: VuFind.translate('records'),
      navDropdownToggleClassName: "nav-dropdown-toggle",
      breakPoint: 400
    });
  }

  /**
   * Initialize filters toggle button events
   */
  function initFiltersToggle () {
    var win = $(window);

    if (win.width() <= 991) {
      $('.finna-filters .filters').addClass('hidden');
      $('.finna-filters .filters-toggle .toggle-text').html(VuFind.translate('show_filters'));
    }

    win.on('throttled-resize.finna', function checkFiltersEnabled(e, data) {
      var filters = $('.finna-filters .filters');
      if (data.w > 991 && filters.hasClass('hidden')) {
        filters.removeClass('hidden');
      }

    });

    $('.filters-toggle').on('click', function filterToggleClicked() {
      var button = $(this);
      var filters = button.closest('.finna-filters').find('.filters');
      button.toggleClass('open');

      /**
       * Set state of given text
       * @param {boolean} setHidden Set text
       * @param {string} text Set button text
       */
      function setState(setHidden, text) {
        filters.toggleClass('hidden', setHidden);
        button.find('.toggle-text').html(VuFind.translate(text));
      }

      if (filters.hasClass('hidden')) {
        setState(false, 'hide_filters');
      } else {
        setState(true, 'show_filters');
      }
    });
  }

  /**
   * Initialize cookie consent events
   */
  function initCookieConsent() {
    var state = finna.common.getCookie('cookieConsent');
    if ('undefined' === typeof state || !state) {
      $('.cookie-consent-dismiss').on('click', function dismiss() {
        finna.common.setCookie('cookieConsent', 1, { expires: 365 });
        $('.cookie-consent').addClass('hidden');
      });
      $('.cookie-consent').removeClass('hidden');
    }
    VuFind.listen('cookie-consent-first-done', VuFind.refreshPage);
    VuFind.listen('cookie-consent-changed', VuFind.refreshPage);
  }

  /**
   * Toggle login accordion.
   * The accordion has a delicate relationship with the tabs. Handle with care!
   * @param {string} tabId Current tab id
   */
  function _toggleLoginAccordion(tabId) {
    var $accordionHeading = $('.login-accordion .accordion-heading a[data-tab="' + tabId + '"]').closest('.accordion-heading');
    var $loginTabs = $('.login-tabs');
    var $tabContent = $loginTabs.find('.tab-content');
    if ($accordionHeading.hasClass('active')) {
      $accordionHeading.removeClass('active');
      // Hide tab from accordion
      $loginTabs.find('.tab-pane.active').removeClass('active');
      // Deactivate any tab since it can't follow the state of a collapsed accordion
      $loginTabs.find('.nav-tabs > li > a.active').removeClass('active');
      // Move tab content out from accordions
      $tabContent.insertAfter($('.login-accordion .accordion-heading').last());
    } else {
      // Move tab content under the correct accordion toggle
      $tabContent.insertAfter($accordionHeading);
      $('.login-accordion').find('.accordion-heading.active').removeClass('active');
      $accordionHeading.addClass('active');
      $loginTabs.find('.tab-pane.active').removeClass('active');
      $loginTabs.find('.' + tabId + '-tab').addClass('active');
    }
  }

  /**
   * Activate a login tab
   * @param {string} tabId Id of the tab to activate
   */
  function _activateLoginTab(tabId) {
    const tabsEl = document.querySelector('.login-tabs');
    if (tabsEl) {
      const newTabEl = tabsEl.querySelector('li.' + tabId);
      if (newTabEl) {
        const triggerEl = newTabEl.querySelector('a');
        bootstrap.Tab.getOrCreateInstance(triggerEl).show();
      }
    }
    _toggleLoginAccordion(tabId);
  }

  /**
   * Handle a login tab click
   * @param {HTMLElement} linkEl Tab link
   */
  function handleLoginTabClick(linkEl)
  {
    const tabEl = linkEl.closest('li');
    if (tabEl && tabEl.dataset.bsTab) {
      _activateLoginTab(tabEl.dataset.bsTab);
    }
  }

  /**
   * Init login tabs
   */
  function initLoginTabs() {
    document.querySelectorAll('.login-tabs .nav-tabs a').forEach(linkEl => linkEl.addEventListener('click', () => handleLoginTabClick(linkEl)));

    // Accordion
    $('.login-accordion .accordion-toggle').on('click', function accordionClicked() {
      _activateLoginTab($(this).find('a').data('tab'));
    });
    // Call activation to position the initial content properly
    _activateLoginTab($('.login-tabs .accordion-heading.initiallyActive a').data('tab'));
  }

  /**
   * Set image paginator translations
   */
  function setImagePaginatorTranslations() {
    $.fn.setPaginatorTranslations({
      image: VuFind.translate('Image'),
      close: VuFind.translate('close'),
      next: VuFind.translate('Next Record'),
      previous: VuFind.translate('Previous Record'),
      no_cover: VuFind.translate('No Cover Image')
    });
  }

  /**
   * Initialize image paginators
   */
  function initImagePaginators() {
    $('.image-popup-trigger.init').each(function initImages() {
      $(this).finnaPaginator($(this).data('settings'), $(this).data('images'));
    });
  }

  /**
   * Initialize help tabs in custom themes
   */
  function initHelpTabs() {
    if ($('.help-tabs')[0]) {
      $('.help-tabs').removeAttr('role');
      $('.help-tab').each(function initHelpTab() {
        const $li = $(this);
        if ($li.hasClass('nav-item')) {
          // Already converted
          return;
        }

        const tabContent = $li.text();
        $li.html('')
          .addClass('nav-item')
          .attr('tabindex', '-1')
          .removeAttr('aria-selected')
          .removeAttr('role');

        const url = $li.data('url');
        const $a = $('<a>')
          .attr('href', url)
          .attr('class', 'nav-link')
          .text(tabContent)
          .appendTo($li);

        if ($li.hasClass('active')) {
          $a.addClass('active')
            .attr('aria-current', 'page')
            .trigger('focus');
        }
      });
    }
  }

  /**
   * Initialize triggers to activate print procedure
   */
  function initPrintTriggers() {
    $('[data-trigger-print]').off('click').on(
      'click',
      function printWindow() {
        window.print();
        return false;
      }
    );
  }

  /**
   * Set select checkboxes in correct myresearch pages.
   * @param {HTMLInputElement} element Checkbox element for which the change event occurs
   */
  function toggleButtonsForSelected(element) {
    if (element.closest('form').id === 'renewals') {
      var checkedRenewals = document.querySelector('form[name="renewals"] .checkbox input[type=checkbox]:checked');
      var renewSelected = document.getElementById('renewSelected');
      if (renewSelected) {
        renewSelected.toggleAttribute('disabled', checkedRenewals === null);
      }
    } else if (element.closest('form').id === 'purge_history') {
      var checkedHistory = document.querySelector('form[name="purge_history"] .result .checkbox input[type=checkbox]:checked');
      var purgeSelected = document.getElementById('purgeSelected');
      if (purgeSelected) {
        purgeSelected.toggleAttribute('disabled', checkedHistory === null);
      }
    }
  }

  /**
   * Initialize buttons to select all checkboxes
   */
  function initSelectAllButtonListeners() {
    document.querySelectorAll('form[name="renewals"] .checkbox').forEach(element => {
      element.addEventListener('change', function disableButtons() {
        toggleButtonsForSelected(element);
      });
    });
    document.querySelectorAll('form[name="purge_history"] .checkbox').forEach(element => {
      element.addEventListener('change', function disableButtons() {
        toggleButtonsForSelected(element);
      });
    });
  }

  var my = {
    getOrganisationPageLink: getOrganisationPageLink,
    isTouchDevice: isTouchDevice,
    initCondensedList: initCondensedList,
    initTruncate: initTruncate,
    initLocationService: initLocationService,
    initBuildingFilter: initBuildingFilter,
    initJumpMenus: initJumpMenus,
    initMobileNarrowSearch: initMobileNarrowSearch,
    initOrganisationPageLinks: initOrganisationPageLinks,
    initSecondaryLoginField: initSecondaryLoginField,
    initILSPasswordRecoveryLink: initILSPasswordRecoveryLink,
    initILSSelfRegistrationLink: initILSSelfRegistrationLink,
    initLoginTabs: initLoginTabs,
    initToolTips: initToolTips,
    initImagePaginators: initImagePaginators,
    init: function init() {
      initResizeListener();
      initScrollRecord();
      initJumpMenus();
      initAnchorNavigationLinks();
      initTruncate();
      initContentNavigation();
      initMobileNarrowSearch();
      setStickyMyaccountHeader();
      initMobileCartIndicator();
      initToolTips();
      initModalToolTips();
      initScrollLinks();
      initSearchboxFunctions();
      initCondensedList();
      initTouchDeviceGallery();
      initSideFacets();
      initPiwikPopularSearches();
      initAutoScrollTouch();
      initIpadCheck();
      initLightboxLogin();
      initOrganisationInfoWidgets();
      initOrganisationPageLinks();
      initAudioButtons();
      initPriorityNav();
      initFiltersToggle();
      initCookieConsent();
      setImagePaginatorTranslations();
      initImagePaginators();
      initHelpTabs();
      initPrintTriggers();
      initSelectAllButtonListeners();
    },
    showPostLoginLightbox: showPostLoginLightbox
  };

  return my;
})();

