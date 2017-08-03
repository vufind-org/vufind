/*global ChannelSlider, getUrlRoot, htmlEncode, VuFind */
/*exported channelAddLinkButtons */

function channelAddLinkButtons(elem) {
  var links = JSON.parse(elem.dataset.linkJson);
  var $cont = $('<div class="channel-links pull-left"></div>');
  for (var i = 0; i < links.length; i++) {
    $cont.append(
      $('<a/> ', {
        'href': links[i].url,
        'class': links[i].label + " btn btn-default",
        'html': '<i class="fa ' + links[i].icon + '"></i> ' + VuFind.translate(links[i].label)
      })
    );
  }
  $('#' + elem.id + ' .slider-menu').append($cont);
}

function setupChannelSlider(i, op) {
  if (ChannelSlider(op)) {
    $(op).find('.thumb').each(function thumbnailBackgrounds(index, thumb) {
      var img = $(thumb).find('img');
      $(thumb).css('background-image', 'url(' + img.attr('src') + ')');
      img.remove();
    });
    // truncate long titles and add hover
    $(op).find('.channel-record').dotdotdot({
      callback: function dddcallback(istrunc, orig) {
        if (istrunc) {
          $(this).attr('title', $(orig).text());
        }
      }
    });
    $('.channel-record').unbind('click').click(function channelRecord(event) {
      var record = $(event.delegateTarget);
      if (record.data('popover')) {
        if (record.attr('aria-describedby')) {
          record.popover('hide');
        } else {
          $('[aria-describedby]').popover('hide');
          record.popover('show');
        }
      } else {
        record.data('popover', true);
        record.popover({
          content: VuFind.translate('loading') + '...',
          html: true,
          placement: 'bottom',
          trigger: 'focus',
          container: '#' + record.closest('.channel').attr('id')
        });
        $('[aria-describedby]').popover('hide');
        record.popover('show');
        $.ajax({
          url: VuFind.path + getUrlRoot(record.attr('href')) + '/AjaxTab',
          type: 'POST',
          data: {tab: 'description'}
        })
        .done(function channelPopoverDone(data) {
          record.data('bs.popover').options.content = '<h2>' + htmlEncode(record.text()) + '</h2>'
            + '<div class="btn-group btn-group-justified">'
            + '<a href="' + VuFind.path + '/Channels/Record?'
              + 'id=' + encodeURIComponent(record.attr('data-record-id'))
              + '&source=' + encodeURIComponent(record.attr('data-record-source'))
            + '" class="btn btn-default">' + VuFind.translate('channel_expand') + '</a>'
            + '<a href="' + record.attr('href') + '" class="btn btn-default">' + VuFind.translate('View Record') + '</a>'
            + '</div>'
            + data;
          record.popover('show');
        });
      }
      return false;
    });
    // Channel add buttons
    channelAddLinkButtons(op);
    $('.channel-add-menu[data-group="' + op.dataset.group + '"].hidden')
      .clone()
      .addClass('pull-right')
      .removeClass('hidden')
      .appendTo($(op).find('.slider-menu'));
  }
}

function bindChannelAddMenu(iteration, scope) {
  $(scope).find('.channel-add-menu .dropdown-menu a').click(function selectAddedChannel(e) {
    $.ajax(e.target.href).done(function addChannelAjaxDone(data) {
      var list = $(e.target).closest('.dropdown-menu');
      var $testEl = $(data);
      // Make sure the channel has content
      if ($testEl.find('.channel-record').length === 0) {
        $(e.target).closest('.channel').after(
          '<div class="channel-title no-results">'
          + '<h2>' + $testEl.find('h2').html() + '</h2>'
          + VuFind.translate('nohit_heading')
          + '</div>'
        );
      } else {
        $(e.target).closest('.channel').after(data);
        $('.channel').each(setupChannelSlider);
        $('.channel').each(bindChannelAddMenu);
      }
      // Remove dropdown link
      $('[data-token="' + e.target.dataset.token + '"]').parent().remove();

      if (list.children().length === 0) {
        $('.channel-add-menu[data-group="' + list.closest('.channel-add-menu').data('group') + '"]').remove();
      }
    });
    return false;
  });
  $(scope).find('.channel-add-menu .add-btn').click(function addChannels(e) {
    var links = $(e.target).closest('.channel-add-menu').find('.dropdown-menu a');
    for (var i = 0; i < links.length && i < 2; i++) {
      links[i].click();
    }
  });
}

$(document).ready(function channelReady() {
  $('.channel').each(setupChannelSlider);
  $('.channel').each(bindChannelAddMenu);
  $('.channel').on('dragStart', function channelDrag() {
    $('[aria-describedby]').popover('hide');
  });
});
