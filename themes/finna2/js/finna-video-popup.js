/*global VuFind, finna, videojs */
finna.videoPopup = (function finnaVideoPopup() {

  function initVideoJs(_container, videoSources, posterUrl) {
    var $container = $(_container);
    var $videoElem = $(_container).find('video');

    // Use a fairly small buffer for faster quality changes
    videojs.Hls.GOAL_BUFFER_LENGTH = 10;
    videojs.Hls.MAX_GOAL_BUFFER_LENGTH = 20;
    var player = videojs($videoElem.get(0), {
      html5: {
        hls: {
          overrideNative: !videojs.browser.IS_SAFARI
        }
      }
    });
 
    player.ready(function onReady() {
      this.hotkeys({
        enableVolumeScroll: false,
        enableModifiersForNumbers: false
      });
    });

    player.src(videoSources);
    player.poster(posterUrl);

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

  function initVideoPopup(_container) {
    var container = typeof _container === 'undefined' ? $('body') : $(_container);
    var inline = container.find('[data-inline]');
    var parent;
    var embed = inline.length > 0;
    if (inline.length) {
      parent = 'inline-video';
      $('.inline-video-container').insertAfter($('.search-form-container'));
      $('.inline-video-container').removeClass('hidden');

      if (inline.length < 2) {
        inline.addClass('hidden');
      }
    }

    var translations = {
      close: VuFind.translate('close'),
      next: VuFind.translate('Next Record'),
      previous: VuFind.translate('Previous Record'),
    };

    container.find('[data-embed-video]').each(function initVideo() {
      var videoSources = $(this).data('videoSources');
      var scripts = $(this).data('scripts');
      var posterUrl = $(this).data('posterUrl');
      $(this).finnaPopup({
        id: 'recordvideo',
        modal: '<video class="video-js vjs-big-play-centered video-popup" controls></video>',
        classes: 'video-popup',
        parent: parent,
        cycle: !embed,
        embed: embed,
        translations: translations,
        onPopupInit: function onPopupInit(t) {
          if (this.embed) {
            t.removeClass('active-video');
          }
        },
        onPopupOpen: function onPopupOpen() {
          if (this.embed) {
            $('.active-video').removeClass('active-video');
            this.currentTrigger().addClass('active-video');
          } else {
            this.content.css('height', '100%');
          }
          finna.layout.loadScripts(scripts, function onScriptsLoaded() {
            finna.videoPopup.initVideoJs('.video-popup', videoSources, posterUrl);
          });
        }
      });
    });
  }

  function initIframeEmbed(_container) {
    var container = typeof _container === 'undefined' ? $('body') : _container;
    var inline = container.find('[data-inline-iframe]');
    var parent;
    var embed = inline.length > 0;
    if (inline.length) {
      parent = 'inline-video';
      $('.inline-video-container').insertAfter($('.search-form-container'));
      $('.inline-video-container').removeClass('hidden');

      if (inline.length < 2) {
        inline.addClass('hidden');
      }
    }
    var translations = {
      close: VuFind.translate('close'),
      next: VuFind.translate('Next Record'),
      previous: VuFind.translate('Previous Record'),
    };

    container.find('[data-embed-iframe]').each(function setIframes() {
      var source = $(this).is('a') ? $(this).attr('href') : $(this).data('link');
      $(this).finnaPopup({
        id: 'recordiframe',
        cycle: !embed,
        classes: 'finna-iframe',
        modal: '<div style="height:100%">' +
          '<iframe class="player finna-popup-iframe" frameborder="0" allowfullscreen></iframe>' +
          '</div>',
        parent: parent,
        translations: translations,
        embed: embed,
        onPopupInit: function onPopupInit(t) {
          if (this.embed) {
            t.removeClass('active-video');
          }
        },
        onPopupOpen: function onPopupOpen() {
          if (this.embed) {
            $('.active-video').removeClass('active-video');
            this.currentTrigger().addClass('active-video');
          } else {
            this.content.css('height', '100%');
          }
          // If using Chrome + VoiceOver, Chrome crashes if vimeo player video settings button has aria-haspopup=true
          $('.vp-prefs .js-prefs').attr('aria-haspopup', false);
          var player = this.content.find('iframe');
          player.attr('src', this.adjustEmbedLink(source));
        }
      });
    });
  }

  var my = {
    initVideoPopup: initVideoPopup,
    initVideoJs: initVideoJs,
    initIframeEmbed: initIframeEmbed,
  };

  return my;
})();
