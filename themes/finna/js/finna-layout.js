finna.layout = (function() {
    var refreshPage = false;

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
        return !!('ontouchstart' in window)
            || !!('onmsgesturechange' in window); // IE10
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
        var detectHeight = $(window).height() - $('body').height();
        if (detectHeight > 0) {
            var expandedFooter = $('footer').height() + detectHeight;
            $('footer').height(expandedFooter);
        }
    };

    var initHideDetails = function() {
      if ($(".record-information").height() > 350 && $(".show-details-button")[0]) {
        $(".record-information .record-details-more").addClass('hidden');
        $(".record-information .show-details-button").removeClass('hidden');
        $(".description").addClass('too-long');
      }
      $('.show-details-button').click (function() {
        $(".record-information .record-details-more").toggleClass('hidden');
        $('.description .more-link.wide').click();
        $(this).toggleClass('hidden');
        $(".hide-details-button").toggleClass("hidden");
      });
      $('.hide-details-button').click (function() {
        $(".record-information .record-details-more").toggleClass('hidden');
        $('.description .less-link.wide').click();
        $(this).toggleClass('hidden');
        $(".show-details-button").toggleClass("hidden");
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
      holder.find(".truncate-field").not('.truncate-done').each(function(index) {
        $(this).addClass('truncate-done');
        // check that truncate-field has children, where we can count line-height
        if ($(this).children().length > 0) {
          var rowCount = 3;
          if ($(this).data("rows")) {
            rowCount = $(this).data("rows");
          }

          if (typeof($(this).data('row-height')) !== 'undefined') {
              rowHeight[index] = $(this).data('row-height');
          } else {
            if ($(this).children().first().is("div")) {
              rowHeight[index] = parseFloat($(this).children().first().height());
            }
            else {
              rowHeight[index] = parseFloat($(this).children().first().css('line-height').replace('px', ''));
            }
          }

          // get the line-height of first element to determine each text line height
          truncation[index] = rowHeight[index] * rowCount;
          // truncate only if there's more than one line to hide
          if ($(this).height() > (truncation[index] + rowHeight[index] + 1)) {
            $(this).css('height', truncation[index] - 1 + 'px');
            if ($( this ).hasClass("wide")) { // generate different truncate styles according to class
              $(this).after("<div class='more-link wide'><i class='fa fa-handle-open'></i></div><div class='less-link wide'> <i class='fa fa-handle-close'></i></div>");
            }
            else {
              $(this).after("<div class='more-link'>"+vufindString.show_more+" <i class='fa fa-arrow-down'></i></div><div class='less-link'>"+vufindString.show_less+" <i class='fa fa-arrow-up'></i></div>");
            }
            $('.less-link').hide();

            var self = $(this);

            $(this).nextAll('.more-link').first().click(function( event ) {
              $(this).hide();
              $(this).next('.less-link').show();
              $(this).prev('.truncate-field').css('height','auto');
              notifyTruncateChange(self);
            });

            $(this).nextAll('.less-link').first().click(function( event ) {
              $(this).hide();
              $(this).prev('.more-link').show();
              $(this).prevAll('.truncate-field').first().css('height', truncation[index]-1+'px');
              notifyTruncateChange(self);
            });
          }
          notifyTruncateChange($(this));
        }
        $(this).trigger('truncate-done', [$(this)]);
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
        $(".template-dir-record #record").swipe( {
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
            nonSelectedText: vufindString.none_selected,
            nSelectedText: vufindString.selected,
            buttonClass: "form-control",
        });
        $('.multiselect.dropdown-toggle').click(function(e) {
            $(this).siblings('.multiselect-container').toggleClass('show');
        });
        $('html').on('click', function(e) {
            if ($(e.target).parent().attr('class') != 'multiselect-selected-text' && !$(e.target).parent().hasClass('multiselect')) {
                $('.multiselect-container.show').removeClass('show');
            }
        });
    };

    var initMobileNarrowSearch = function() {
        var filterAmount = $('.checkboxFilter input[checked]').length+$('.list-group.filters .list-group-item.active').length;
        if (filterAmount > 0) {
          $('.mobile-navigation .sidebar-navigation .active-filters').removeClass('hidden');
          $('.mobile-navigation .sidebar-navigation .active-filters').append(' '+filterAmount);
        }
        $('.mobile-navigation .sidebar-navigation, .sidebar h4').click(function(e) {
            if ($(e.target).attr('class') != 'fa fa-info-big') {
              $('.sidebar').toggleClass('open');
            }
            $('.mobile-navigation .sidebar-navigation i').toggleClass('fa-arrow-down');
            $('body').toggleClass('prevent-scroll');
        });
        $('.mobile-navigation .sidebar-navigation .active-filters').click(function() {
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
      var scroll = $(window).scrollTop();
      var modalContent = 0;
      $(window).scroll(function (event) {
        scroll = $(window).scrollTop();
        if (scroll > 2000) {
           $('.template-dir-record .back-to-up').removeClass('hidden');
        }
        else {
          $('.template-dir-record .back-to-up').addClass('hidden');
        }
      });

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

      $('.template-dir-record .back-to-up').click(function() {
        $('html, body').animate({scrollTop: $('#hierarchyTreeHolder').offset().top-70}, 200);
      });
    };

    var initSearchboxFunctions = function() {
      if ($('.navbar-form .checkbox')[0]) {
        $('.tt-dropdown-menu').addClass('checkbox-active');
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
        form.find('.autocomplete').typeahead('val', '');
        form.find('.clear-button').addClass('hidden');
        form.find('.searchForm_lookfor').focus();
      });
      $('.autocomplete').on('typeahead:selected', function () {
        $('.navbar-form').submit();
      });
      
      $('.select-type').click(function() {
        $('input[name=type]:hidden').val($(this).children().val());
        $('.type-dropdown .dropdown-toggle span').text($(this).text());
      });
      
    };

    var initToolTips = function () {
      $('[data-toggle="tooltip"]').tooltip({trigger:'click', viewport: '.container'});
      // prevent link opening if tooltip is placed inside link element
      $('[data-toggle="tooltip"] > i').click(function(event) {
        event.preventDefault();
      });
      // close tooltip if user clicks anything else than tooltip button
      $('html').on('click', function(e) {
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

    var initSaveRecordLinks = function(holder) {
        if (typeof(holder) == "undefined") {
            holder = $("body");
        }
        holder.find('.save-record').click(function() {
            var parts = this.href.split('/');
            var id = $(this).attr('id');
            if (!id) {
                id = $(this).data('id');
            }
            if (!id) {
                return;
            }
            return finna.layout.lightbox.get(parts[parts.length-3],'Save',{id:id});
        });
    };

    var checkSaveStatuses = function(holder) {
        // This function may be called directly or via redirection in finna.js
        if (typeof(holder) == "undefined") {
            holder = $("body");
        }

        var data = $.map(holder.find('.result,.record'), function(i) {
            if($(i).find('.hiddenId').length == 0 || $(i).find('.hiddenSource').length == 0) {
                return false;
            }
            return {'id':$(i).find('.hiddenId').val(), 'source':$(i).find('.hiddenSource')[0].value};
        });

        if (data.length) {
            var ids = [];
            var srcs = [];
            for (var i = 0; i < data.length; i++) {
                ids[i] = data[i].id;
                srcs[i] = data[i].source;
            }
            $.ajax({
                dataType: 'json',
                url: path + '/AJAX/JSON?method=getSaveStatuses',
                data: {id:ids, 'source':srcs},
                success: function(response) {
                    if(response.status == 'OK') {
                        holder.find('.savedLists > ul').empty();
                        $.each(response.data, function(i, result) {
                            var $container = holder.find('input[value="' + result.record_id + '"]').closest(".result");
                            if ($container.length) {
                                $container = $container.find(".savedLists");
                            }
                            if ($container.length == 0) { // Record view
                                $container = $('#savedLists');
                            }
                            var $ul = $container.children('ul:first');
                            if ($ul.length == 0) {
                                $container.append('<ul></ul>');
                                $ul = $container.children('ul:first');
                            }
                            var html = '<li><a href="' + path + '/MyResearch/MyList/' + result.list_id + '">'
                                + result.list_title + '</a></li>';
                            $ul.append(html);
                            $container.removeClass('hidden');
                        });
                    }
                }
            });
            initSaveRecordLinks(holder);
        }
    };

    var initAuthorizationNotification = function(holder) {
        if (typeof(holder) == "undefined") {
            holder = $("body");
        }
        holder.find(".authorization-notification .modal-link").click(function() {
            refreshPage = true;
            return Lightbox.get('MyResearch','UserLogin');
        });
    };

    var initLightbox = function(holder) {
        if (typeof(holder) == "undefined") {
            // This must be called with a holder. Defaults are done in lightbox.js.
            return;
        }
        // This part copied from lightbox.js. TODO: refactor
        /**
         * If a link with the class .modal-link triggers the lightbox,
         * look for a title="" to use as our lightbox title.
         */
        holder.find('.modal-link,.help-link').click(function() {
            var title = $(this).attr('title');
            if(typeof title === "undefined") {
                title = $(this).html();
            }
            $('#modal .modal-title').html(title);
            Lightbox.titleSet = true;
        });
    };

    var isPageRefreshNeeded = function() {
        return refreshPage;
    };

    var updateLoginName = function() {
        $.ajax({
            dataType: 'json',
            url: path + '/AJAX/JSON?method=getUserDisplayName',
            success: function (response) {
                if (response.status == 'OK') {
                    $('.logoutOptions .username').text(response.data);
                }
            }
        });
    }
    
    var initTouchDeviceGallery = function () {
        if ($('.result-view-grid')[0] != null && isTouchDevice()) {
            $('.result-view-grid').addClass('touch-device');
        }
    }
    var initImageCheck = function() {
        $(".image-popup-trigger img").each(function() {
            $(this).one("load",function() {
                if (this.naturalWidth && this.naturalWidth == 10 && this.naturalHeight == 10) {
                    $(this).parent().addClass('no-image');
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
    }
    
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
    }
    
    var my = {
        isPageRefreshNeeded: isPageRefreshNeeded,
        isTouchDevice: isTouchDevice,
        initAuthorizationNotification: initAuthorizationNotification,
        initTruncate: initTruncate,
        lightbox: Lightbox,
        checkSaveStatuses: checkSaveStatuses,
        initSaveRecordLinks: initSaveRecordLinks,
        initLightbox: initLightbox,
        updateLoginName: updateLoginName,
        initHierarchicalFacet: initHierarchicalFacet,
        initJumpMenus: initJumpMenus,
        initMobileNarrowSearch: initMobileNarrowSearch,
        initSecondaryLoginField: initSecondaryLoginField, 
        init: function() {
            initJumpMenus();
            initAnchorNavigationLinks();
            initFixFooter();
            initHideDetails();
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
            checkSaveStatuses();
            initAuthorizationNotification();
            initTouchDeviceGallery();
            initImageCheck();
        }
    };

    return my;
})(finna);

