/*global VuFind, checkSaveStatuses, action, finna, initFacetTree, priorityNav */
finna.layout = (function finnaLayout() {
  var _fixFooterTimeout = null;
  var masonryInitialized = false;

  function initResizeListener() {
    var intervalId = false;
    $(window).on('resize', function onResizeWindow(/*e*/) {
      clearTimeout(intervalId);
      intervalId = setTimeout(function onTimeout() {
        var data = {
          w: $(window).width(),
          h: $(window).height()
        };
        $(window).trigger('resize.screen.finna', data);
      }, 100);
    });
  }

  function isTouchDevice() {
    return (('ontouchstart' in window)
      || (navigator.maxTouchPoints > 0)
      || (navigator.msMaxTouchPoints > 0)); // IE10, IE11, Edge
  }

  function detectIe() {
    var undef,
      v = 3,
      div = document.createElement('div'),
      all = div.getElementsByTagName('i');
    while (
      div.innerHTML = '<!--[if gt IE ' + (++v) + ']><i></i><![endif]-->',
      all[0]
    );
    return v > 4 ? v : undef;
  }

  // Append current anchor (location.hash) to selected links
  // in order to preserve the anchor when the link is clicked.
  // This is used in top header language links.
  function initAnchorNavigationLinks() {
    $('a.preserve-anchor').each(function addAnchors() {
      var hash = location.hash;
      if (hash.length === 0) {
        return;
      }
      $(this).attr('href', $(this).attr('href') + hash);
    });
  }

  function initFixFooter() {
    $(window).resize(function onResizeWindow(/*e*/) {
      if (!_fixFooterTimeout) {
        _fixFooterTimeout = setTimeout(function fixFooterCallback() {
          _fixFooterTimeout = null;
          $('footer').height('auto');
          var detectHeight = $(window).height() - $('body').height();
          if (detectHeight > 0) {
            var expandedFooter = $('footer').height() + detectHeight;
            $('footer').height(expandedFooter);
          }
        }, 50);
      }
    }).resize();
  }

  function initLocationService(_holder) {
    var holder = typeof _holder === 'undefined' ? $(document) : _holder;

    function closeModalCallback(modal) {
      modal.removeClass('location-service location-service-qrcode');
      modal.find('.modal-dialog').removeClass('modal-lg');
    }

    holder.find('a.location-service.location-service-modal').click(function onClickModalLink(/*e*/) {
      var modal = $('#modal');
      modal.addClass('location-service');
      modal.find('.modal-dialog').addClass('modal-lg');

      $('#modal').one('hidden.bs.modal', function onHiddenModal() {
        closeModalCallback($(this));
      });
      modal.find('.modal-body').load($(this).data('lightbox-href') + '&layout=lightbox');
      modal.modal();
      return false;
    });
  }

  function initTruncate(_holder) {
    var holder = typeof _holder === 'undefined' ? $(document) : _holder;

    function notifyTruncateChange(field) {
      field.find('.truncate-change span').each(function setupTruncateChange(ind, e) {
        $(e).trigger('truncate-change');
      });
    }

    var truncation = [];
    var rowHeight = [];
    holder.find('.truncate-field').not('.truncate-done').each(function handleTruncate(index) {
      var self = $(this);
      self.addClass('truncate-done');

      if (typeof(self.data('row-height')) !== 'undefined') {
        rowHeight[index] = self.data('row-height');
      } else if (self.children().length > 0) {
        // use first child as the height element if available
        var heightElem = self.children().first();
        if (heightElem.is('div')) {
          rowHeight[index] = parseFloat(heightElem.height());
        } else {
          rowHeight[index] = parseFloat(heightElem.css('line-height').replace('px', ''));
        }
      } else {
        rowHeight[index] = parseFloat(self.css('line-height').replace('px', ''));
      }

      var rowCount = 3;
      if (self.data('rows')) {
        rowCount = self.data('rows');
      }

      // get the line-height of first element to determine each text line height
      truncation[index] = rowHeight[index] * rowCount;
      // truncate only if there's more than one line to hide
      if (self.height() > (truncation[index] + rowHeight[index] + 1)) {
        self.css('height', truncation[index] - 1 + 'px');
        self.before('<button type="button" class="less-link-top">' + VuFind.translate('show_less') + ' <i class="fa fa-arrow-up" aria-hidden="true"></i></button>');
        self.after('<button type="button" class="more-link">' + VuFind.translate('show_more') + ' <i class="fa fa-arrow-down" aria-hidden="true"></i></button><button type="button" class="less-link">' + VuFind.translate('show_less') + ' <i class="fa fa-arrow-up" aria-hidden="true"></i></button>');
        $('.less-link-top').hide();
        $('.less-link').hide();

        self.nextAll('.more-link').first().click(function onClickMoreLink(/*event*/) {
          $(this).hide();
          $(this).next('.less-link').show();
          $(this).prev('.truncate-field').css('height', 'auto');
          if (self.height() > (rowHeight[index] * 30)) {
            $(this).siblings('.less-link-top').show();
          }
          notifyTruncateChange(self);
        });

        self.prevAll('.less-link-top').first().click(function onClickLessLink(/*event*/) {
          $(this).hide();
          $(this).siblings('.less-link').hide();
          $(this).siblings('.more-link').show();
          $(this).nextAll('.truncate-field').first().css('height', truncation[index] - 1 + 'px');
          notifyTruncateChange(self);
        });
        self.nextAll('.less-link').first().click(function onClickLessLink(/*event*/) {
          $(this).hide();
          $(this).siblings('.less-link-top').hide();
          $(this).prev('.more-link').show();
          $(this).prevAll('.truncate-field').first().css('height', truncation[index] - 1 + 'px');
          notifyTruncateChange(self);
        });
        self.addClass('truncated');
      }
      notifyTruncateChange(self);
      self.trigger('truncate-done', [self]);
    });
  }

  function initContentNavigation() {
    if ($('.content-navigation-menu')[0]) {
      $('.content-section').each(function initContentSection(index) {
        var link = '#' + $(this).attr('id');
        $('.content-navigation-menu').append('<h2 class="nav-' + index + '"> <a href="' + link + '">' + $('h2', this).text() + '</a></h2>');
        $('.content-navigation-menu h2.nav-' + index).click(function onMenuClick() {
          $('body, html').animate({
            scrollTop: $(link).offset().top - 5
          }, 350);
        });
      });

      var menuPosition = $('.content-navigation-menu').offset().top;
      // fixed menu & prevent footer overlap
      $(window).scroll(function onScroll() {
        if ($(window).scrollTop() > menuPosition) {
          $('.content-navigation-menu').addClass('attached');
          if ($(window).scrollTop() + $('.content-navigation-menu').outerHeight(true) > $('footer').offset().top) {
            $('.content-navigation-menu').css({'bottom': $('footer').height() + 20 + 'px', 'top': 'auto'});
          }
          else {
            $('.content-navigation-menu').css({'bottom': 'auto', 'top': '0px'});
          }
        }
        else {
          $('.content-navigation-menu').removeClass('attached');
        }
      });
    }
  }

  function initHelpTabs() {
    if ($('.help-tabs')[0]) {
      $('.help-tab').each(function initHelpTab() {
        if ($(this).hasClass('active')) {
          $(this).focus();
        }
        var url = $(this).data('url');
        $(this).keydown(function onTabEnter(event) {
          if (event.which === 13) {
            window.location.href = url;
          }
        });
        $(this).click(function onTabClick() {
          window.location.href = url;
        });
      });
    }
  }

  function initRecordSwipe() {
    if ($('#view-pager').length && isTouchDevice()) {
      $('section.main').append("<div class='swipe-arrow-navigation arrow-navigation-left'><i class='fa fa-arrow-left'></i></div>");
      $('section.main').append("<div class='swipe-arrow-navigation arrow-navigation-right'><i class='fa fa-arrow-right'></i></div>");
      $('.swipe-arrow-navigation').hide();
      $(".template-dir-record .record").swipe( {
        allowPageScroll: "vertical",
        swipeRight: function swipeRight(/*event, phase, direction, distance, duration*/) {
          if ($('#view-pager .pager-previous-record a').length) {
            var prevRecordUrl = $('#view-pager .pager-previous-record a').attr('href');
            window.location.href = prevRecordUrl;
          }
        },
        swipeLeft: function swipeLeft(/*event, direction, distance, duration*/) {
          if ($('#view-pager .pager-next-record a').length) {
            var nextRecordUrl = $('#view-pager .pager-next-record a').attr('href');
            window.location.href = nextRecordUrl;
          }
        },
        swipeStatus: function swipeStatus(event, phase, direction, distance/*, duration, fingers*/) {
          if (phase === 'move' && direction === 'right' && distance > 75 && $('#view-pager .pager-previous-record a').length) {
            $('.arrow-navigation-left').show('fast');
          }
          if (phase === 'move' && direction === 'left' && distance > 75 && $('#view-pager .pager-next-record a').length) {
            $('.arrow-navigation-right').show('fast');
          }
          if (phase === 'cancel') {
            $('.swipe-arrow-navigation').hide('fast');
          }
        },
        // Default is 75px, set to 0 for demo so any distance triggers swipe
        threshold: 125,
        cancelThreshold: 20
      });
    }
  }

  function initMobileNarrowSearch() {
    $('.mobile-navigation .sidebar-navigation, .sidebar h1').unbind('click').click(function onClickMobileNav(e) {
      if ($(e.target).attr('class') !== 'fa fa-info-big') {
        $('.sidebar').toggleClass('open');
      }
      $('.mobile-navigation .sidebar-navigation i').toggleClass('fa-arrow-down');
      $('body').toggleClass('prevent-scroll');
    });
    $('.mobile-navigation .sidebar-navigation .active-filters').unbind('click').click(function onClickMobileActiveFilters() {
      $('.sidebar').scrollTop(0);
    });
  }

  function initCheckboxClicks() {
    $('.checkboxFilter:not(.mylist-select-all) .checkbox input').click(function onClickCheckbox() {
      $(this).closest('.checkbox').toggleClass('checked');
      var nonChecked = true;
      $('.myresearch-row .checkboxFilter .checkbox').each(function setupCheckbox() {
        if ($(this).hasClass('checked')) {
          $('.mylist-functions button, .mylist-functions select').removeAttr('disabled');
          $('.mylist-functions .jump-menu-style').removeClass('disabled');
          nonChecked = false;
        }
      });
      if (nonChecked) {
        $('.mylist-functions button, .mylist-functions select').attr('disabled', true);
        $('.mylist-functions .jump-menu-style').addClass('disabled');
      }
    });

    var myListSelectAll = $('.checkboxFilter.mylist-select-all');
    var myListJumpMenu = $('.mylist-functions .jump-menu-style');
    var myListFunctions = $('.mylist-functions button, .mylist-functions select');
    myListSelectAll.find('.checkbox .checkbox-select-all').click(function onClickCheckbox() {
      var checkboxes = $('.myresearch-row .checkboxFilter .checkbox, .checkboxFilter.mylist-select-all .checkbox');
      if ($(this).closest('.checkbox').hasClass('checked')) {
        var isEverythingChecked = !$('.myresearch-row .checkboxFilter .checkbox').not('.checked').length;
        checkboxes.toggleClass('checked', !isEverythingChecked);
        myListJumpMenu.toggleClass('disabled', isEverythingChecked);
        myListFunctions.attr('disabled', isEverythingChecked);
      } else {
        checkboxes.toggleClass('checked', true);
        myListJumpMenu.toggleClass('disabled', false);
        myListFunctions.attr('disabled', false);
      }
    });
  }

  function initScrollLinks() {
    $('.library-link').click(function onClickLibraryLink() {
      $('html, body').animate({
        scrollTop: $('.recordProvidedBy').offset().top
      }, 500);
    });
    if ($('.floating-feedback-btn').length) {
      var feedbackBtnOffset = $('.floating-feedback-btn').offset().top;
      $(window).scroll(function onScrollWindow(/*event*/) {
        var scroll = $(window).scrollTop();
        if (scroll > feedbackBtnOffset) {
          $('.floating-feedback-btn').addClass('fixed');
        }
        else {
          $('.floating-feedback-btn').removeClass('fixed');
        }
      });
    }
    if ($('.template-dir-record .back-to-up').length) {
      $(window).scroll(function onScrollWindow(/*event*/) {
        var scroll = $(window).scrollTop();
        if (scroll > 2000) {
          $('.template-dir-record .back-to-up').removeClass('hidden');
        }
        else {
          $('.template-dir-record .back-to-up').addClass('hidden');
        }
      });
    }
  }

  function initSearchboxFunctions() {
    if ($('.navbar-form .checkbox')[0]) {
      $('.autocomplete-results').addClass('checkbox-active');
    }
    $('.searchForm_lookfor').on('input', function onInputLookfor() {
      var form = $(this).closest('.searchForm');
      if ($(this).val() !== '' ) {
        form.find('.clear-button').removeClass('hidden');
      } else {
        form.find('.clear-button').addClass('hidden');
      }
    });

    $('.clear-button').click(function onClickClear() {
      var form = $(this).closest('.searchForm');
      form.find('.searchForm_lookfor').val('');
      form.find('.clear-button').addClass('hidden');
      form.find('.searchForm_lookfor').focus();
    });

    $('.searchForm_lookfor').bind('autocomplete:select', function onAutocompleteSelect() {
      $('.navbar-form').submit();
    });

    $('.select-type').on('click', function onClickSelectType(event) {
      event.preventDefault();
      var dropdownToggle = $('.type-dropdown .dropdown-toggle');

      $('input[name=type]:hidden').val($(this).siblings().val());
      dropdownToggle.find('span').text($(this).text());
      dropdownToggle.attr('aria-label', ($(this).text()));
      dropdownToggle.dropdown('toggle');
      dropdownToggle.focus();
    });

    if (sessionStorage.getItem('vufind_retain_filters')) {
      if (sessionStorage.getItem('vufind_retain_filters') === 'true') {
        $('.searchFormKeepFilters').closest('.checkbox').addClass('checked');
      } else {
        $('.searchFormKeepFilters').closest('.checkbox').removeClass('checked');
      }
    }
  }

  function initToolTips(_holder) {
    var holder = typeof _holder === 'undefined' ? $(document) : _holder;

    holder.find('[data-toggle="tooltip"]').tooltip({trigger: 'click', viewport: '.container'});
    // prevent link opening if tooltip is placed inside link element
    holder.find('[data-toggle="tooltip"] > i').click(function onClickTooltip(event) {
      event.preventDefault();
    });
    // close tooltip if user clicks anything else than tooltip button
    $('html').click(function onClickHtml(e) {
      if (typeof $(e.target).parent().data('original-title') == 'undefined' && typeof $(e.target).data('original-title') == 'undefined') {
        $('[data-toggle="tooltip"]').tooltip('hide');
      }
    });
  }

  function initCondensedList() {
    $('.condensed-collapse-toggle').click(function onClickCollapseToggle(event) {
      if ((event.target.nodeName) !== 'A' && (event.target.nodeName) !== 'MARK') {
        $(this).nextAll('.condensed-collapse-data').first().slideToggle(120, 'linear');
        $('.fa-arrow-right', this).toggleClass('fa-arrow-down');
        var holder = $(this).parent().parent();
        holder.toggleClass('open');
        if (holder.hasClass('open') && !holder.hasClass('opened')) {
          holder.addClass('opened');
          finna.itemStatus.initItemStatuses(holder);
          finna.itemStatus.initDedupRecordSelection(holder);
        }
      }
    });
  }

  function initTouchDeviceGallery() {
    if ($('.result-view-grid')[0] != null && isTouchDevice()) {
      $('.result-view-grid').addClass('touch-device');
    }
  }

  function initBuildingFilter() {
    $('#building_filter').keyup(function onKeyUpFilter() {
      var valThis = this.value.toLowerCase();
      $('#facet_building>ul>li>a .text').each(function doBuildingSearch() {
        var text = $(this).text().toLowerCase();
        if (text.indexOf(valThis) !== -1) {
          $(this).closest('li').show();
        } else {
          $(this).closest('li').hide();
        }
      });
    });
  }

  function addJSTreeListener(treeNode) {
    treeNode.bind('ready.jstree', function onReadyJstree() {
      var tree = $(this);
      // if hierarchical facet contains 2 or less top level items, it is opened by default
      if (tree.find('ul > li').length <= 2) {
        tree.find('ul > li.jstree-node.jstree-closed > i.jstree-ocl').each(function openNode() {
          tree.jstree('open_node', this, null, false);
        });
      }
      // show filter if 15+ organisations
      if (tree.parent().parent().attr('id') === 'side-panel-building' && tree.find('ul.jstree-container-ul > li').length > 15) {
        $(this).prepend('<div class="building-filter"><label for="building_filter" class="sr-only">' + VuFind.translate('Organisation') + '</label><input class="form-control" id="building_filter" placeholder="' + VuFind.translate('Organisation') + '..."></input></div>');
        initBuildingFilter();
      }
      // open facet if it has children and it is selected
      $(tree.find('.jstree-node.active.jstree-closed')).each(function openNode() {
        tree.jstree('open_node', this, null, false);
      });
    });
  }

  function initHierarchicalFacet(treeNode, inSidebar) {
    addJSTreeListener(treeNode);
    initFacetTree(treeNode, inSidebar);
  }

  function initJumpMenus(_holder) {
    var holder = typeof _holder === 'undefined' ? $('body') : _holder;
    holder.find('select.jumpMenu').unbind('change').change(function onChangeJumpMenu() { $(this).closest('form').submit(); });
    holder.find('select.jumpMenuUrl').unbind('change').change(function onChangeJumpMenuUrl(e) { window.location.href = $(e.target).val(); });
  }

  function initSecondaryLoginField(labels, idPrefix) {
    var searchPrefix = idPrefix ? '#' + idPrefix : '#';
    $(searchPrefix + 'target').change(function onChangeLoginTarget() {
      var target = $(searchPrefix + 'target').val();
      var field = $(searchPrefix + 'secondary_username');
      if ((typeof labels[target] === 'undefined') || labels[target] === '') {
        field.val('');
        field.closest('.form-group').hide();
      } else {
        var group = field.closest('.form-group');
        group.find('label').text(labels[target] + ':');
        group.show();
      }
    }).change();
  }

  function initILSPasswordRecoveryLink(links, idPrefix) {
    var searchPrefix = idPrefix ? '#' + idPrefix : '#';
    $(searchPrefix + 'target').change(function onChangeLoginTargetLink() {
      var target = $(searchPrefix + 'target').val();
      if (links[target]) {
        $('#login_library_card_recovery').attr('href', links[target]).show();
      } else {
        $('#login_library_card_recovery').hide();
      }
    }).change();
  }

  function initILSSelfRegistrationLink(links, idPrefix) {
    var searchPrefix = idPrefix ? '#' + idPrefix : '#';
    $(searchPrefix + 'target').change(function onChangeLoginTargetLink() {
      var target = $(searchPrefix + 'target').val();
      if (links[target]) {
        $('#login_library_card_register').attr('href', links[target]).show();
      } else {
        $('#login_library_card_register').hide();
      }
    }).change();
  }

  function initSideFacets() {
    if (!document.addEventListener) {
      return;
    }
    document.addEventListener('VuFind.sidefacets.loaded', function onSideFacetsLoaded() {
      finna.dateRangeVis.init();
      initToolTips($('.sidebar'));
      initMobileNarrowSearch();
      VuFind.lightbox.bind($('.sidebar'));
    });
    document.addEventListener('VuFind.sidefacets.treenodeloaded', function onTreeNodeLoaded(e) {
      addJSTreeListener(e.detail.node);
    });
  }

  function initPiwikPopularSearches() {
    var $container = $('.piwik-popular-searches');
    if ($container.length === 0) {
      return;
    }
    $container.find('.load-indicator').removeClass('hidden');
    $.getJSON(VuFind.path + '/AJAX/JSON?method=getPiwikPopularSearches')
      .done(function onGetPiwikSearchesDone(response) {
        $container.html(response.data.html);
      })
      .fail(function onGetPiwikSearchesFail() {
        $container.find('.load-indicator').addClass('hidden');
        $container.find('.load-failed').removeClass('hidden');
      });
  }

  function initAutoScrollTouch() {
    if (!navigator.userAgent.match(/iemobile/i) && isTouchDevice() && $(window).width() < 1025) {
      $('.search-query').click(function onClickSearchQuery() {
        $('html, body').animate({
          scrollTop: $(this).offset().top - 75
        }, 200);
      });
    }
  }

  function initIpadCheck() {
    if (navigator.userAgent.match(/iPad/i)) {
      if (navigator.userAgent.match(/OS 6_\d(_\d) like Mac OS X/i)) {
        $('body').addClass('ipad-six');
      }
    }
  }

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

  function initLightboxLogin() {
    if (!document.addEventListener) {
      return;
    }
    document.addEventListener('VuFind.lightbox.login', function onLightboxLogin(e) {
      if (typeof action !== 'undefined' && action === 'home' && !e.detail.formUrl.match(/catalogLogin/) && !e.detail.formUrl.match(/\Save/) && !e.detail.formUrl.match(/%2[fF]Save/)) {
        window.location.href = VuFind.path + '/MyResearch/Home';
        e.preventDefault();
      }
    });
    $('#modal').on('show.bs.modal', function onShowModal() {
      if ($('#modal').find('#authcontainer').length > 0) {
        $('#modal .modal-dialog').addClass('modal-lg modal-lg-dynamic');
      }
    });
    $('#modal').on('hidden.bs.modal', function onHiddenModal() {
      $('#modal .modal-dialog.modal-lg-dynamic').removeClass('modal-lg');
    });
  }

  function initLoadMasonry() {
    var ie = detectIe();
    // do not execute on ie8 or lower as they are not supported by masonry
    if (ie > 8 || ie == null) {
      $('.result-view-grid .masonry-wrapper').waitForImages(function imageCallback() {
        // init Masonry after all images have loaded
        $('.result-view-grid .masonry-wrapper').masonry({
          fitWidth: false,
          itemSelector: '.result.grid',
          columnWidth: '.result.grid',
          isResizeBound: 'true',
          horizontalOrder: 'true'
        });
        $(this).trigger('masonryInited');
        masonryInitialized = true;
      });
    }
  }

  function getMasonryState() {
    return masonryInitialized;
  }

  function getOrganisationPageLink(organisation, organisationName, link, callback) {
    var params = {
      url: VuFind.path + '/AJAX/JSON?method=getOrganisationInfo',
      dataType: 'json',
      method: 'POST',
      data: {
        method: 'getOrganisationInfo',
        'params[action]': 'lookup',
        link: link ? '1' : '0',
        parent: organisation
      }
    };
    if (organisationName) {
      params.data.parentName = String(organisationName);
    }
    $.ajax(params)
      .done(function onGetOrganisationInfoDone(response) {
        callback(response.data);
      })
      .fail(function onGetOrganisationInfoFail() {
        callback(false);
      });
  }

  function initOrganisationPageLinks() {
    $('.organisation-page-link').not('.done').map(function setupOrganisationPageLinks() {
      $(this).one('inview', function onInViewLink() {
        var holder = $(this);
        var organisationId = $(this).data('organisation');
        var organisationName = $(this).data('organisationName');
        var organisationSector = $(this).data('organisationSector');
        var organisation = {'id': organisationId, 'sector': organisationSector};
        getOrganisationPageLink(organisation, organisationName, true, function organisationPageCallback(response) {
          holder.toggleClass('done', true);
          if (response) {
            $.each(response, function handleLinks(id, item) {
              holder.html(item).closest('li.record-organisation').toggleClass('organisation-page-link-visible', true);
            });
          }
        });
      });
    });
  }

  function initOrganisationInfoWidgets() {
    $('.organisation-info[data-init="1"]').map(function setupOrganisationInfo() {
      var service = finna.organisationInfo;
      var widget = finna.organisationInfoWidget;
      widget.init($(this), service);
      widget.loadOrganisationList();
    });
  }

  function initVideoButtons() {
    finna.videoPopup.initVideoPopup($('body'));
    finna.videoPopup.initIframeEmbed($('body'));
  }

  function loadScripts(scripts, callback) {
    var needed = {};
    // Check for required scripts that are not yet loaded
    if (scripts) {
      for (var item in scripts) {
        if (scripts.hasOwnProperty(item) && $('#' + item).length === 0) {
          needed[item] = scripts[item];
        }
      }
    }
    var loadCount = Object.keys(needed).length;
    if (loadCount) {
      // Load scripts and initialize player when all are loaded
      var scriptLoaded = function scriptLoaded() {
        if (--loadCount === 0) {
          if (typeof callback === 'function') {
            callback();
          }
        }
      };
      for (var itemNeeded in needed) {
        if (needed.hasOwnProperty(itemNeeded)) {
          $(needed[itemNeeded])
            .load(scriptLoaded)
            .attr('async', 'true')
            .appendTo($('head'))
            .load();
        }
      }
    } else if (typeof callback === 'function') {
      callback();
    }
  }

  function initKeyboardNavigation() {
    $(window).keyup(function onKeyUp(e) {
      var $target = $(e.target);
      // jsTree link target navigation
      if ((e.which === 13 || e.which === 32)
          && $target.hasClass('jstree-anchor') && $target.find('.main').length > 0
      ) {
        $target.find('.main').click();
        e.preventDefault();
        return false;
      }
      return true;
    });
  }

  function initPriorityNav() {
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

  function initFiltersToggle () {
    if ($(window).width() <= 991) {
      $('.finna-filters .filters').addClass('hidden');
      $('.finna-filters .filters-toggle .toggle-text').html(VuFind.translate('show_filters'));
    }

    $(window).resize(function checkFiltersEnabled(){
      if ($(window).width() > 991 && $('.finna-filters .filters').hasClass('hidden')) {
        $('.finna-filters .filters').removeClass('hidden');
      }
    });

    $('.filters-toggle').click(function filterToggleClicked() {
      var button = $(this);
      var filters = button.closest('.finna-filters').find('.filters');

      function setState(setHidden, arrowClass, text) {
        filters.toggleClass('hidden', setHidden);
        button.find('.fa').attr('class', arrowClass);
        button.find('.toggle-text').html(VuFind.translate(text));
      }

      if (filters.hasClass('hidden')) {
        setState(false, 'fa fa-arrow-up', 'hide_filters');
      } else {
        setState(true, 'fa fa-arrow-down', 'show_filters');
      }
    });
  }

  function initFiltersCheckbox() {
    $('#filter-checkbox').change(function setCheckboxClass(){
      $('.finna-filters .checkbox').toggleClass('checked');
      var sort = $(this).closest('form').find('input[name=sort]');
      sort.val($('.finna-filters .checkbox').hasClass('checked') ? sort.data('value') : '');
    });
  }

  function initCookieConsent() {
    var state = $.cookie('cookieConsent');
    if ('undefined' === typeof state || !state) {
      $('.cookie-consent-dismiss').click(function dismiss() {
        $.cookie('cookieConsent', 1, {path: VuFind.path, expires: 365});
        $('.cookie-consent').addClass('hidden');
      });
      $('.cookie-consent').removeClass('hidden');
    }
  }

  // The accordion has a delicate relationship with the tabs. Handle with care!
  function _toggleLoginAccordion(tabId) {
    var $accordionHeading = $('.login-accordion .accordion-heading a[data-tab="' + tabId + '"]').closest('.accordion-heading');
    var $loginTabs = $('.login-tabs');
    var $tabContent = $loginTabs.find('.tab-content');
    if ($accordionHeading.hasClass('active')) {
      $accordionHeading.removeClass('active');
      // Hide tab from accordion
      $loginTabs.find('.tab-pane.active').removeClass('active');
      // Deactivate any tab since it can't follow the state of a collapsed accordion
      $loginTabs.find('.nav-tabs li.active').removeClass('active');
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

  function _activateLoginTab(tabId) {
    var $top = $('.login-tabs');
    $top.find('.tab-pane.active').removeClass('active');
    $top.find('li.' + tabId).tab('show');
    $top.find('.' + tabId + '-tab').addClass('active');
    _toggleLoginAccordion(tabId);
  }

  function initLoginTabs() {
    // Tabs
    $('.login-tabs .nav-tabs a').click(function recordTabsClick() {
      if (!$(this).closest('li').hasClass('active')) {
        _activateLoginTab(this.className);
      }
      return false;
    });

    // Accordion
    $('.login-accordion .accordion-toggle').click(function accordionClicked() {
      _activateLoginTab($(this).find('a').data('tab'));
    });
    // Call activation to position the initial content properly
    _activateLoginTab($('.login-tabs .accordion-heading.initiallyActive a').data('tab'));
  }

  var my = {
    getOrganisationPageLink: getOrganisationPageLink,
    isTouchDevice: isTouchDevice,
    initTruncate: initTruncate,
    initLocationService: initLocationService,
    initHierarchicalFacet: initHierarchicalFacet,
    initJumpMenus: initJumpMenus,
    initMobileNarrowSearch: initMobileNarrowSearch,
    initOrganisationPageLinks: initOrganisationPageLinks,
    initSecondaryLoginField: initSecondaryLoginField,
    initILSPasswordRecoveryLink: initILSPasswordRecoveryLink,
    initILSSelfRegistrationLink: initILSSelfRegistrationLink,
    initLoginTabs: initLoginTabs,
    loadScripts: loadScripts,
    getMasonryState: getMasonryState,
    init: function init() {
      initScrollRecord();
      initJumpMenus();
      initAnchorNavigationLinks();
      initFixFooter();
      initTruncate();
      initContentNavigation();
      initHelpTabs();
      initRecordSwipe();
      initMobileNarrowSearch();
      initCheckboxClicks();
      initToolTips();
      initResizeListener();
      initScrollLinks();
      initSearchboxFunctions();
      initCondensedList();
      if (typeof checkSaveStatuses !== 'undefined') { checkSaveStatuses(); }
      initTouchDeviceGallery();
      initSideFacets();
      initPiwikPopularSearches();
      initAutoScrollTouch();
      initIpadCheck();
      initLightboxLogin();
      initLoadMasonry();
      initOrganisationInfoWidgets();
      initOrganisationPageLinks();
      initVideoButtons();
      initKeyboardNavigation();
      initPriorityNav();
      initFiltersToggle();
      initFiltersCheckbox();
      initCookieConsent();
    }
  };

  return my;
})();

