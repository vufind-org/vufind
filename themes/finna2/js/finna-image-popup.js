/*global VuFind, finna, module, videojs, L */
finna.imagePopup = (function finnaImagePopup() {

  function openPopup(trigger) {
    var ind = trigger.data('ind');
    var links = trigger.closest('.recordcover-holder').find('.image-popup');
    var link = links.filter(function filterLink() {
      return $(this).data('ind') === ind
    } );
    link.click();
  }

  function initThumbnailNavi() {
    // Assign record indices
    var recordIndex = null;
    if ($('.paginationSimple').length) {
      recordIndex = $('.paginationSimple .index').text();
      $('.image-popup-trigger').each(function handlePopupTrigger() {
        $(this).data('recordInd', recordIndex++);
      });
    }

    // Assign image indices
    var index = 0;
    $('.image-popup').each(function assignIndex() {
      $(this).data('ind', index++);
      var recordIdx = $(this).closest('.recordcover-holder').find('.image-popup-trigger').data('recordInd');
      if (recordIdx) {
        $(this).data('recordInd', recordIdx);
      }
    });

    // Assign image indices for individual images.
    $('.recordcovers').each(function handleCover() {
      var thumbInd = 0;
      $(this).find('.image-popup').each(function handlePopup() {
        $(this).data('thumbInd', thumbInd++);
      });
    });

    // Open image-popup from medium size record image.
    $('.image-popup-trigger').each(function handlePopupTrigger() {
      var links = $(this).closest('.recordcover-holder').find('.recordcovers .image-popup');
      var linkIndex = links.eq(0).data('ind');
      $(this).data('ind', linkIndex);
      $(this).data('thumbInd', 0);
    });

    // Roll-over thumbnail images: update medium size record image and indices.
    $('.image-popup-navi').click(function updateImage(e) {
      var trigger = $(this).closest('.recordcover-holder').find('.image-popup-trigger');
      trigger.data('ind', $(this).data('ind'));
      trigger.data('thumbInd', $(this).data('thumbInd'));
      // Temporarily adjust the image so that the user sees something is happening
      var img = trigger.find('img');
      img.css('opacity', 0.5);
      img.one('load', function onLoadImage() {
        img.css('opacity', '');
        $('.image-dimensions').text('(' + (this.naturalWidth + ' X ' + this.naturalHeight + ')'));
      });
      img.attr('src', $(this).attr('href'));
      var textContainers = $(this).closest('.record-image-container').find('.image-details-container');
      textContainers.addClass('hidden');
      textContainers.filter('[data-img-index="' + $(this).data('imgIndex') + '"]').removeClass('hidden');
      initRecordImage();
      e.preventDefault();
    });
  }

  // Copied from finna-mylist.js to avoid dependency
  function getActiveListId() {
    return $('input[name="listID"]').val();
  }

  function initRecordImage() {
    // Collect data for all image-popup triggers on page.
    var urls = $('.image-popup').map(function mapPopupTriggers() {
      // result list
      var id = $(this).closest('.result').find('.hiddenId');
      if (!id.length) {
        // gallery view
        id = $(this).closest('.record-container').find('.hiddenId');
        if (!id.length) {
          // record page
          id = $('.record .hiddenId');
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
      var src = VuFind.path + '/AJAX/JSON?method=getImagePopup&id=' + encodeURIComponent(id) + '&index=' + thumbInd;

      if (typeof publicList !== 'undefined') {
        src += '&publicList=1';
      }

      var listId = getActiveListId();
      if (typeof listId !== 'undefined') {
        src += '&listId=' + listId;
      }

      return {
        src: src,
        href: $(this).attr('href'),
        ind: ind,
        recordInd: recordInd
      }
    }).toArray();

    $('.image-popup-trigger').each(function initPopup() {
      $(this).magnificPopup({
        items: urls,
        index: $(this).data('ind'),
        type: 'ajax',
        tLoading: '',
        tClose: VuFind.translate('close'),
        preloader: true,
        preload: [1, 3],
        removalDelay: 200,
        ajax: {
          cursor: '',
          settings: {
            dataType: 'json'
          }
        },
        callbacks: {
          parseAjax: function onParseAjax(mfpResponse) {
            mfpResponse.data = mfpResponse.data.data.html;
          },
          ajaxContentAdded: function onAjaxContentAdded() {
            var popup = $('.imagepopup-holder');
            var type = popup.data("type");
            var id = popup.data("id");
            var recordIndex = $.magnificPopup.instance.currItem.data.recordInd;
            VuFind.lightbox.bind('.imagepopup-holder');
            var zoomable = $('#leaflet-map-image').data('large-image-layout') && $('#leaflet-map-image').data('enable-image-popup-zoom');
            if (zoomable) {
              initImageZoom();
            } else {
              $('.image img').one('load', function onLoadImg() {
                $('.imagepopup-holder .image').addClass('loaded');
                initDimensions();
                $(this).attr('alt', $('#popup-image-title').html());
                $(this).attr('aria-labelledby', 'popup-image-title');
                if ($('#popup-image-description').length) {
                  $(this).attr('aria-describedby', 'popup-image-description');
                }
              }).each(function triggerImageLoad() {
                if (this.complete) {
                  $(this).load();
                }
              });
            }

            // Prevent navigation button CSS-transitions on touch-devices
            if (finna.layout.isTouchDevice()) {
              $('.mfp-container .mfp-close, .mfp-container .mfp-arrow-right, .mfp-container .mfp-arrow-left').addClass('touch-device');

              $('.mfp-container').swipe({
                allowPageScroll: 'vertical',
                // Generic swipe handler for all directions
                swipeRight: function onSwipeRight(/*event, phase, direction, distance, duration*/) {
                  $('.mfp-container .mfp-arrow-left').click();
                },
                swipeLeft: function onSwipeLeft(/*event, direction, distance, duration*/) {
                  $('.mfp-container .mfp-arrow-right').click();
                },
                threshold: 75,
                cancelThreshold: 20
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
            $('.imagepopup-holder .image-rights .copyright-link a').click(function onClickCopyright() {
              var mode = $(this).data('mode') === '1';

              var moreLink = $('.imagepopup-holder .image-rights .more-link');
              var lessLink = $('.imagepopup-holder .image-rights .less-link');

              moreLink.toggle(!mode);
              lessLink.toggle(mode);

              $('.imagepopup-holder .image-rights .copyright').toggle(mode);

              return false;
            });

            // load feedback modal
            if ($('.imagepopup-holder .feedback-record')[0] || $('.imagepopup-holder .save-record')[0]) {
              $('.imagepopup-holder .feedback-record, .imagepopup-holder .save-record').click(function onClickActionLink(/*e*/) {
                $.magnificPopup.close();
              });
            }

            // Load book description
            var summaryHolder = $('.imagepopup-holder .summary');
            if (type === 'marc') {
              var url = VuFind.path + '/AJAX/JSON?method=getDescription&id=' + id;
              $.getJSON(url)
                .done(function onGetDescriptionDone(response) {
                  var data = response.data.html;
                  if (data.length > 0) {
                    summaryHolder.find('> div p').html(data);
                    finna.layout.initTruncate(summaryHolder);
                    summaryHolder.removeClass('loading');
                  }
                })
                .fail(function onGetDescriptionFail(/*response, textStatus*/) {
                  summaryHolder.removeClass('loading');
                });
            } else {
              finna.layout.initTruncate(summaryHolder);
              summaryHolder.removeClass('loading');
            }

            // Init embedding
            finna.layout.initIframeEmbed(popup);
            initVideoPopup(popup);
          },
          close: function closePopup() {
            if ($('#video-player').length) {
              videojs('video-player').dispose();
            }
          }
        },
        gallery: {
          enabled: true,
          preload: [0, 2],
          navigateByImgClick: true,
          arrowMarkup: '<button title="%title%" type="button" aria-label="%title%" class="mfp-arrow mfp-arrow-%dir%"></button>',
          tPrev: VuFind.translate('Prev'),
          tNext: VuFind.translate('Next'),
          tCounter: ''
        }
      });
    });
  }

  function initVideoPopup(_container) {
    var container = typeof _container === 'undefined' ? $('body') : _container;

    container.find('a[data-embed-video]').click(function openVideoPopup(e) {
      var videoSources = $(this).data('videoSources');
      var posterUrl = $(this).data('posterUrl');
      var scripts = $(this).data('scripts');

      var mfp = $.magnificPopup.instance;
      mfp.index = -1;
      mfp.gallery = {enabled: false};
      mfp.items[-1] = {
        src: '<div class="video-popup"><video id="video-player" class="video-js vjs-big-play-centered" controls></video></div>',
        type: 'inline'
      };
      $('.mfp-arrow-right, .mfp-arrow-left').addClass('hidden');
      mfp.updateItemHTML();

      finna.layout.loadScripts(scripts, function onScriptsLoaded() {
        finna.layout.initVideoJs('.video-popup', videoSources, posterUrl);
      });

      e.preventDefault();
    });
  }

  function resolveRecordImageSize() {
    if ($('.image-popup-navi').length > 1) {
      initThumbnailNavi();
      initRecordImage();
    } else {
      $('.image-popup-trigger img').one('load', function onLoadImg() {
        if (this.naturalWidth > 10 && this.naturalHeight > 10) {
          initThumbnailNavi();
          initRecordImage();
        } else {
          $(this).closest('.recordcover-holder').hide();
          $('.image-rights').hide();
          $('.media-left > .organisation-menu').hide();
        }
      });
    }
  }

  function initImageZoom() {
    var img = new Image();
    img.src = $('.imagepopup-holder .original-image-url').attr('href');
    img.onload = function onLoadImg() {
      $('.imagepopup-holder .image').addClass('loaded');
      initDimensions();
      $('.mfp-content').removeClass('full-size');
      $(this).attr('alt', $('#popup-image-title').html());
      $(this).attr('aria-labelledby', 'popup-image-title');
      if ($('#popup-image-description').length) {
        $(this).attr('aria-describedby', 'popup-image-description');
      }
      var map = L.map('leaflet-map-image', {
        minZoom: 1,
        maxZoom: 6,
        center: [0, 0],
        zoomControl: false,
        zoom: 1,
        crs: L.CRS.Simple,
        maxBoundsViscosity: 0.9,
        dragging: true,
      });
      $('.zoom-in').click(function zoomIn() {
        map.setZoom(map.getZoom() + 1)
      });
      $('.zoom-out').click(function zoomOut() {
        map.setZoom(map.getZoom() - 1)
      });
      $('.zoom-reset').click(function zoomReset() {
        map.setZoom(1)
      });
      var h = this.naturalHeight;
      var w = this.naturalWidth;
      if (h === 10 && w === 10) {
        $('#leaflet-map-image').hide();
        return;
      }
      var imageNaturalSizeZoomLevel = 3;
      if (h < 2000 && w < 2000) {
        imageNaturalSizeZoomLevel = 2;
      }
      if (h < 1000 && w < 1000) {
        imageNaturalSizeZoomLevel = 1;
      }
      var southWest = map.unproject([0, h], imageNaturalSizeZoomLevel);
      var northEast = map.unproject([w, 0], imageNaturalSizeZoomLevel);
      var bounds = new L.LatLngBounds(southWest, northEast);
      var overlay = L.imageOverlay(img.src, bounds);
      map.flyToBounds(bounds, {animate: false});
      map.setMaxBounds(bounds);
      overlay.addTo(map);
      map.invalidateSize();
      map.on('zoomend', function adjustPopupSize() {
        if (map.getZoom() > 1) {
          $('.mfp-content').addClass('full-size');
        } else {
          $('.mfp-content').removeClass('full-size');
          map.flyToBounds(bounds, {animate: false});
        }
        map.invalidateSize();
      });
    }
  }

  function initDimensions() {
    if (typeof $('.open-link a').attr('href') !== 'undefined') {
      var img = document.createElement('img')
      img.src = $('.open-link a').attr('href');
      img.onload = function onLoadImg() {
        if (this.width === 10 && this.height === 10) {
          $('.open-link').hide();
        }
        else {
          $('.open-link .image-dimensions').text( '(' + this.width + ' X ' + this.height + ')')
        }
      }
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
          $(this).closest('a.image-popup-trigger').unbind('click');
          $('.record.large-image-layout').addClass('no-image-layout').removeClass('large-image-layout');
          $('.large-image-sidebar').addClass('visible-xs visible-sm');
          $('.record-main').addClass('mainbody left');
          var href = $(this).parent().attr('href');
          $(this).parent().attr({'href': href.split('#')[0], 'title': ''});
          $(this).parents('.grid').addClass('no-image');
          $('.rating-stars').addClass('hidden-xs');
          $('.image-rights-default').addClass('hidden');
        }
      }).each(function loadImage() {
        if (this.complete) {
          $(this).load();
        }
      });
    });
  }

  function init() {
    if (module !== 'record') {
      initThumbnailNavi();
      initRecordImage();
    } else {
      resolveRecordImageSize();
      initDimensions();
    }

    if (location.hash === '#image') {
      openPopup($('.image-popup-trigger'));
    }
    $.extend(true, $.magnificPopup.defaults, {
      tLoading: VuFind.translate('loading') + '...'
    });
    initImageCheck();
  }

  var my = {
    init: init
  };

  return my;
})();
