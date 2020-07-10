/* global finna, VuFind, L */
finna.imagePaginator = (function imagePaginator() {
  var imageElement = '<a draggable="false" href="" class="image-popup image-popup-navi hidden-print"></a>';
  var paginatorIndex = 0;
  var timeOut = null;

  var defaults = {
    recordId: 0,
    iconlabelClass: 'default-icon',
    maxRows: 3,
    imagesPerPage: 8,
    imagesOnMobile: 6,
    imagesOnPopup: 10,
    imagesOnNormal: 8,
    imagesPerRow: 8,
    enableImageZoom: false,
    recordType: 'default-type',
    leaflet: {
      offsetPercentage: 4
    }
  };

  var translations = {
    image: '',
    close: '',
    next: '',
    previous: '',
    no_cover: '',
    isSet: false
  };

  /**
   * Initializer function
   *
   * @param {object} images
   * @param {object} settings
   * @param {boolean} isList
   */
  function FinnaPaginator(images, settings) {
    var _ = this;

    _.isList = settings.isList;
    if (_.isList) {
      settings.imagesOnNormal = 0;
      _.root = $('.hiddenId[value="' + settings.recordId + '"]').closest('.result').find('.recordcover-holder');
    } else {
      _.root = $('.recordcover-holder.paginate');
    }
    _.root.removeClass('paginate');
    _.images = images;

    _.trigger = _.root.find('.image-popup-trigger');
    _.setPaginatorIndex(paginatorIndex++);

    _.settings = $.extend({}, defaults, settings);
    _.setMaxImages(_.settings.imagesOnNormal);
    // Index for loading correct images
    _.offSet = 0;

    // Needed references
    _.imageHolder = null;
    _.imageDetail = _.root.find('.recordcover-image-detail .image-description');
    _.moreBtn = null;
    _.lessBtn = null;
    _.pagerInfo = null;
    _.leftBtn = null;
    _.rightBtn = null;
    _.leftBrowseBtn = null;
    _.rightBrowseBtn = null;
    _.imagePopup = null;
    _.leafletLoader = null;
    _.leafletStartBounds = null;
    _.canvasElements = {};
    _.openImageIndex = 0;
    _.imagePopup = $(imageElement).clone();
  }

  /**
   * Function to create a new paginator with given images object and settings object
   *
   * @param {object} images
   * @param {object} settings
   */
  function initPaginator(images, settings) {
    if (translations.isSet === false) {
      translations = {
        image: VuFind.translate('Image'),
        close: VuFind.translate('close'),
        next: VuFind.translate('Next Record'),
        previous: VuFind.translate('Previous Record'),
        no_cover: VuFind.translate('No Cover Image'),
        isSet: true
      };
    }
    if (settings.recordType === 'marc') {
      settings.imagesOnPopup = 4;
    }
    var paginator = new FinnaPaginator(images, settings);
    paginator.init();
  }

  /**
   * Helper function for setting paginator index.
   *
   * @param {int} index
   */
  FinnaPaginator.prototype.setPaginatorIndex = function setPaginatorIndex(index) {
    var _ = this;
    _.paginatorIndex = index;
  };

  /**
   * Helper function to show a button and hide another
   *
   * @param {HTMLElement} show
   * @param {HTMLElement} hide
   */
  function toggleButtons(show, hide) {
    show.show();
    hide.hide();
  }

  /**
   * Function to create proper elements for the paginator
   */
  FinnaPaginator.prototype.init = function init() {
    var _ = this;

    _.setReferences(_.root.find('.recordcovers'));

    if (!_.isList) {
      _.moreBtn = _.root.find('.show-more-images');
      _.lessBtn = _.root.find('.show-less-images');
      toggleButtons(_.moreBtn, _.lessBtn);
      _.setEvents();
      _.loadPage(0);
      _.setTrigger(_.imageHolder.find('a:first'));
    } else {
      _.setEvents();
      _.setListTrigger(_.getImageFromArray(0));
      _.root.find('.recordcovers').addClass('mini-paginator');
      _.root.find('.recordcovers-more').hide();
    }
  };

  /**
   * Function to set references when state of paginator changes or is created
   *
   * @param {HTMLElement} covers
   * @param {boolean} isPopup
   */
  FinnaPaginator.prototype.setReferences = function setReferences(covers, isPopup) {
    var _ = this;
    covers.addClass('paginated');

    _.imageHolder = covers.find('.finna-element-track');
    _.imageHolder.empty();
    _.leftBtn = covers.find('.left-button');
    _.rightBtn = covers.find('.right-button');
    if (typeof isPopup === 'undefined' || !isPopup) {
      _.leftBrowseBtn = _.root.find('.next-image.left');
      _.rightBrowseBtn = _.root.find('.next-image.right');
      if (_.isList) {
        _.pagerInfo = covers.find('.paginator-info');
      } else {
        _.pagerInfo = _.trigger.find('.paginator-info');
      }
    } else {
      var mfpContainer = $('.finna-popup.content');
      _.pagerInfo = mfpContainer.find('.paginator-info');
      _.leftBrowseBtn = mfpContainer.find('.next-image.left');
      _.rightBrowseBtn = mfpContainer.find('.next-image.right');
    }
    _.leftBrowseBtn.off('click').click(function browseLeft() {
      _.onBrowseButton(-1);
    });
    _.rightBrowseBtn.off('click').click(function browseRight() {
      _.onBrowseButton(1);
    });
    if (_.images.length < 2) {
      covers.hide();
      _.pagerInfo.hide();
    }

    if (_.images.length < _.settings.imagesPerRow) {
      $('.recordcovers-more').hide();
    }
  };

  /**
   * Function to set browse button states
   */
  FinnaPaginator.prototype.setBrowseButtons = function setBrowseButtons(isList) {
    var _ = this;
    var state = typeof isList !== "undefined" && isList !== false;
    _.leftBrowseBtn.prop('disabled', state || _.openImageIndex < 1);
    _.rightBrowseBtn.prop('disabled', state || _.openImageIndex >= _.images.length - 1);
  };

  /**
   * Function to set events so the paginator works properly on list or normal view
   */
  FinnaPaginator.prototype.setEvents = function setEvents() {
    var _ = this;

    if (!_.isList) {
      _.leftBtn.click(function loadImages() {
        _.loadPage(-1);
      });
      _.rightBtn.click(function loadImages() {
        _.loadPage(1);
      });
      _.moreBtn.click(function setImages() {
        toggleButtons(_.lessBtn, _.moreBtn);
        _.loadPage(0, null, _.settings.imagesPerRow * _.settings.maxRows);
      });
      _.lessBtn.click(function setImages() {
        toggleButtons(_.moreBtn, _.lessBtn);
        _.loadPage(0, null, _.settings.imagesPerRow);
      });
    } else {
      _.leftBtn.off('click').click(function setImage(){
        _.onListButton(-1);
      });
      _.rightBtn.off('click').click(function setImage(){
        _.onListButton(1);
      });
      _.setButtons();
    }
    _.imagePopup.on('click', function setTriggerEvents(e){
      e.preventDefault();
      _.setTrigger($(this));
    });
  };

  FinnaPaginator.prototype.setCanvasElement = function setCanvasElement(type) {
    var _ = this;
    $.each(_.canvasElements, function hideObject(key, value) {
      value.toggle(type === key);
    });
  };

  /**
   * Function which is executed after nonzoomable image has been opened to a popup
   *
   * @param {object} image
   */
  FinnaPaginator.prototype.onNonZoomableClick = function onNonZoomableClick(image) {
    var _ = this;

    var icon = _.canvasElements.noZoom.find('.iconlabel');
    icon.addClass(_.settings.iconlabelClass).hide();
    _.canvasElements.noZoom.find('img').css('opacity', '0.5');
    _.openImageIndex = image.attr('index');

    var img = new Image();
    img.src = image.data('largest');
    $(img).attr('alt', image.data('alt'));
    img.onload = function onLoad() {
      if (typeof _.canvasElements.noZoom === 'undefined') {
        return;
      }
      if (this.naturalWidth && this.naturalWidth === 10 && this.naturalHeight === 10) {
        _.canvasElements.noZoom.addClass('no-image');
        icon.show();
        $(this).attr('alt', translations.no_cover);
      } else if (_.canvasElements.noZoom.hasClass('no-image')) {
        icon.hide();
      }
      _.canvasElements.noZoom.find('img').replaceWith($(this));
    };

    _.setCanvasElement('noZoom');
    _.setCurrentVisuals();
    _.setPagerInfo(true);
    if (typeof _.settings.onlyImage === 'undefined' || _.settings.onlyImage === false) {
      _.loadImageInformation();
    }
    _.setBrowseButtons();
  };

  /**
   * Function to consume image objects data and load a zoomable version to leaflet
   *
   * @param {HTMLElement} image
   */
  FinnaPaginator.prototype.onLeafletImageClick = function onLeafletImageClick(image) {
    var _ = this;

    if (_.openImageIndex !== image.attr('index')) {
      _.openImageIndex = image.attr('index');
      if (typeof _.settings.onlyImage === 'undefined' || _.settings.onlyImage === false) {
        _.loadImageInformation();
      }
    }

    _.setCanvasElement('leaflet');
    _.setPagerInfo(true);
    _.setCurrentVisuals();

    _.leafletHolder.eachLayer(function removeLayers(layer) {
      _.leafletHolder.removeLayer(layer);
    });
    _.leafletHolder.setMaxBounds(null);
    _.leafletHolder.setMinZoom(1);
    var img = new Image();
    img.src = image.data('largest');
    timeOut = setTimeout(function onLoadStart() {
      _.leafletLoader.addClass('loading');
    }, 100);

    img.onload = function onLoadImg() {
      if (timeOut !== null) {
        clearTimeout(timeOut);
        timeOut = null;
      }
      if (_.leafletHolder.length === 0 || typeof _.canvasElements.leaflet === 'undefined') {
        return;
      }

      _.leafletHolder.eachLayer(function removeLayers(layer) {
        _.leafletHolder.removeLayer(layer);
      });

      var h = this.naturalHeight;
      var w = this.naturalWidth;
      var leafletHolderWidth = _.canvasElements.leaflet.width();
      var leafletHolderHeight = _.canvasElements.leaflet.height();

      var zoomLevel = 1;
      var alt = h === 10 && w === 10 ? translations.no_cover : image.data('alt');

      var offsetPercentage = _.settings.leaflet.offsetPercentage;

      function calculateBounds(boundWidth, imageWidth, boundHeight, imageHeight) {
        var heightPercentage = 0;
        var widthPercentage = 0;
        var newHeight = imageHeight;
        var newWidth = imageWidth;

        if (imageHeight >= boundHeight) {
          newHeight = boundHeight - (boundHeight / 100 * offsetPercentage);
          heightPercentage = 100 - (newHeight / imageHeight * 100);
        }

        if (imageWidth >= boundWidth) {
          newWidth = boundWidth - (boundWidth / 100 * offsetPercentage);
          widthPercentage = 100 - (newWidth / imageWidth * 100);
        }

        if (heightPercentage > widthPercentage) {
          newWidth = imageWidth - (imageWidth / 100 * heightPercentage);
        } else if (widthPercentage > heightPercentage) {
          newHeight = imageHeight - (imageHeight / 100 * widthPercentage);
        }

        return {
          height: newHeight,
          width: newWidth
        };
      }

      var bounds = calculateBounds(leafletHolderWidth, w, leafletHolderHeight, h);
      var imageBounds = new L.LatLngBounds(_.leafletHolder.unproject([0, bounds.height], zoomLevel), _.leafletHolder.unproject([bounds.width, 0], zoomLevel));

      L.imageOverlay(img.src, imageBounds, {alt: alt}).addTo(_.leafletHolder);
      _.leafletHolder.flyToBounds(imageBounds, {animate: false});
      _.leafletHolder.invalidateSize(false);
      _.leafletLoader.removeClass('loading');
      _.leafletHolder.setMaxBounds(imageBounds);
      _.leafletStartBounds = imageBounds;
      _.leafletHolder.setMinZoom(_.leafletHolder.getZoom());
      _.setZoomButtons();
    };
    _.setBrowseButtons();
  };

  /**
   * Function to browse images presented in image holder object
   *
   * @param int direction to try and find an image from
   */
  FinnaPaginator.prototype.onBrowseButton = function onBrowseButton(direction) {
    var _ = this;
    var index = +direction + (+_.openImageIndex);
    var found = _.findSmallImage(index);
    if (found.length) {
      found.click();
    } else {
      _.loadPage(direction);
      found = _.findSmallImage(index);
      if (!found.length) {
        _.loadPage(0, index);
        found = _.findSmallImage(index);
      }
      found.click();
    }
    _.setBrowseButtons();
  };

  /**
   * Function to decide which image will be loaded on list type paginator, determined by direction
   *
   * @param {int} direction
   */
  FinnaPaginator.prototype.onListButton = function onListButton(direction) {
    var _ = this;
    var image = _.getImageFromArray(direction);
    _.setListTrigger(image);
    _.setButtons();
  };

  /**
   * Function to set correct canvas content and event listener on non zoomable open
   */
  FinnaPaginator.prototype.onNonZoomableOpen = function onNonZoomableOpen() {
    var _ = this;
    _.imagePopup.off('click').on('click', function onImageClick(e){
      e.preventDefault();
      _.onNonZoomableClick($(this));
    });
    _.setCanvasElement('noZoom');
  };

  /**
   * Function to set correct canvas content and initialize leaflet on zoomable open
   */
  FinnaPaginator.prototype.onZoomableOpen = function onZoomableOpen() {
    var _ = this;

    _.imagePopup.off('click').on('click', function onImageClick(e){
      e.preventDefault();
      _.onLeafletImageClick($(this));
    });
    _.leafletLoader = _.canvasElements.leaflet.find('.leaflet-image-loading');

    _.leafletHolder = L.map('leaflet-map-image', {
      minZoom: 1,
      maxZoom: 20,
      center: [0, 0],
      zoomControl: false,
      zoom: 1,
      crs: L.CRS.Simple,
      maxBoundsViscosity: 0,
      bounceAtZoomLimits: false
    });
    _.setCanvasElement('leaflet');
  };

  /**
   * Function to set left and right button to correct states
   */
  FinnaPaginator.prototype.setButtons = function setButtons() {
    var _ = this;
    _.rightBtn.prop('disabled', _.images.length <= _.settings.imagesPerPage || _.offSet === _.images.length - 1);
    _.leftBtn.prop('disabled', _.images.length <= _.settings.imagesPerPage || _.offSet < 1);
  };

  /**
   * Function to set correct info for page info, for popup prepend text with image
   *
   * @param {boolean} isPopup
   */
  FinnaPaginator.prototype.setPagerInfo = function setPagerInfo(isPopup) {
    var _ = this;
    var infoText = '';
    var imageIndex = +_.openImageIndex + 1;
    if (typeof isPopup === 'undefined' || !isPopup) {
      infoText = imageIndex + " / " + _.images.length;
    } else {
      infoText = translations.image + ' ' + imageIndex + ' / ' + _.images.length;
    }
    _.pagerInfo.find('.image-index').html(infoText);
  };

  /**
   * Function to create the track which holds the smaller images. Also determines if is called from popup so a new track can be created
   *
   * @param {HTMLElement} popupTrackArea
   * @param {boolean} isPopup
   */
  FinnaPaginator.prototype.createPopupTrack = function createPopupTrack(popupTrackArea, isPopup) {
    var _ = this;
    var covers = _.root.find('.recordcovers').clone(true);
    _.setReferences(covers, isPopup);

    if (_.isList) {
      covers.removeClass('mini-paginator');
      _.leftBtn.off('click').click(function loadImages(){
        _.loadPage(-1);
      });
      _.rightBtn.off('click').click(function loadImages(){
        _.loadPage(1);
      });
    }
    popupTrackArea.append(covers);
    if (_.images.length < 2) {
      popupTrackArea.hide();
    }
    _.loadPage(0, _.openImageIndex);
  };

  /**
   * Sets the current record index inside list view to the modal
   */
  FinnaPaginator.prototype.setRecordIndex = function setRecordIndex() {
    var _ = this;
    if ($('.paginationSimple .index').length) {
      var total = $('.paginationSimple .total').html();
      var current = +$('.paginationSimple .index').html() + $.fn.finnaPopup.getCurrent('paginator');
      _.pagerInfo.siblings('.record-index').find('.total').html(current + " / " + total);
    }
  };

  /**
   * Function to consume imagepopup elements data to create image trigger
   * When the image does not exist, we remove the trigger event and let the user navigate directly to record
   *
   * @param {HTMLElement} imagePopup
   */
  FinnaPaginator.prototype.changeTriggerImage = function changeTriggerImage(imagePopup) {
    var _ = this;
    var img = _.trigger.find('img');
    img.attr('data-src', imagePopup.attr('href'));
    img.attr('alt', imagePopup.data('alt'));

    if (_.openImageIndex !== imagePopup.attr('index')) {
      img.css('opacity', 0.5);
    }

    function setImageProperties(image) {
      $(image).css('opacity', '');
      _.setDimensions();
      if (image.naturalWidth && image.naturalWidth === 10 && image.naturalHeight === 10) {
        _.trigger.addClass('no-image');
        $(image).attr('alt', translations.no_cover);
        if (_.isList) {
          if (_.images.length < 2) {
            _.settings.enableImageZoom = false;
          }
          _.trigger.trigger('removeclick');
          $(image).parents('.grid').addClass('no-image');
        }
        if (!_.isList && _.images.length <= 1) {
          _.root.closest('.media-left').not('.audio').addClass('hidden-xs');
          _.root.closest('.media-left').find('.organisation-menu').hide();
          _.root.css('display', 'none');
          _.root.siblings('.image-details-container:not(:has(.image-rights))').hide();
          $('.record.large-image-layout').addClass('no-image-layout').removeClass('large-image-layout');
          $('.large-image-sidebar').addClass('visible-xs visible-sm');
          $('.record-main').addClass('mainbody left');
        }
      } else if (_.trigger.hasClass('no-image')) {
        _.trigger.removeClass('no-image');
      }
    }

    if (!_.isList) {
      $('.image-details-container').addClass('hidden');
      var details = $('.image-details-container[data-img-index="' + imagePopup.attr('index') + '"]');
      details.removeClass('hidden');
      var license = details.find('.truncate-field, .copyright');
      if (license.length && !license.hasClass('truncated')) {
        license.addClass("truncate-field");
        license.removeClass('truncate-done');
        finna.layout.initTruncate(details);
      }
    }
    _.imageDetail.html(imagePopup.data('description'));

    img.unveil(100, function handleLoading() {
      $(this).on('load', function handleImage() {
        setImageProperties(this);
      });
    });
  };

  /**
   * Function to clear track of images and load new amount of images with direction.
   * If openimageindex is set, loads images from that image. If imagesperpage is set updates the amount of images to show in total
   *
   * @param {int} direction
   * @param {int} openImageIndex
   * @param {int} imagesPerPage
   */
  FinnaPaginator.prototype.loadPage = function loadPage(direction, openImageIndex, imagesPerPage) {
    var _ = this;
    _.imageHolder.empty();

    if (typeof imagesPerPage !== 'undefined') {
      _.settings.imagesPerPage = imagesPerPage;
    }

    if (typeof openImageIndex !== 'undefined' && openImageIndex !== null) {
      _.offSet = +openImageIndex;
    }

    _.offSet += _.settings.imagesPerPage * direction;
    if (_.offSet < 0) {
      _.offSet = 0;
    }

    var max = _.settings.imagesPerPage - 1;
    var lastImage = max + _.offSet;

    if (lastImage > _.images.length - 1) {
      lastImage = _.images.length - 1;
      _.offSet = lastImage;
    }

    var firstImage = lastImage - max;

    if (firstImage < 1) {
      _.offSet = 0;
      firstImage = 0;
    }
    var column = 1;
    var cur = '';
    for (var currentImage = firstImage; currentImage <= lastImage; currentImage++) {
      if (column === 1) {
        cur = $('<div/>');
        _.imageHolder.append(cur);
      }
      cur.append(_.createImagePopup(_.images[currentImage]));
      column = (column === _.settings.imagesPerRow) ? 1 : column + 1;
    }
    _.setCurrentVisuals();
    _.setButtons();
  };

  /**
   * Function to find a single image from array with direction
   *
   * @param {int} direction
   */
  FinnaPaginator.prototype.getImageFromArray = function getImageFromArray(direction) {
    var _ = this;
    var max = _.images.length - 1;
    _.offSet += direction;

    if (_.offSet < 0) {
      _.offSet = 0;
    } else if (_.offSet > max) {
      _.offSet = max;
    }

    return _.images[_.offSet];
  };

  /**
   * Function to load information for image with paginators openimageindex
   */
  FinnaPaginator.prototype.loadImageInformation = function loadImageInformation() {
    var _ = this;
    var src = VuFind.path + '/AJAX/JSON?method=getImageInformation&id=' + encodeURIComponent(_.settings.recordId) + '&index=' + _.openImageIndex;

    if (typeof publicList !== 'undefined') {
      src += '&publicList=1';
    }
    var listId = $('input[name="listID"]').val();

    if (typeof listId !== 'undefined') {
      src += '&listId=' + listId;
    }
    $('.collapse-content-holder').html('<div class="large-spinner"><i class="fa fa-spinner fa-spin"/></div>');
    $.ajax({
      url: src,
      dataType: 'html'
    }).done( function setImageData(response) {
      $('.collapse-content-holder').html(JSON.parse(response).data.html);
      _.setDimensions();
      if (_.settings.recordType === 'marc') {
        _.loadBookDescription();
      } else {
        finna.layout.initTruncate($('.finna-popup'));
        $('.imagepopup-holder .summary').removeClass('loading');
      }
      VuFind.lightbox.bind('.imagepopup-holder');
      if (typeof $('.open-link a').attr('href') !== 'undefined') {
        _.setDimensions();
      }
      var collapseArea = $('.finna-popup .collapse-content-holder');
      collapseArea.find('[data-embed-video]').each(function initVideo() {
        var videoSources = $(this).data('videoSources');
        var scripts = $(this).data('scripts');
        var posterUrl = $(this).data('posterUrl');
        $(this).finnaPopup({
          id: 'popupvideo',
          cycle: false,
          parent: 'video-player',
          classes: 'canvas-player',
          translations: translations,
          modal: '<video class="video-js vjs-big-play-centered" controls></video>',
          onPopupOpen: function onPopupOpen() {
            // Lets find the active trigger
            finna.layout.loadScripts(scripts, function onScriptsLoaded() {
              finna.videoPopup.initVideoJs('.video-popup', videoSources, posterUrl);
            });
            _.setCanvasElement('video');
          },
          onPopupClose: function onPopupClose() {

          }
        });
      });

      collapseArea.find('[data-embed-iframe]').each(function setIframes() {
        var source = $(this).is('a') ? $(this).attr('href') : $(this).data('link');
        $(this).finnaPopup({
          id: 'popupiframe',
          cycle: false,
          classes: 'finna-iframe',
          translations: translations,
          modal: '<div style="height:100%">' +
            '<iframe class="player finna-popup-iframe" frameborder="0" allowfullscreen></iframe>' +
            '</div>',
          parent: 'video-player',
          onPopupOpen: function onPopupOpen() {
            var player = this.content.find('iframe');
            player.attr('src', this.adjustEmbedLink(source));
            _.setCanvasElement('video');
          },
          onPopupClose: function onPopupClose() {

          }
        });
      });

      if ($('.imagepopup-holder .feedback-record')[0] || $('.imagepopup-holder .save-record')[0]) {
        $('.imagepopup-holder .feedback-record, .imagepopup-holder .save-record').click(function onClickActionLink(/*e*/) {
          $.fn.finnaPopup.closeOpen();
        });
      }
      _.setRecordIndex();
    }).fail( function setImageDataFailure() {
      $('.collapse-content-holder').html('');
      _.setRecordIndex();
    });
  };

  /**
   * Function to load extra information for marc type records
   */
  FinnaPaginator.prototype.loadBookDescription = function loadBookDescription() {
    var _ = this;
    var url = VuFind.path + '/AJAX/JSON?method=getDescription&id=' + _.settings.recordId;
    var summaryHolder = $('.imagepopup-holder .summary');
    $.getJSON(url)
      .done(function onGetDescriptionDone(response) {
        var data = response.data.html;
        if (data.length > 0) {
          summaryHolder.find('> div p').html(data);
          finna.layout.initTruncate(summaryHolder);
        }
        summaryHolder.removeClass('loading');
      })
      .fail(function onGetDescriptionFail(/*response, textStatus*/) {
        summaryHolder.removeClass('loading');
      });
  };

  /**
   * Function to create small images for popup track consuming the data from image object
   *
   * @param {object} image
   */
  FinnaPaginator.prototype.createImagePopup = function createImagePopup(image) {
    var _ = this;
    var holder = $(_.imagePopup).clone(true);
    if (_.images.length > 1) {
      var img = new Image();
      holder.append(img, $('<i class="fa fa-spinner fa-spin"/>'));
      img.src = image.small;
      img.alt = image.alt;
      img.title = image.title;
      img.onload = function onLoad() {
        $(this).siblings('i').remove();
      };
    }
    holder.attr({
      'index': image.index,
      'data-largest': image.largest,
      'data-description': image.description,
      'href': (!_.isList && _.settings.enableImageZoom) ? image.largest : image.medium,
      'data-alt': image.alt
    });

    return holder;
  };

  /**
   * Checks if current row of images has the active record
   */
  FinnaPaginator.prototype.setCurrentVisuals = function setCurrentVisuals() {
    var _ = this;
    $('a.image-popup-navi').removeClass('current');
    _.findSmallImage(_.openImageIndex).addClass('current');
  };

  /**
   * Sets the max amount of images to show in the track. Popup has different amounts determined.
   *
   * @param {int} amount
   * @param {boolean} isPopup
   */
  FinnaPaginator.prototype.setMaxImages = function setMaxImages(amount, isPopup) {
    var _ = this;
    var width = $(window).width();

    var images = amount;
    if ((typeof isPopup === 'undefined' || isPopup === false) && _.isList) {
      images = 0;
    } else if (width < 500) {
      images = _.settings.imagesOnMobile;
    } else if (width < 768) {
      images = amount;
    } else if (width < 991) {
      images = _.settings.imagesOnMobile;
    } else if (width < 2000) {
      images = amount;
    } else if (isPopup && width < 5000) {
      images = amount + 6;
    }
    _.settings.imagesPerRow = images;
    _.settings.imagesPerPage = _.settings.imagesPerRow;
  };

  /**
   * Function to set image dimensions to download image link
   */
  FinnaPaginator.prototype.setDimensions = function setDimensions() {
    var popupHidden = !$.fn.finnaPopup.isOpen();
    var container = popupHidden ? $('.image-details-container').not('.hidden') : $('.image-information-holder');
    var openLink = container.find('.open-link a, .display-image a').attr('href');
    if (typeof openLink !== 'undefined') {
      var img = new Image();
      img.src = openLink;
      img.onload = function onLoadImg() {
        var width = this.width;
        var height = this.height;
        if (width === 10 && height === 10) {
          $('.open-link').hide();
        } else {
          container.find('.open-link .image-dimensions, .display-image .image-dimensions').text( '(' + width + ' x ' + height + ' px)');
        }
      };
    }
  };

  /**
   * Function to set image popup trigger click event and logic when popup is being opened
   */
  FinnaPaginator.prototype.setTrigger = function setTrigger(imagePopup) {
    var _ = this;
    _.changeTriggerImage(imagePopup);
    _.openImageIndex = imagePopup.attr('index');
    _.setBrowseButtons(_.isList);
    _.setPagerInfo(false);
    _.setCurrentVisuals();
    var modal = $('#imagepopup-modal').find('.imagepopup-holder').clone();

    _.trigger.not('[data-disable-modal="1"]').finnaPopup({
      modal: modal,
      id: 'paginator',
      translations: translations,
      onPopupOpen: function onPopupOpen() {
        var obj = this;
        var content = obj.content;
        obj.modalHolder.addClass(_.settings.recordType);
        _.canvasElements = {
          leaflet: content.find('.leaflet-map-image'),
          noZoom: content.find('.popup-nonzoom'),
          video: content.find('.popup-video')
        };
        _.canvasElements.leaflet.attr('id', 'leaflet-map-image');
        _.canvasElements.video.attr('id', 'video-player');

        _.setMaxImages(_.settings.imagesOnPopup, true);
        if (!_.isList) {
          toggleButtons(_.moreBtn, _.lessBtn);
        }

        if (_.settings.enableImageZoom) {
          _.onZoomableOpen();
        } else {
          _.onNonZoomableOpen();
        }

        obj.content.toggleClass('nonzoomable', !_.settings.enableImageZoom);
        _.createPopupTrack(content.find('.finna-image-pagination'), true);
        var foundImage = _.findSmallImage(_.openImageIndex);
        _.openImageIndex = null;
        foundImage.click();
        _.setBrowseButtons();
      },
      onPopupClose: function onPopupClose() {
        var covers = _.root.find('.recordcovers');
        _.setReferences(covers);
        _.imagePopup.off('click').on('click', function setTriggerEvents(e){
          e.preventDefault();
          _.setTrigger($(this));
        });
        _.canvasElements = {};
        _.setMaxImages(_.settings.imagesOnNormal);
        if (_.isList) {
          _.offSet = +_.openImageIndex;
          covers.addClass('mini-paginator');
          _.onListButton(0);
        } else {
          _.loadPage(0, _.openImageIndex);
          _.findSmallImage(_.openImageIndex).click();
        }
      }
    });
  };

  /**
   * Function to initialize zoom button logics inside popup
   */
  FinnaPaginator.prototype.setZoomButtons = function setZoomButtons() {
    var _ = this;
    _.zoomButtonState();
    $('.zoom-in').off('click').click(function zoomIn() {
      _.leafletHolder.setZoom(_.leafletHolder.getZoom() + 1);
    });
    $('.zoom-out').off('click').click(function zoomOut() {
      _.leafletHolder.setZoom(_.leafletHolder.getZoom() - 1);
    });
    $('.zoom-reset').off('click').click(function zoomReset() {
      _.leafletHolder.flyToBounds(_.leafletStartBounds, {animate: false});
    });
    $('.zoom-in, .zoom-out, .zoom-reset').off('dblclick').on('dblclick', function preventPropagation(e){
      e.preventDefault();
      e.stopPropagation();
    });
    _.leafletHolder.on('zoomend', function checkButtons() {
      _.zoomButtonState();
    });
  };

  /**
   * Function to set zoombutton states inside popup to disabled or enabled
   */
  FinnaPaginator.prototype.zoomButtonState = function zoomButtonState() {
    var _ = this;
    var min = _.leafletHolder.getMinZoom();
    var max = _.leafletHolder.getMaxZoom();
    var cur = _.leafletHolder.getZoom();
    $('.zoom-out').toggleClass('inactive', cur === min);
    $('.zoom-in').toggleClass('inactive', cur === max);
  };

  /**
   * Function to set list image trigger function
   */
  FinnaPaginator.prototype.setListTrigger = function setListTrigger(image) {
    var _ = this;
    var tmpImg = $(_.imagePopup).clone(true);
    tmpImg.find('img').data('src', image.small);
    tmpImg.attr({
      'index': image.index,
      'href': image.medium,
      'data-alt': image.alt
    });
    tmpImg.click();
  };

  /**
   * Function to find an image element from imageHolder track
   *
   * @param index int index of wanted image element
   */
  FinnaPaginator.prototype.findSmallImage = function findSmallImage(index) {
    var _ = this;
    return _.imageHolder.find('a[index="' + index + '"]');
  };

  var my = {
    initPaginator: initPaginator
  };

  return my;
})();
