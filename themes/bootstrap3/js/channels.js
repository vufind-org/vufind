/*global getUrlRoot, htmlEncode, VuFind */
/*exported channelAddLinkButtons */

function channelAddLinkButtons(elem) {
  var links = JSON.parse(elem.dataset.linkJson);
  var $cont = $(
    '<div class="dropdown">' +
    '  <button class="btn btn-link" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">' +
    '    <i class="fa fa-caret-square-o-down"></i>' +
    '   </button>' +
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
    $('[aria-describedby]').popover('hide');
  });
  // truncate long titles and add hover
  $(op).find('.channel-record').dotdotdot({
    callback: function dddcallback(istrunc, orig) {
      if (istrunc) {
        $(this).attr('title', $(orig).text().trim());
      }
    }
  });
  var currentPopover = false;
  $(op).find('.channel-record').unbind('click').click(function channelRecord(event) {
    var record = $(event.delegateTarget);
    if (record.data('popover')) {
      if (record.data('record-id') === currentPopover) {
        record.popover('hide');
        currentPopover = false;
      } else {
        record.popover('show');
        currentPopover = record.data('record-id');
      }
    } else {
      currentPopover = record.data('record-id');
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
    .removeClass('hidden')
    .prependTo($(op).parent(".channel-wrapper"));
}

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

function bindChannelAddMenu(iteration, channel) {
  var scope = $(channel).parent(".channel-wrapper");
  $(scope).find('.channel-add-menu .dropdown-menu a').click(selectAddedChannel);
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
});
