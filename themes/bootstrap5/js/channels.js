/*global bootstrap, getUrlRoot, VuFind */
VuFind.register('channels', function Channels() {
  function addLinkButtons(elem) {
    var links;
    try {
      links = JSON.parse(elem.dataset.linkJson);
    } catch (e) {
      console.error("Error parsing " + elem.dataset.linkJson);
      return;
    }
    if (links.length === 0) {
      return;
    }
    var $cont = $(
      '<div class="dropdown">' +
        '<button class="btn btn-link" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true" aria-label="' + VuFind.translate('toggle_dropdown') + '">' +
          VuFind.icon("ui-dots-menu") +
        '</button>' +
      '</div>'
    );
    var $list = $('<ul class="dropdown-menu"></ul>');
    for (var i = 0; i < links.length; i++) {
      var li = $('<li/>');
      li.append(
        $('<a/> ', {
          'href': links[i].url,
          'class': links[i].label,
          'html': '<i class="fa ' + links[i].icon + '"></i> ' + VuFind.translate(links[i].label)
        })
      );
      $list.append(li);
    }
    $cont.append($list);
    $(elem).siblings('.channel-title').append($cont);
  }

  var currentPopoverRecord = false;
  function isCurrentPopoverRecord(record) {
    return record && currentPopoverRecord
      && record.data('record-id') === currentPopoverRecord.data('record-id')
      && record.data('record-source') === currentPopoverRecord.data('record-source')
      && record.data('channel-id') === currentPopoverRecord.data('channel-id');
  }
  function switchPopover(record) {
    // Hide the old popover:
    if (currentPopoverRecord) {
      bootstrap.Popover.getInstance(currentPopoverRecord).hide();
    }
    // Special case: if the new popover is the same as the old one, reset the
    // current popover status so that the next click will open it again (toggle)
    if (isCurrentPopoverRecord(record)) {
      currentPopoverRecord = false;
    } else {
      // Default case: set the currentPopover to the new incoming value:
      currentPopoverRecord = record;
    }
    // currentPopover has now been updated; show it if appropriate:
    if (currentPopoverRecord) {
      bootstrap.Popover.getInstance(currentPopoverRecord).show();
    }
  }

  // Truncate lines to height with ellipses
  function clampLines(el) {
    var words = el.innerHTML.split(" ");
    while (el.scrollHeight > el.offsetHeight) {
      words.pop();
      el.innerHTML = words.join(" ") + VuFind.translate("eol_ellipsis");
    }
  }

  function setupChannelSlider(i, op) {
    $(op).find(".slide").removeClass("hidden");
    $(op).slick({
      slidesToShow: 6,
      slidesToScroll: 6,
      infinite: false,
      rtl: $(document.body).hasClass("rtl"),
      responsive: [
        {
          breakpoint: 768,
          settings: {
            slidesToShow: 3,
            slidesToScroll: 3
          }
        },
        {
          breakpoint: 480,
          settings: {
            slidesToShow: 1,
            slidesToScroll: 1
          }
        }
      ]
    });
    $(op).on('swipe', function channelDrag() {
      switchPopover(false);
    });

    $(op).find('.channel-record').off("click").on("click", function channelRecord(event) {
      var record = $(event.delegateTarget);
      if (!record.data('popover-loaded')) {
        record.data('popover-loaded', true);
        switchPopover(false);
        let loadingPopover = new bootstrap.Popover(
          record,
          {
            content: VuFind.translate('loading_ellipsis'),
            html: true,
            placement: 'bottom',
            trigger: 'manual',
            container: '#' + record.closest('.channel').attr('id')
          }
        );
        loadingPopover.show();
        $.ajax({
          url: VuFind.path + getUrlRoot(record.attr('href')) + '/AjaxTab',
          type: 'POST',
          data: {tab: 'description'}
        })
          .done(function channelPopoverDone(data) {
            var newContent = '<div class="btn-group btn-group-justified">'
              + '<a href="' + VuFind.path + '/Channels/Record?'
              + 'id=' + encodeURIComponent(record.attr('data-record-id'))
              + '&source=' + encodeURIComponent(record.attr('data-record-source'))
              + '" class="btn btn-default">' + VuFind.translate('channel_expand') + '</a>'
              + ' <a href="' + record.attr('href') + '" class="btn btn-default">' + VuFind.translate('View Record') + '</a>'
              + '</div>'
              + data;
            loadingPopover.dispose();
            new bootstrap.Popover(
              record,
              {
                content: newContent,
                html: true,
                placement: 'bottom',
                trigger: 'manual',
                sanitize: false,
                container: '#' + record.closest('.channel').attr('id')
              }
            );
            switchPopover(record);
          });
      } else {
        switchPopover(record);
      }
      return false;
    });

    // Channel add buttons
    addLinkButtons(op);
    $('.channel-add-menu[data-group="' + op.dataset.group + '"].hidden')
      .clone()
      .removeClass('hidden')
      .prependTo($(op).parent(".channel-wrapper"));

    // Fix title overflow
    op.querySelectorAll(".channel-record-title").forEach(clampLines);
  }

  var bindChannelAddMenu; // circular dependency fix for jshint

  function selectAddedChannel(e) {
    $.ajax(e.target.href).done(function addChannelAjaxDone(data) {
      var list = $(e.target).closest('.dropdown-menu');
      var $testEls = $('<div>' + data + '</div>').find('.channel-wrapper');
      var $dest = $(e.target).closest('.channel-wrapper');
      // Remove dropdown link
      $('[data-token="' + e.target.dataset.token + '"]').parent().remove();
      // Insert new channels
      $testEls.each(function addRetrievedNonEmptyChannels(i, element) {
        var $testEl = $(element);
        // Make sure the channel has content
        if ($testEl.find('.channel-record').length === 0) {
          $dest.after(
            '<div class="channel-wrapper">'
            + '<div class="channel-title no-results">'
            + '<h2>' + $testEl.find('h2').html() + '</h2>'
            + VuFind.translate('nohit_heading')
            + '</div></div>'
          );
        } else {
          $dest.after($testEl);
          $testEl.find('.channel').each(setupChannelSlider);
          $testEl.find('.channel').each(bindChannelAddMenu);
        }

        if (list.children().length === 0) {
          $('.channel-add-menu[data-group="' + list.closest('.channel-add-menu').data('group') + '"]').remove();
        }
      });
    });
    return false;
  }

  bindChannelAddMenu = function bindChannelAddMenuFunc(iteration, channel) {
    var scope = $(channel).parent(".channel-wrapper");
    $(scope).find('.channel-add-menu .dropdown-menu a').on("click", selectAddedChannel);
    $(scope).find('.channel-add-menu .add-btn').on("click", function addChannels(e) {
      var links = $(e.target).closest('.channel-add-menu').find('.dropdown-menu a');
      for (var i = 0; i < links.length && i < 2; i++) {
        links[i].click();
      }
    });
  };

  function init () {
    $('.channel').each(setupChannelSlider);
    $('.channel').each(bindChannelAddMenu);
    document.addEventListener('hidden.bs.popover', (e) => {
      if (isCurrentPopoverRecord($(e.target))) {
        switchPopover(false);
      }
    });
    document.addEventListener('mouseup', function onMouseUp(e) {
      // Close any current popover if clicked outside of a popover and a record that triggers one:
      const popover = document.querySelector('.channel-wrapper .channel .popover');
      if (popover && !popover.contains(e.target)) {
        const intersectingRecords = Array.from(document.querySelectorAll('.channel-wrapper .channel .channel-record')).filter(r => r.contains(e.target));
        if (intersectingRecords.length === 0) {
          switchPopover(false);
        }
      }
    });
  }

  return { init: init };
});
