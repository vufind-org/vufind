/*global VuFind, finna, videojs */
finna.videoPopup = (function finnaVideoPopup() {

  function resizeVideoPopup($container) {
    var mfp = $container.closest('.mfp-content');
    if (mfp.length === 0) {
      return;
    }
    var width = $('.mfp-content').width();
    var height = $('.mfp-container').height();
    $container.css('width', width).css('height', height);
  }

  function initVideoJs(_container, videoSources, posterUrl) {
    var $container = $(_container);
    var $videoElem = $(_container).find('video');

    // Use a fairly small buffer for faster quality changes
    videojs.Hls.GOAL_BUFFER_LENGTH = 10;
    videojs.Hls.MAX_GOAL_BUFFER_LENGTH = 20;
    var player = videojs($videoElem.get(0));

    player.ready(function onReady() {
      this.hotkeys({
        enableVolumeScroll: false,
        enableModifiersForNumbers: false
      });
    });

    resizeVideoPopup($container);

    player.src(videoSources);
    player.poster(posterUrl);

    var handleCloseButton = function handleCloseButton() {
      if (player.userActive()) {
        $('.mfp-close').css('opacity', '1');
      } else {
        $('.mfp-close').css('opacity', '0');
      }
    };

    player.on('useractive', handleCloseButton);
    player.on('userinactive', handleCloseButton);

    var selectedBitrate = 'auto';

    player.qualityLevels().on('addqualitylevel', function onAddQualityLevel(event) {
      event.qualityLevel.enabled = selectedBitrate === "auto" || event.qualityLevel.height.toString() === selectedBitrate;
    });

    player.on('loadedmetadata', function onMetadataLoaded() {
      var qualityLevels = player.qualityLevels();
      var addLevel = function addLevel(i, val) {
        var $item = $('<li/>')
          .addClass('vjs-menu-item')
          .attr('tabindex', i)
          .attr('role', 'menuitemcheckbox')
          .attr('aria-live', 'polite')
          .attr('aria-checked', 'false')
          .data('bitrate', String(val).toLowerCase());
        $('<span/>')
          .addClass('vjs-menu-item-text')
          .text(val)
          .appendTo($item);
        $item.appendTo($container.find('.quality-selection'));
        return $item;
      };
      var qLevels = [];
      for (var i = 0; i < qualityLevels.length; i++) {
        var quality = qualityLevels[i];

        if (quality.height !== undefined) {
          qLevels.push(quality.height);

          if (!$container.find('.quality-selection').length) {
            var $qs = $('<div/>').addClass('vjs-menu-button vjs-menu-button-popup vjs-control vjs-button');
            var $button = $('<button/>')
              .addClass('vjs-menu-button vjs-menu-button-popup vjs-button')
              .attr({'type': 'button', 'aria-live': 'polite', 'aria-haspopup': true, 'title': VuFind.translate('Quality')})
              .appendTo($qs);
            $('<span/>')
              .addClass('vjs-icon-cog')
              .attr('aria-hidden', 'true')
              .appendTo($button);
            $('<span/>')
              .addClass('vjs-control-text')
              .text(VuFind.translate('Quality'))
              .appendTo($button);
            var $menu = $('<div/>')
              .addClass('vjs-menu')
              .appendTo($qs);
            $('<ul/>')
              .addClass('quality-selection vjs-menu-content')
              .attr('role', 'menu')
              .appendTo($menu);

            $container.find('.vjs-fullscreen-control').before($qs);
          } else {
            $container.find('.quality-selection').empty();
          }

          qLevels.sort(function compareFunc(a, b) {
            return a - b;
          });

          $.each(qLevels, addLevel);

          addLevel(qLevels.length, 'auto')
            .addClass('vjs-selected')
            .attr('aria-checked', 'true');
        }
      }
    });

    player.load();

    $('body')
      .unbind('click.videoQuality')
      .on('click.videoQuality', '.quality-selection li', function onClickQuality() {
        if ($container.find($(this)).length === 0) {
          return;
        }
        $container.find('.quality-selection li')
          .removeClass('vjs-selected')
          .prop('aria-checked', 'false');

        $(this)
          .addClass('vjs-selected')
          .attr('aria-checked', 'true');

        selectedBitrate = String($(this).data('bitrate'));
        var levels = player.qualityLevels();
        for (var i = 0; i < levels.length; i++) {
          levels[i].enabled = 'auto' === selectedBitrate || String(levels[i].height) === selectedBitrate;
        }
      });
  }

  function displayVideo(video) {
    var videoSources = video.data('videoSources');
    var scripts = video.data('scripts');
    var posterUrl = video.data('posterUrl');
    var videoPlayer = "<video id='video-player' class='video-js video-js-player vjs-big-play-centered' controls></video>";
    if ($('[data-inline]').length > 0) {
      $('.inline-video').html(videoPlayer);
      $('[data-inline].active-video').removeClass('active-video');
      video.addClass('active-video');
      finna.layout.loadScripts(scripts, function onScriptsLoaded() {
        initVideoJs('.inline-video', videoSources, posterUrl);
      });
      $('.vjs-big-play-button').focus();
    } else {
      $.magnificPopup.open({
        type: 'inline',
        items: {
          src: "<div class='video-popup'>" + videoPlayer + "</div>"
        },
        callbacks: {
          open: function onOpen() {
            $('#video-player').closest('.mfp-content').addClass('videoplayer-only');
            finna.layout.loadScripts(scripts, function onScriptsLoaded() {
              initVideoJs('.video-popup', videoSources, posterUrl);
            });
          },
          close: function onClose() {
            videojs('video-player').dispose();
          },
          resize: function resizeVideo() {
            resizeVideoPopup($('.video-popup'));
          }
        }
      });
    }
  }

  function initVideoPopup(_container) {
    var container = typeof _container === 'undefined' ? $('body') : $(_container);

    container.find('[data-embed-video]').click(function onClickVideoLink(e) {
      displayVideo($(this));
      e.preventDefault();
    });

    if (container.find('[data-inline]').length > 0) {
      var defaultVideo = container.find('[data-inline]').first();
      $('.inline-video-container').insertAfter($('.search-form-container'));
      $('.inline-video-container').removeClass('hidden');
      if (container.find('[data-inline]').length < 2 ) {
        container.find('[data-inline]').addClass('hidden');
      }
      displayVideo(defaultVideo);
    }
  }

  function displayIframe(video) {
    var source = video.is('a') ? video.attr('href') : video.data('link');
    if ($('[data-inline-iframe]').length) {
      var embedUrl = video.data('embed-url');
      var player = '<iframe class="embed-responsive-item" src="' + embedUrl + '" allowfullscreen>';
      $('.inline-video').html(player);

      // If using Chrome + VoiceOver, Chrome crashes if vimeo player video settings button has aria-haspopup=true
      $('.vp-prefs .js-prefs').attr('aria-haspopup', false);
      $('[data-inline-iframe].active-video').removeClass('active-video');
      video.addClass('active-video');
    } else {
      $.magnificPopup.open({
        type: 'iframe',
        tClose: VuFind.translate('close'),
        items: {
          src: source
        },
        iframe: {
          markup: '<div class="mfp-iframe-scaler">'
            + '<div class="mfp-close"></div>'
            + '<iframe class="mfp-iframe" frameborder="0" allowfullscreen></iframe>'
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
    }
  }
  function initIframeEmbed(_container) {
    var container = typeof _container === 'undefined' ? $('body') : _container;

    container.find('[data-embed-iframe]').click(function onClickEmbedLink(e) {
      if (typeof $.magnificPopup.instance !== 'undefined' && $.magnificPopup.instance.isOpen) {
        // Close existing popup (such as image-popup) first without delay so that its
        // state doesn't get confused by the immediate reopening.
        $.magnificPopup.instance.st.removalDelay = 0;
        $.magnificPopup.close();
      }
      displayIframe($(this));
      e.preventDefault();
      return false;
    });
    if (container.find('[data-inline-iframe]').length > 0) {
      var defaultVideo = container.find('[data-inline-iframe]').first();
      $('.inline-video-container').insertAfter($('.search-form-container'));
      $('.inline-video-container').removeClass('hidden');
      if (container.find('[data-inline-iframe]').length < 2 ) { 
        container.find('[data-inline-iframe]').addClass('hidden');
      }
      displayIframe(defaultVideo);
    }
  }

  var my = {
    initVideoPopup: initVideoPopup,
    initVideoJs: initVideoJs,
    initIframeEmbed: initIframeEmbed,
  };

  return my;
})();
