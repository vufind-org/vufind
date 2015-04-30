finna.layout = (function() {

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
      }
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
              rowHeight[index] = parseFloat($(this).children().first().css('line-height').replace('px', ''));
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
    
    var initOpenUrlLinks = function() {
        var links = $('a.openUrlEmbed');
        links.each(function(ind, e) {
            $(e).one('inview', function() {
                $(this).click();
            });
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

    var my = {
        isTouchDevice: isTouchDevice,
        initTruncate: initTruncate,
        init: function() {
            $('select.jumpMenu').unbind('change').change(function() { $(this).closest('form').submit(); });
            $('select.jumpMenuUrl').unbind('change').change(function(e) { window.location.href = $(e.target).val(); });

            initAnchorNavigationLinks();
            initFixFooter();
            initOpenUrlLinks();
            initHideDetails();

            initTruncatedRecordImageNavi();
            initTruncate();
            initContentNavigation();
            initRecordSwipe();
        },
    };

    return my;
})(finna);

