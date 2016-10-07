/*global getUrlRoot, htmlEncode, VuFind */

function bindChannelAddMenu(scope) {
  $(scope).find('.channel-add-menu .dropdown-menu a').click(function selectAddedChannel(e) {
    $.ajax(e.target.href).done(function addChannelAjaxDone(data) {
      $(e.target).closest('.channel').after(data);
      $('[data-token="' + e.target.dataset.token + '"]').remove();
      $('.channel').each(setupChannelSlider);
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

function setupChannelSlider(i, op) {
  if (VuFind.slider(op)) {
    $(op).find('.thumb').each(function thumbnailBackgrounds(index, thumb) {
      var img = $(thumb).find('img');
      $(thumb).css('background-image', 'url(' + img.attr('src') + ')');
      img.remove();
    });
    $('.channel-add-menu[data-group="' + op.dataset.group + '"]')
      .clone()
      .removeAttr('data-group')
      .addClass('pull-right')
      .removeClass('hidden')
      .appendTo($(op).find('.slider-menu'));
    bindChannelAddMenu(op);
  }
}

$(document).ready(function channelReady() {
  // truncate long titles and add hover
  $('.channel-record').dotdotdot({
    callback: function dddcallback(istrunc, orig) {
      if (istrunc) {
        $(this).attr('title', $(orig).text());
      }
    }
  });
  $('.channel').each(setupChannelSlider);
  $('.channel').on('dragStart', function channelDrag() {
    $('[aria-describedby]').popover('hide');
  });
  $('.channel-record').click(function channelRecord(event) {
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
          + '" class="btn btn-default">More Like This</a>'
          + '<a href="' + record.attr('href') + '" class="btn btn-default">Go To Record</a>'
          + '</div>'
          + data;
        record.popover('show');
      });
    }
    return false;
  });
});
