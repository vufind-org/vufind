/*global VuFind, checkSaveStatuses, action, finna, L, initFacetTree, setupFacets, videojs, priorityNav, buildFacetNodes */
finna.layout = (function finnaLayout() {
  var _fixFooterTimeout = null;

  function initMap(map) {
    // Add zoom control with translated tooltips
    L.control.zoom({
      position: 'topleft',
      zoomInTitle: VuFind.translate('map_zoom_in'),
      zoomOutTitle: VuFind.translate('map_zoom_out')
    }).addTo(map);

    // Enable mouseWheel zoom on click
    map.once('focus', function onFocusMap() {
      map.scrollWheelZoom.enable();
    });
    map.scrollWheelZoom.disable();
  }

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
        var visible = $(e).position().top <= field.height();
        $(e).trigger('truncate-change', [visible]);
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
        if (self.hasClass('wide')) { // generate different truncate styles according to class
          self.after('<div class="more-link wide"><i class="fa fa-handle-open"></i></div><div class="less-link wide"> <i class="fa fa-handle-close"></i></div>');
        } else {
          self.after('<div class="more-link">' + VuFind.translate('show_more') + ' <i class="fa fa-arrow-down"></i></div><div class="less-link">' + VuFind.translate('show_less') + ' <i class="fa fa-arrow-up"></i></div>');
        }
        $('.less-link').hide();

        self.nextAll('.more-link').first().click(function onClickMoreLink(/*event*/) {
          $(this).hide();
          $(this).next('.less-link').show();
          $(this).prev('.truncate-field').css('height', 'auto');
          notifyTruncateChange(self);
        });

        self.nextAll('.less-link').first().click(function onClickLessLink(/*event*/) {
          $(this).hide();
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

  function initTruncatedRecordImageNavi() {
    function displayTruncatedImage(placeholder) {
      var img = $('<img/>');
      img.append($('<i class="fa fa-spinner fa-spin"/>')).one('load', function onLoadImage() {
        $(this).empty();
      })
      img.attr('src', placeholder.data('src'));
      img.attr('alt', '');
      placeholder.parent().removeClass('truncate-change');
      placeholder.replaceWith(img);
    }

    // Load truncated record images lazily when parent container is opened
    $('.recordcovers .truncate-change span').each(function addTruncateChangeHandler() {
      $(this).bind('truncate-change', function onTruncateChange(e, visible) {
        if (visible) {
          $(this).unbind('truncate-change');
          // Postpone loading until the image placeholder is scrolled into viewport
          $(this).unbind('inview').one('inview', function onInView() {
            displayTruncatedImage($(this));
          });
        }
      });
    });

    // Add image count to 'more-link' label when truncate-field has been inited.
    $('.recordcovers.truncate-field').bind('truncate-done', function onTruncateDone(e, field) {
      var moreLink = field.nextAll('.more-link');
      if (moreLink.length) {
        var childrenCnt = field.find('a').not('.hide').length;
        var cnt = $('<span class="cnt">(' + childrenCnt + ')</span>');
        cnt.insertAfter(moreLink.first().children().first());
      }
    });
  }

  function initContentNavigation() {
    if ($('.content-navigation-menu')[0]) {
      $('.content-section').each(function initContentSection(index) {
        var link = '#' + $(this).attr('id');
        $('.content-navigation-menu').append('<h2 class="nav-' + index + '">' + $('h2', this).text() + '</h2>');
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

  function initMultiSelect() {
    $('.multi-select').multiselect({
      enableCaseInsensitiveFiltering: true,
      maxHeight: 310,
      nonSelectedText: VuFind.translate('none_selected'),
      nSelectedText: VuFind.translate('selected'),
      buttonClass: 'form-control'
    });
    // use click events only if there is a multi-select element
    if ($('.multi-select').length) {
      $('.multiselect.dropdown-toggle').click(function onClickDropdownToggle(/*e*/) {
        $(this).siblings('.multiselect-container').toggleClass('show');
      });
      $('html').on('click', function onClickHtml(e) {
        if (!$(e.target).hasClass('multiselect') && !$(e.target).parent().hasClass('multiselect')) {
          $('.multiselect-container.show').removeClass('show');
        }
      });
    }
  }

  function initMobileNarrowSearch() {
    var filterAmount = $('.checkboxFilter input[checked]').length + $('.list-group.filters .list-group-item.active').length;
    if (filterAmount > 0) {
      $('.mobile-navigation .sidebar-navigation .active-filters  .active-filter-count').text(filterAmount);
      $('.mobile-navigation .sidebar-navigation .active-filters').removeClass('hidden');
    }
    $('.mobile-navigation .sidebar-navigation, .sidebar h4').unbind('click').click(function onClickMobileNav(e) {
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
    var modalContent = 0;
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

    $('#modal').on('shown.bs.modal', function onShownModal(/*e*/) {
      $('#hierarchyTree').scroll(function onScrollHierarchyTree() {
        modalContent = $('#hierarchyTree').scrollTop();
        if (modalContent > 1500) {
          $('#modal .back-to-up').removeClass('hidden');
        }
        else {
          $('#modal .back-to-up').addClass('hidden');
        }
      });
      $('.back-to-up').click(function onClickBackToUp() {
        $('#hierarchyTree, #modal').animate({scrollTop: 0 }, 200);
      });
    });
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
    $('.searchForm_lookfor').bind('autocomplete:select', function onAutocompleteSelect() { $('.navbar-form').submit() });

    $('.select-type').click(function onClickSelectType() {
      $('input[name=type]:hidden').val($(this).children().val());
      $('.type-dropdown .dropdown-toggle span').text($(this).text());
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

  function initImageCheck() {
    $('.image-popup-trigger img').each(function setupImagePopup() {
      $(this).one('load', function onLoadImage() {
        // Don't hide anything if we have multiple images
        var navi = $(this).closest('.image-popup-navi');
        if (navi && navi.length > 1) {
          return;
        }
        if (this.naturalWidth && this.naturalWidth === 10 && this.naturalHeight === 10) {
          $(this).parent().addClass('no-image');
          $('.record.large-image-layout').addClass('no-image-layout').removeClass('large-image-layout');
          $('.large-image-sidebar').addClass('visible-xs');
          $('.record-main').addClass('mainbody left');
          var href = $(this).parent().attr('href');
          $(this).parent().attr({'href': href.split('#')[0], 'title': ''});
          $(this).parents('.grid').addClass('no-image');
          $('.rating-stars').addClass('hidden-xs');
        }
      }).each(function loadImage() {
        if (this.complete) {
          $(this).load();
        }
      });
    });
  }

  function initHierarchicalFacet(treeNode, inSidebar) {
    addJSTreeListener(treeNode);
    initFacetTree(treeNode, inSidebar);
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

  function initJumpMenus(_holder) {
    var holder = typeof _holder === 'undefined' ? $('body') : _holder;
    holder.find('select.jumpMenu').unbind('change').change(function onChangeJumpMenu() { $(this).closest('form').submit(); });
    holder.find('select.jumpMenuUrl').unbind('change').change(function onChangeJumpMenuUrl(e) { window.location.href = $(e.target).val(); });
  }

  function initSecondaryLoginField(labels, topClass) {
    $('#login_target').change(function onChangeLoginTarget() {
      var target = $('#login_target').val();
      var field = $('#login_' + (topClass ? topClass + '_' : '') + 'secondary_username');
      if (labels[target] === '') {
        field.val('');
        field.closest('.form-group').hide();
      } else {
        var group = field.closest('.form-group');
        group.find('label').text(labels[target] + ':');
        group.show();
      }
    }).change();
  }

  function initSideFacets() {
    // Load new-style ajax facets
    $('.side-facets-container-ajax')
      .find('div.collapse[data-facet]:not(.in)')
      .on('shown.bs.collapse', function expandFacet() {
        loadAjaxSideFacets();
      });
    loadAjaxSideFacets();

    // Handle any old-style ajax facets
    var $container = $('.side-facets-container');
    if ($container.length === 0) {
      return;
    }
    $container.find('.facet-load-indicator').removeClass('hidden');
    var query = window.location.href.split('?')[1];
    $.getJSON(VuFind.path + '/AJAX/JSON?method=getSideFacets&' + query)
      .done(function onGetSideFacetsDone(response) {
        $container.replaceWith(response.data);
        finna.dateRangeVis.init();
        initToolTips($('.sidebar'));
        initMobileNarrowSearch();
        VuFind.lightbox.bind($('.sidebar'));
        setupFacets();
      })
      .fail(function onGetSideFacetsFail() {
        $container.find('.facet-load-indicator').addClass('hidden');
        $container.find('.facet-load-failed').removeClass('hidden');
      });
  }

  function loadAjaxSideFacets() {
    var $container = $('.side-facets-container-ajax');
    if ($container.length === 0) {
      return;
    }

    var facetList = [];
    var $facets = $container.find('div.collapse.in[data-facet], div.checkbox[data-facet]');
    $facets.each(function addFacet() {
      if (!$(this).data('loaded')) {
        facetList.push($(this).data('facet'));
      }
    });
    if (facetList.length === 0) {
      return;
    }
    var urlParts = window.location.href.split('?');
    var query = urlParts.length > 1 ? urlParts[1] : '';
    var request = {
      method: 'getSideFacets',
      query: query,
      enabledFacets: facetList
    }
    $container.find('.facet-load-indicator').removeClass('hidden');
    $.getJSON(VuFind.path + '/AJAX/JSON?' + query, request)
      .done(function onGetSideFacetsDone(response) {
        $.each(response.data, function initFacet(facet, facetData) {
          var $facetContainer = $container.find('div[data-facet="' + facet + '"]');
          $facetContainer.data('loaded', 'true');
          if (typeof facetData === 'number') {
            $facetContainer.find('.avail-count').text(
              facetData.toString().replace(/\B(?=(\d{3})+\b)/g, VuFind.translate('number_thousands_separator'))
            );
          } else if (typeof facetData === 'string') {
            $facetContainer.html(facetData);
          } else {
            // TODO: this block copied from facets.js, refactor
            var treeNode = $facetContainer.find('.jstree-facet');

            // Enable keyboard navigation also when a screen reader is active
            treeNode.bind('select_node.jstree', function selectNode(event, data) {
              window.location = data.node.data.url;
              event.preventDefault();
              return false;
            });

            addJSTreeListener(treeNode);

            var currentPath = treeNode.data('path');
            var allowExclude = treeNode.data('exclude');
            var excludeTitle = treeNode.data('exclude-title');

            var results = buildFacetNodes(facetData, currentPath, allowExclude, excludeTitle, true);
            treeNode.on('loaded.jstree open_node.jstree', function treeNodeOpen(/*e, data*/) {
              treeNode.find('ul.jstree-container-ul > li.jstree-node').addClass('list-group-item');
            });
            treeNode.jstree({
              'core': {
                'data': results
              }
            });
          }
          $facetContainer.find('.facet-load-indicator').remove();
        });
        finna.dateRangeVis.init();
        initToolTips($('.sidebar'));
        initMobileNarrowSearch();
        VuFind.lightbox.bind($('.sidebar'));
      })
      .fail(function onGetSideFacetsFail() {
        $container.find('.facet-load-indicator').remove();
        $container.find('.facet-load-failed').removeClass('hidden');
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
        $container.html(response.data);
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

  function initBuildingFilter() {
    $('#building_filter').keyup(function onKeyUpFilter() {
      var valThis = this.value.toLowerCase();
      $('#facet_building>ul>li>a>.main').each(function setupBuildingSearch() {
        var text = $(this).text().toLowerCase();
        if (text.indexOf(valThis) !== -1) {
          $(this).parent().parent().show();
        } else {
          $(this).parent().parent().hide();
        }
      });
    });
  }

  function initLoginRedirect() {
    if (!document.addEventListener) {
      return;
    }
    document.addEventListener('VuFind.lightbox.login', function onLightboxLogin(e) {
      if (typeof action !== 'undefined' && action === 'home' && !e.detail.formUrl.match(/catalogLogin/) && !e.detail.formUrl.match(/\Save/) && !e.detail.formUrl.match(/%2[fF]Save/)) {
        window.location.href = VuFind.path + '/MyResearch/Home';
        e.preventDefault();
      }
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
          isResizeBound: 'true'
        });
      });
    }
  }

  function initOrganisationPageLinks() {
    $('.organisation-page-link').not('.done').map(function setupOrganisationPageLinks() {
      $(this).one('inview', function onInViewLink() {
        var holder = $(this);
        var organisation = $(this).data('organisation');
        var organisationName = $(this).data('organisationName');
        getOrganisationPageLink(organisation, organisationName, true, function organisationPageCallback(response) {
          holder.toggleClass('done', true);
          if (response) {
            var data = response[organisation];
            holder.html(data).closest('li.record-organisation').toggleClass('organisation-page-link-visible', true);
          }
        });
      });
    });
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
      params.data.parentName = new String(organisationName);
    }
    $.ajax(params)
      .done(function onGetOrganisationInfoDone(response) {
        callback(response.data.items);
      })
      .fail(function onGetOrganisationInfoFail() {
        callback(false);
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

  function initIframeEmbed(_container) {
    var container = typeof _container === 'undefined' ? $('body') : _container;

    container.find('a[data-embed-iframe]').click(function onClickEmbedLink(e) {
      if (typeof $.magnificPopup.instance !== 'undefined' && $.magnificPopup.instance.isOpen) {
        // Close existing popup (such as image-popup) first without delay so that its
        // state doesn't get confused by the immediate reopening.
        $.magnificPopup.instance.st.removalDelay = 0;
        $.magnificPopup.close();
      }
      $.magnificPopup.open({
        type: 'iframe',
        tClose: VuFind.translate('close'),
        items: {
          src: $(this).attr('href')
        },
        iframe: {
          markup: '<div class="mfp-iframe-scaler">'
            + '<div class="mfp-close"></div>'
            + '<iframe class="mfp-iframe" frameborder="0" allowfullscreen></iframe>' +
            + '</div>',
          patterns: {
            youtube_short: {
              index: 'youtu.be/',
              id: 'youtu.be/',
              src: '//www.youtube.com/embed/%id%?autoplay=1'
            }
          }
        },
        callbacks: {
          open: function onOpen() {
            if (finna.layout.isTouchDevice()) {
              $('.mfp-container .mfp-close, .mfp-container .mfp-arrow-right, .mfp-container .mfp-arrow-left').addClass('touch-device');
            }
          }
        }
      });
      e.preventDefault();
      return false;
    });
  }

  function initVideoPopup(_container) {
    var container = typeof _container === 'undefined' ? $('body') : _container;

    container.find('a[data-embed-video]').click(function onClickVideoLink(e) {
      var videoSources = $(this).data('videoSources');
      var posterUrl = $(this).data('posterUrl');
      $.magnificPopup.open({
        type: 'inline',
        items: {
          src: "<div class='video-popup'><video id='video-player' class='video-js vjs-big-play-centered' controls></video></div>"
        },
        callbacks: {
          open: function onOpen() {
            var player = videojs('video-player');

            videojs.Html5DashJS.hook(
              'beforeinitialize',
              function onBeforeInit(videoJs, mediaPlayer) {
                mediaPlayer.getDebug().setLogToBrowserConsole(false);
              }
            );

            player.ready(function onReady() {
              this.hotkeys({
                enableVolumeScroll: false,
                enableModifiersForNumbers: false
              });
            });

            player.src(videoSources);
            player.poster(posterUrl);
            player.load();
          },
          close: function onClose() {
            videojs('video-player').dispose();
          }
        }
      });
      e.preventDefault();
      return false;
    });
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
    var filterAmount = $('.filters-bar .filter-value').length;
    if (filterAmount > 0) {
      $('.filters-toggle .active-filter-count').text(' (' + filterAmount + ')');
    }

    $('.filters-toggle').click(function filterToggleClicked(){
      if ($('.filters-bar').hasClass('hidden')) {
        $('.filters-bar').removeClass('hidden');
        $('.filters-toggle .fa-arrow-down').removeClass('fa-arrow-down').addClass('fa-arrow-up');
      } else {
        $('.filters-bar').addClass('hidden');
        $('.filters-toggle .fa-arrow-up').removeClass('fa-arrow-up').addClass('fa-arrow-down');

      }
    });
  }

  var my = {
    getOrganisationPageLink: getOrganisationPageLink,
    isTouchDevice: isTouchDevice,
    initMap: initMap,
    initTruncate: initTruncate,
    initLocationService: initLocationService,
    initHierarchicalFacet: initHierarchicalFacet,
    initJumpMenus: initJumpMenus,
    initMobileNarrowSearch: initMobileNarrowSearch,
    initOrganisationPageLinks: initOrganisationPageLinks,
    initSecondaryLoginField: initSecondaryLoginField,
    initIframeEmbed: initIframeEmbed,
    initVideoPopup: initVideoPopup,
    init: function init() {
      initScrollRecord();
      initJumpMenus();
      initAnchorNavigationLinks();
      initFixFooter();
      initTruncatedRecordImageNavi();
      initTruncate();
      initContentNavigation();
      initRecordSwipe();
      initMultiSelect();
      initMobileNarrowSearch();
      initCheckboxClicks();
      initToolTips();
      initResizeListener();
      initScrollLinks();
      initSearchboxFunctions();
      initCondensedList();
      if (typeof checkSaveStatuses !== 'undefined') { checkSaveStatuses(); }
      initTouchDeviceGallery();
      initImageCheck();
      initSideFacets();
      initPiwikPopularSearches();
      initAutoScrollTouch();
      initIpadCheck();
      initLoginRedirect();
      initLoadMasonry();
      initOrganisationInfoWidgets();
      initOrganisationPageLinks();
      initIframeEmbed();
      initVideoPopup();
      initKeyboardNavigation();
      initPriorityNav();
      initFiltersToggle();
    }
  };

  return my;
})();

