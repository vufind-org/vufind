finna.imagePopup = (function(finna) {

    var openPopup = function(trigger) {
        var ind = trigger.data('ind');
        var links = trigger.closest('.recordcover-holder').find('.image-popup');
        var link = links.filter(function() { 
            return $(this).data('ind') === ind 
        } );
        link.click();
    }

    var initThumbnailNavi = function() {
        // Assign record indices
        var recordIndex = null;
        if ($(".paginationSimple").length) {
            recordIndex = $(".paginationSimple .index").text();
            $(".image-popup-trigger").each(function() {
                $(this).data('recordInd', recordIndex++);
            });
        }

        // Assign image indices
        var index = 0;
        $(".image-popup").each(function() {
            $(this).data('ind', index++);
            if (recordIndex = $(this).closest('.recordcover-holder').find('.image-popup-trigger').data('recordInd')) {
                $(this).data('recordInd', recordIndex);
            }
        });

        // Assign image indices for individual images.
        $(".recordcovers").each(function() {
            var thumbInd = 0;
            $(this).find('.image-popup').each(function() {
                $(this).data('thumbInd', thumbInd++);
            });
        });

        // Open image-popup from medium size record image.
        $(".image-popup-trigger").each(function() {
            var links = $(this).closest('.recordcover-holder').find('.recordcovers .image-popup');
            var index = links.eq(0).data('ind');
            $(this).data('ind', index);
            $(this).data('thumbInd', 0);
        });

        // Roll-over thumbnail images: update medium size record image and indices.
        $(".image-popup-navi").mouseenter(function() {
            var trigger = $(this).closest('.recordcover-holder').find('.image-popup-trigger');
            trigger.data('ind', $(this).data('ind'));
            trigger.data('thumbInd', $(this).data('thumbInd'));
            trigger.find('img').attr('src', $(this).attr('href'));
        });

        // Open image-popup from medium size record image.
        $(".image-popup-trigger").click(function(e) {
            openPopup($(this));
            e.preventDefault();
        });     
    };

    // Copied from finna-mylist.js to avoid dependency
    var getActiveListId = function() {
        return $('input[name="listID"]').val();
    };
    
    var initRecordImage = function() {
        // Collect data for all image-popup triggers on page.
        urls = $('.image-popup').map(
            function() {
                var id = null;

                // result list
                id = $(this).closest('.result').find('.hiddenId');
                if (!id.length) {
                    // gallery view
                    id = $(this).closest('.record-container').find('.hiddenId');
                    if (!id.length) {
                        // record page
                        id = $(this).closest('.record.recordId').find('.hiddenId');
                    }
                }
                if (!id.length) {
                    // my list
                    id = $(this).closest('.myresearch-row').find('.hiddenId');
                }

                if (!id.length) {
                    return;
                }
                id = id.val();

                var ind = $(this).data('ind');
                var thumbInd = $(this).data('thumbInd');
                var recordInd = $(this).data('recordInd');
                var src =
                    path + '/AJAX/JSON?method=getImagePopup&id=' + encodeURIComponent(id)
                    + '&index=' + thumbInd;

                if (typeof(publicList) != 'undefined') {
                    src += '&publicList=1';
                }

                var listId = getActiveListId();
                if (typeof(listId) != 'undefined') {
                    src += '&listId=' + listId;
                }

                return {
                    src: src,
                    href: $(this).attr('href'),
                    ind: ind,
                    recordInd: recordInd
                }
            } 
        ).toArray();
        
        // Init image-popup components.
        $('.image-popup').each(function() {
            $(this).magnificPopup({
                items: urls,
                index: $(this).data('ind'),
                type: 'ajax',
	            tLoading: '',
	            preloader: true,
	            preload: [1,3],
	            removalDelay: 200,
                ajax: {
                    cursor: ''
                },

                callbacks: {
                    ajaxContentAdded: function() {
                        var popup = $(".imagepopup-holder");
                        var type = popup.data("type");
                        var id = popup.data("id");
                        var recordIndex = $.magnificPopup.instance.currItem.data.recordInd;

                        $(".imagepopup-holder .image img").one("load", function() {
                            $(".imagepopup-holder .image").addClass('loaded');
                            initDimensions();
                        }).each(function() {
                            if(this.complete) {
                                $(this).load();
                            }
                        });
                        
                        // Prevent navigation button CSS-transitions on touch-devices
                        if (finna.layout.isTouchDevice()) {
                            $(".mfp-container .mfp-close").addClass('touch-device');
                            
                            $(".mfp-container").swipe( {
                              allowPageScroll:"vertical",
                              //Generic swipe handler for all directions
                              swipeRight:function(event, phase, direction, distance, duration) {
                                $(".mfp-container .mfp-arrow-left").click();
                              },
                              swipeLeft:function(event, direction, distance, duration) {
                                $(".mfp-container .mfp-arrow-right").click();
                              },
                            threshold: 75,
                            cancelThreshold:20,
                            });   
                        }                        

                        // Record index
                        if (recordIndex) {
                            var recIndex = $('.imagepopup-holder .image-info .record-index');
                            var recordCount = $(".paginationSimple .total").text();
                            recIndex.find('.index').html(recordIndex);
                            recIndex.find('.total').html(recordCount);
                            recIndex.show();
                        }

                        // Image copyright information
                        $(".imagepopup-holder .image-rights .copyright-link a").on("click", function() {
                            var mode = $(this).data("mode") == 1;                                      
                            
                            var moreLink = $(".imagepopup-holder .image-rights .more-link");
                            var lessLink = $(".imagepopup-holder .image-rights .less-link");
                            
                            moreLink.toggle(!mode);
                            lessLink.toggle(mode);
                            
                            $(".imagepopup-holder .image-rights .copyright").toggle(mode);
                            
                            return false;                                      
                        });

                        // Load book description                        
                        var summaryHolder = $(".imagepopup-holder .summary");
                        if (type == 'marc') {
                            var url = path + '/AJAX/JSON?method=getDescription&id=' + id;
                            $.getJSON(url, function(response) {
                                if (response.status === 'OK' && response.data.length > 0) {
                                    summaryHolder.find("> div p").html(response.data);
                                    finna.layout.initTruncate(summaryHolder);
                                    summaryHolder.removeClass('loading');
                                }
                            });
                        } else {
                            finna.layout.initTruncate(summaryHolder);
                            summaryHolder.removeClass('loading');
                        }
                    },
                },

                gallery: {                 
                    enabled: true,
                    preload: [0,2],
                    navigateByImgClick: true,
                    arrowMarkup: '<button title="%title%" type="button" class="mfp-arrow mfp-arrow-%dir%"></button>',
                    tPrev: 'trPrev',
                    tNext: 'trNext',
                    tCounter: ''
                }
            });
        });
    };

    var resolveRecordImageSize = function() {
        $(".image-popup-trigger img").one('load', function() {
            if (this.naturalWidth > 10 && this.naturalHeight > 10) {
                initThumbnailNavi();
                initRecordImage();
            } else {
                $(this).closest('a.image-popup-trigger')
                    .addClass('disable')
                    .unbind('click').on('click', function() { return false; }
                );
            }
        });
    };

    var initDimensions = function() {
      if ($('.open-link a').attr('href') != 'undefined') {
          var img = document.createElement('img')
          img.src = $('.open-link a').attr('href');
          img.onload = function() {
            if (this.width == 10 && this.height == 10) { 
              $('.open-link').hide();
            }
            else {
              $('.open-link .image-dimensions').text( '('+ this.width + ' X ' + this.height + ')')
            }
          }
      }
    }
    var my = {
        init: function() {
            if (module != 'record') {
                initThumbnailNavi();
                initRecordImage();
            } else {
                resolveRecordImageSize();
            }

            if (location.hash == '#image') {
                openPopup($('.image-popup-trigger'));
            }
        }
    };
    
    return my;

})(finna);

