/*global VuFind,checkSaveStatuses,action*/
finna.layout = (function() {
    var _fixFooterTimeout = null;

    var initMap = function(map) {
        // Add zoom control with translated tooltips
        L.control.zoom({
            position:'topleft',
            zoomInTitle: VuFind.translate('map_zoom_in'),
            zoomOutTitle: VuFind.translate('map_zoom_out')
        }).addTo(map);

        // Enable mouseWheel zoom on click
        map.once('focus', function() {
            map.scrollWheelZoom.enable();
        });
        map.scrollWheelZoom.disable();
    };

    var initResizeListener = function() {
        var intervalId = false;
        $(window).on("resize", function(e) {
            clearTimeout(intervalId);
            intervalId = setTimeout(function() {
                var data = {
                    w: $(window).width(),
                    h: $(window).height()
                };
                $(window).trigger("resize.screen.finna", data);
            }, 100);
        });
    };

    var isTouchDevice = function() {
        return (('ontouchstart' in window)
              || (navigator.maxTouchPoints > 0)
              || (navigator.msMaxTouchPoints > 0)); // IE10, IE11, Edge
    };

    var detectIe = function(){
        var undef,
            v = 3,
            div = document.createElement('div'),
            all = div.getElementsByTagName('i');
        while (
            div.innerHTML = '<!--[if gt IE ' + (++v) + ']><i></i><![endif]-->',
            all[0]
        );
        return v > 4 ? v : undef;
    };

    // Append current anchor (location.hash) to selected links
    // in order to preserve the anchor when the link is clicked.
    // This is used in top header language links.
    var initAnchorNavigationLinks = function() {
        $('a.preserve-anchor').each(function() {
            var hash = location.hash;
            if (hash.length == 0) {
                return;
            }
            $(this).attr('href', $(this).attr('href') + hash);
        });
    };

    var initFixFooter = function() {
        $(window).resize(function(e) {
            if (!_fixFooterTimeout) {
                _fixFooterTimeout = setTimeout(function() {
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
    };

    var initLocationService = function(holder) {
        if (typeof holder == 'undefined') {
            holder = $(document);
        }
        var closeModalCallback = function(modal) {
            modal.removeClass('location-service location-service-qrcode');
            modal.find('.modal-dialog').removeClass('modal-lg');
        };

        holder.find('a.location-service.location-service-modal').click(function(e) {
            var modal = $('#modal');
            modal.addClass('location-service');
            modal.find('.modal-dialog').addClass('modal-lg');

            $('#modal').one('hidden.bs.modal', function() {
                closeModalCallback($(this));
            });
            modal.find('.modal-body').load($(this).data('lightbox-href') + '&layout=lightbox');
            modal.modal();
            return false;
        });
    };

    var initTruncate = function(holder) {
      if (typeof holder === 'undefined') {
          holder = $(document);
      }

      var notifyTruncateChange = function(field) {
          field.find('.truncate-change span').each(function(ind, e) {
              var visible = $(e).position().top <= field.height();
              $(e).trigger('truncate-change', [visible]);
          });
      };

      var truncation = [];
      var rowHeight = [];
      holder.find('.truncate-field').not('.truncate-done').each(function(index) {
        var self = $(this);
        self.addClass('truncate-done');
        // check that truncate-field has children, where we can count line-height
        if (self.children().length > 0) {
          var rowCount = 3;
          if (self.data('rows')) {
            rowCount = self.data('rows');
          }

          if (typeof(self.data('row-height')) !== 'undefined') {
              rowHeight[index] = self.data('row-height');
          } else {
            if (self.children().first().is('div')) {
              rowHeight[index] = parseFloat(self.children().first().height());
            }
            else {
              rowHeight[index] = parseFloat(self.children().first().css('line-height').replace('px', ''));
            }
          }

          // get the line-height of first element to determine each text line height
          truncation[index] = rowHeight[index] * rowCount;
          // truncate only if there's more than one line to hide
          if (self.height() > (truncation[index] + rowHeight[index] + 1)) {
            self.css('height', truncation[index] - 1 + 'px');
            if (self.hasClass('wide')) { // generate different truncate styles according to class
              self.after('<div class="more-link wide"><i class="fa fa-handle-open"></i></div><div class="less-link wide"> <i class="fa fa-handle-close"></i></div>');
            }
            else {
              self.after('<div class="more-link">' + VuFind.translate('show_more') + ' <i class="fa fa-arrow-down"></i></div><div class="less-link">' + VuFind.translate('show_less') + ' <i class="fa fa-arrow-up"></i></div>');
            }
            $('.less-link').hide();

            self.nextAll('.more-link').first().click(function(event) {
              $(this).hide();
              $(this).next('.less-link').show();
              $(this).prev('.truncate-field').css('height', 'auto');
              notifyTruncateChange(self);
            });

            self.nextAll('.less-link').first().click(function(event) {
              $(this).hide();
              $(this).prev('.more-link').show();
              $(this).prevAll('.truncate-field').first().css('height', truncation[index]-1+'px');
              notifyTruncateChange(self);
            });
            self.addClass('truncated');
          }
          notifyTruncateChange(self);
        }
        self.trigger('truncate-done', [self]);
      });
    };

    var initTruncatedRecordImageNavi = function() {
        var displayTruncatedImage = function(placeholder) {
            var img = $('<img/>');
            img.append($('<i class="fa fa-spinner fa-spin"/>')).one('load', function() {
                $(this).empty();
            })
            img.attr('src', placeholder.data('src'));
            img.attr('alt', '');
            placeholder.parent().removeClass('truncate-change');
            placeholder.replaceWith(img);
        };

        // Load truncated record images lazily when parent container is opened
        $('.recordcovers .truncate-change span').each(function() {
            $(this).bind('truncate-change', function(e, visible) {
                if (visible) {
                    $(this).unbind('truncate-change');
                    // Postpone loading until the image placeholder is scrolled into viewport
                    $(this).unbind('inview').one('inview', function() {
                        displayTruncatedImage($(this));
                    });
                }
            });
        });

        // Add image count to 'more-link' label when truncate-field has been inited.
        $('.recordcovers.truncate-field').bind('truncate-done', function(e, field) {
            moreLink = field.nextAll('.more-link');
            if (moreLink.length) {
                var childrenCnt = field.find('a').not('.hide').length;
                var cnt = $('<span class="cnt">(' + childrenCnt + ')</span>');
                cnt.insertAfter(moreLink.first().children().first());
            }
        });
    };

    var initContentNavigation = function() {
      if ($('.content-navigation-menu')[0]) {
        $('.content-section').each(function(index) {
          var link = '#'+$(this).attr('id');
          $('.content-navigation-menu').append('<h2 class="nav-'+index+'">'+$('h2', this).text()+'</h2>');
          $('.content-navigation-menu h2.nav-'+index).click(function () {
            $('body, html').animate({
              scrollTop: $(link).offset().top-5
            }, 350);
          });
        });

        var menuPosition = $('.content-navigation-menu').offset().top;
        // fixed menu & prevent footer overlap
        $( window ).scroll(function() {
          if ($(window).scrollTop() > menuPosition) {
            $('.content-navigation-menu').addClass('attached');
            if ($(window).scrollTop()+$('.content-navigation-menu').outerHeight(true) > $('footer').offset().top) {
              $('.content-navigation-menu').css({'bottom': $('footer').height()+20+'px', 'top': 'auto'});
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
    };

    var initRecordSwipe = function () {
      if ($('#view-pager').length && isTouchDevice()) {
        $('section.main').append("<div class='swipe-arrow-navigation arrow-navigation-left'><i class='fa fa-arrow-left'></i></div>");
        $('section.main').append("<div class='swipe-arrow-navigation arrow-navigation-right'><i class='fa fa-arrow-right'></i></div>");
        $('.swipe-arrow-navigation').hide();
        $(".template-dir-record .record").swipe( {
        allowPageScroll:"vertical",
        swipeRight:function(event, phase, direction, distance, duration) {
          if ($('#view-pager .pager-previous-record a').length) {
            var prevRecordUrl =  $('#view-pager .pager-previous-record a').attr('href');
            window.location.href = prevRecordUrl;
          }
        },
        swipeLeft:function(event, direction, distance, duration) {
          if ($('#view-pager .pager-next-record a').length) {
            var nextRecordUrl = $('#view-pager .pager-next-record a').attr('href');
            window.location.href = nextRecordUrl;
          }
        },
        swipeStatus:function(event, phase, direction, distance, duration, fingers) {
              if ((phase != "cancel") && (phase == "move") && (direction == "right") && (distance > 75) && ($('#view-pager .pager-previous-record a').length)) {
                $('.arrow-navigation-left').show('fast');
              }
              if ((phase != "cancel") && (phase == "move") && (direction == "left") && (distance > 75) && ($('#view-pager .pager-next-record a').length)) {
                 $('.arrow-navigation-right').show('fast');
              }
              if (phase == "cancel") {
                $('.swipe-arrow-navigation').hide('fast');
              }
        },
        //Default is 75px, set to 0 for demo so any distance triggers swipe
        threshold: 125,
        cancelThreshold:20,
        });
      }
    };

    var initMultiSelect = function() {
        $('.multi-select').multiselect({
            enableCaseInsensitiveFiltering: true,
            maxHeight: 310,
            nonSelectedText: VuFind.translate('none_selected'),
            nSelectedText: VuFind.translate('selected'),
            buttonClass: "form-control",
        });
        // use click events only if there is a multi-select element
        if ($('.multi-select').length) {
          $('.multiselect.dropdown-toggle').click(function(e) {
              $(this).siblings('.multiselect-container').toggleClass('show');
          });
          $('html').on('click', function(e) {
              if (!$(e.target).hasClass('multiselect') && !$(e.target).parent().hasClass('multiselect')) {
                  $('.multiselect-container.show').removeClass('show');
              }
          });
        }
    };

    var initMobileNarrowSearch = function() {
        var filterAmount = $('.checkboxFilter input[checked]').length + $('.list-group.filters .list-group-item.active').length;
        if (filterAmount > 0) {
          $('.mobile-navigation .sidebar-navigation .active-filters  .active-filter-count').text(filterAmount);
          $('.mobile-navigation .sidebar-navigation .active-filters').removeClass('hidden');
        }
        $('.mobile-navigation .sidebar-navigation, .sidebar h4').unbind('click').click(function(e) {
            if ($(e.target).attr('class') != 'fa fa-info-big') {
              $('.sidebar').toggleClass('open');
            }
            $('.mobile-navigation .sidebar-navigation i').toggleClass('fa-arrow-down');
            $('body').toggleClass('prevent-scroll');
        });
        $('.mobile-navigation .sidebar-navigation .active-filters').unbind('click').click(function() {
            $('.sidebar').scrollTop(0);
        });
    };

    var initCheckboxClicks = function() {
      $('.checkboxFilter:not(.mylist-select-all) .checkbox input').click(function() {
        $(this).closest('.checkbox').toggleClass('checked');
        var nonChecked = true;
        $('.myresearch-row .checkboxFilter .checkbox').each(function() {
            if ($(this).hasClass('checked')) {
              $('.mylist-functions button, .mylist-functions select').removeAttr("disabled");
              $('.mylist-functions .jump-menu-style').removeClass('disabled');
              nonChecked = false;
            }
        });
        if (nonChecked) {
          $('.mylist-functions button, .mylist-functions select').attr("disabled", true);
          $('.mylist-functions .jump-menu-style').addClass('disabled');
        }
      });

        var myListSelectAll = $(".checkboxFilter.mylist-select-all");
        var myListJumpMenu = $(".mylist-functions .jump-menu-style");
        var myListFunctions = $(".mylist-functions button, .mylist-functions select");
        myListSelectAll.find(".checkbox .checkbox-select-all").click(function() {
            var checkboxes = $(".myresearch-row .checkboxFilter .checkbox, .checkboxFilter.mylist-select-all .checkbox");
            if ($(this).closest(".checkbox").hasClass("checked")) {
                var isEverythingChecked = !$(".myresearch-row .checkboxFilter .checkbox").not(".checked").length;
                checkboxes.toggleClass("checked", !isEverythingChecked);
                myListJumpMenu.toggleClass("disabled", isEverythingChecked);
                myListFunctions.attr("disabled", isEverythingChecked);
            } else {
                checkboxes.toggleClass("checked", true);
                myListJumpMenu.toggleClass("disabled", false);
                myListFunctions.attr("disabled", false);
            }
        });
    };

    var initScrollLinks = function() {
      $('.library-link').click(function() {
        $('html, body').animate({
          scrollTop: $('.recordProvidedBy').offset().top
        }, 500);
      });
      var modalContent = 0;
      if ($('.floating-feedback-btn').length) {
        var feedbackBtnOffset = $('.floating-feedback-btn').offset().top;
        $(window).scroll(function (event) {
          scroll = $(window).scrollTop();
          if (scroll > feedbackBtnOffset) {
            $('.floating-feedback-btn').addClass('fixed');
          }
          else {
            $('.floating-feedback-btn').removeClass('fixed');
          }
        });
      }
      if ($('.template-dir-record .back-to-up').length) {
        $(window).scroll(function (event) {
          scroll = $(window).scrollTop();
          if (scroll > 2000) {
            $('.template-dir-record .back-to-up').removeClass('hidden');
          }
          else {
            $('.template-dir-record .back-to-up').addClass('hidden');
          }
        });
      }

      $( "#modal" ).on('shown.bs.modal', function (e) {
        $('#hierarchyTree').scroll(function () {
          modalContent = $('#hierarchyTree').scrollTop();
          if (modalContent > 1500) {
            $('#modal .back-to-up').removeClass('hidden');
          }
          else {
            $('#modal .back-to-up').addClass('hidden');
          }
        });
        $('.back-to-up').click(function() {
            $('#hierarchyTree, #modal').animate({scrollTop: 0 }, 200);
        });
      });
    };

    var initSearchboxFunctions = function() {
      if ($('.navbar-form .checkbox')[0]) {
        $('.autocomplete-results').addClass('checkbox-active');
      }
      $('.searchForm_lookfor').on('input', function() {
        var form = $(this).closest('.searchForm');
        if ($(this).val() != '' ) {
          form.find('.clear-button').removeClass('hidden');
        } else {
          form.find('.clear-button').addClass('hidden');
        }
      });

      $('.clear-button').click(function() {
        var form = $(this).closest('.searchForm');
        form.find('.searchForm_lookfor').val('');
        form.find('.clear-button').addClass('hidden');
        form.find('.searchForm_lookfor').focus();
      });
      $('.searchForm_lookfor').bind('autocomplete:select', function() { $('.navbar-form').submit() });

      $('.select-type').click(function() {
        $('input[name=type]:hidden').val($(this).children().val());
        $('.type-dropdown .dropdown-toggle span').text($(this).text());
      });

    };

    var initToolTips = function (holder) {
      if (typeof holder === 'undefined') {
          holder = $(document);
      }

      holder.find('[data-toggle="tooltip"]').tooltip({trigger: 'click', viewport: '.container'});
      // prevent link opening if tooltip is placed inside link element
      holder.find('[data-toggle="tooltip"] > i').click(function(event) {
        event.preventDefault();
      });
      // close tooltip if user clicks anything else than tooltip button
      $('html').click(function(e) {
        if (typeof $(e.target).parent().data('original-title') == 'undefined' && typeof $(e.target).data('original-title') == 'undefined') {
          $('[data-toggle="tooltip"]').tooltip('hide');
        }
      });
    };

    var initCondensedList = function () {
        $('.condensed-collapse-toggle').click(function(event) {
            if ((event.target.nodeName) != 'A' && (event.target.nodeName) != 'MARK') {
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
    };

    var initTouchDeviceGallery = function () {
        if ($('.result-view-grid')[0] != null && isTouchDevice()) {
            $('.result-view-grid').addClass('touch-device');
        }
    };

    var initImageCheck = function() {
        $('.image-popup-trigger img').each(function() {
            $(this).one('load',function() {
                // Don't hide anything if we have multiple images
                var navi = this.closest('.image-popup-navi');
                if (navi && navi.length > 1) {
                    return;
                }
                if (this.naturalWidth && this.naturalWidth == 10 && this.naturalHeight == 10) {
                    $(this).parent().addClass('no-image');
                    $(this).parents('.grid').addClass('no-image');
                    $('.rating-stars').addClass('hidden-xs');
                    $(this).parents('.record-image-container').find('.image-text-container').addClass('hidden');
                }
            }).each(function() {
                if (this.complete) {
                    $(this).load();
                }
            });
        });
    };

    var initHierarchicalFacet = function(treeNode, inSidebar) {
        treeNode.bind('ready.jstree', function() {
            var tree = $(this);
            // if hierarchical facet contains 2 or less top level items, it is opened by default
            if (tree.find('ul > li').length <= 2) {
                tree.find('ul > li.jstree-node.jstree-closed > i.jstree-ocl').each(function() {
                    tree.jstree('open_node', this, null, false);
                });
            }
            // // show filter if 15+ organisations
            if (tree.parent().parent().attr('id') == 'side-panel-building' && tree.find('ul.jstree-container-ul > li').length > 15) {
               $(this).prepend('<div class="building-filter"><label for="building_filter" class="sr-only">'+VuFind.translate('Organisation')+'</label><input class="form-control" id="building_filter" placeholder="'+VuFind.translate('Organisation')+'..."></input></div>');
               initBuildingFilter();
            }
            // open facet if it has children and it is selected
            $(tree.find('.jstree-node.active.jstree-closed')).each(function() {
                tree.jstree('open_node', this, null, false);
            });
        });
        initFacetTree(treeNode, inSidebar);
    };

    var initJumpMenus = function(holder) {
        if (typeof(holder) == "undefined") {
            holder = $("body");
        }
        holder.find('select.jumpMenu').unbind('change').change(function() { $(this).closest('form').submit(); });
        holder.find('select.jumpMenuUrl').unbind('change').change(function(e) { window.location.href = $(e.target).val(); });
    };

    var initSecondaryLoginField = function(labels, topClass) {
        $('#login_target').change(function() {
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
    };

    var initSideFacets = function() {
        var $container = $('.side-facets-container');
        if ($container.length === 0) {
            return;
        }
        $container.find('.facet-load-indicator').removeClass('hidden');
        var query = window.location.href.split('?')[1];
        $.getJSON(VuFind.path + '/AJAX/JSON?method=getSideFacets&' + query)
        .done(function(response) {
            $container.replaceWith(response.data);
            finna.dateRangeVis.init();
            initToolTips($('.sidebar'));
            initMobileNarrowSearch();
            VuFind.lightbox.bind($('.sidebar'));
            setupFacets();
        })
        .fail(function() {
            $container.find('.facet-load-indicator').addClass('hidden');
            $container.find('.facet-load-failed').removeClass('hidden');
        });
    };

    var initPiwikPopularSearches = function() {
        var $container = $('.piwik-popular-searches');
        if ($container.length === 0) {
            return;
        }
        $container.find('.load-indicator').removeClass('hidden');
        $.getJSON(VuFind.path + '/AJAX/JSON?method=getPiwikPopularSearches')
        .done(function(response) {
            $container.html(response.data);
        })
        .fail(function() {
            $container.find('.load-indicator').addClass('hidden');
            $container.find('.load-failed').removeClass('hidden');
        });
    };

    var initAutoScrollTouch = function() {
      if (!navigator.userAgent.match(/iemobile/i) && isTouchDevice() && $(window).width() < 1025) {
        $( ".search-query" ).click(function() {
          $('html, body').animate({
            scrollTop: $(this).offset().top-5
          }, 200);
        });
      };
    };

    var initIpadCheck = function() {
      if (navigator.userAgent.match(/iPad/i)) {
        if (navigator.userAgent.match(/OS 6_\d(_\d) like Mac OS X/i)) {
          $('body').addClass('ipad-six');
        }
      }
    };

    var initScrollRecord = function() {
        if (!$('section.main').is('.template-name-search, .template-name-results')) {
            return;
        }

        var target = null;
        var identifier = decodeURIComponent(window.location.hash);
        if (identifier === "") {
            // Scroll to search box
            if ($(window).height() < 960 && $(window).scrollTop() === 0) {
                target = $('.nav.searchbox');
            }
        } else {
            // Scroll to record
            var result = $('.hiddenId[value="' + identifier.substr(1) + '"]');
            if (result.length) {
                target = result.closest('.result');
            }
        }
        if (target) {
            $('html,body').animate({scrollTop: target.offset().top}, 100);
        }
    };

    var initBuildingFilter = function() {
      $('#building_filter').keyup(function () {
        var valThis = this.value.toLowerCase();
        $('#facet_building>ul>li>a>.main').each(function () {
            var text  = $(this).text().toLowerCase();
            if (text.indexOf(valThis) != -1) {
              $(this).parent().parent().show();
            } else {
              $(this).parent().parent().hide();
            }
        });
      });
    };

    var initLoginRedirect = function() {
      if (!document.addEventListener) {
        return;
      }
      document.addEventListener('VuFind.lightbox.login', function(e) {
        if (typeof action !== 'undefined' && action == 'home' && !e.detail.formUrl.match(/catalogLogin/) && !e.detail.formUrl.match(/\Save/) && !e.detail.formUrl.match(/%2[fF]Save/)) {
          window.location.href = VuFind.path + '/MyResearch/Home';
          e.preventDefault();
        }
      });
    };

    var initLoadMasonry = function() {
      var ie = detectIe();
      // do not execute on ie8 or lower as they are not supported by masonry
      if (ie > 8 || ie == null) {
        $('.result-view-grid .masonry-wrapper').waitForImages(function() {
          // init Masonry after all images have loaded
          $('.result-view-grid .masonry-wrapper').masonry({
            fitWidth: false,
            itemSelector: '.result.grid',
            columnWidth: '.result.grid',
            isResizeBound: 'true',
          });
        });
      }
    };

    var initOrganisationPageLinks = function() {
        $('.organisation-page-link').not('.done').map(function() {
            $(this).one('inview', function() {
                var holder = $(this);
                var organisation = $(this).data('organisation');
                var organisationName = $(this).data('organisationName');
                getOrganisationPageLink(organisation, organisationName, true, function(response) {
                    holder.toggleClass('done', true);
                    if (response) {
                        var data = response[organisation];
                        holder.html(data).closest('li.record-organisation').toggleClass('organisation-page-link-visible', true);
                    }
                });
            });
        });
    };

    var getOrganisationPageLink = function(organisation, organisationName, link, callback) {
        var url = VuFind.path + '/AJAX/JSON?method=getOrganisationInfo';
        url += '&params[action]=lookup&link=' + (link ? '1' : '0') + '&parent=' + organisation;
        if (organisationName) {
           url += '&parentName=' + organisationName;
        }
        $.getJSON(url)
            .done(function(response) {
                callback(response.data.items);
            })
            .fail(function() {
            });
        return callback(false);
    };

    var initOrganisationInfoWidgets = function() {
        $('.organisation-info[data-init="1"]').map(function() {
            var service = finna.organisationInfo();
            var widget = finna.organisationInfoWidget();
            widget.init($(this), service);
            widget.loadOrganisationList();
        });
    };

    var initIframeEmbed = function(container) {
        if (typeof(container) == 'undefined') {
            container = $('body');
        }
        container.find('a[data-embed-iframe]').click(function(e) {
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
                        + '<iframe class="mfp-iframe" frameborder="0" allowfullscreen></iframe>'+
                        + '</div>'
                },
                callbacks: {
                    open: function() {
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
        init: function() {
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
        }
    };

    return my;
})(finna);

