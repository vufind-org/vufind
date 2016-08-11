/*global getUrlRoot, htmlEncode, VuFind */
$(document).ready(function channelReady() {
  // truncate long titles and add hover
  $('.channel-record').dotdotdot({
    callback: function dddcallback(istrunc, orig) {
      if (istrunc) {
        $(this).attr('title', $(orig).text());
      }
    }
  });
  $('.channel').flickity({
    cellAlign: 'left',
    contain: true,
    freeScroll: true,
    pageDots: false
  });
  $('.channel-record').click(function channelRecord() { return false; });
  $('.channel').on('dragStart', function channelDrag() {
    $('[aria-describedby]').popover('hide');
  });
  $('.channel').on('staticClick', function channelPopover(event, pointer, cellElement/*, cellIndex*/) {
    var record = $(cellElement);
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
  });

  $('.channel-add-menu .dropdown-menu a').click(function selectAddedChannel(e) {
    $.ajax(e.target.href).done(function (data) {
      $(e.target).closest('.channel-add-menu').before(data);
      $(e.target).remove();
      $('.channel').flickity({
        cellAlign: 'left',
        contain: true,
        freeScroll: true,
        pageDots: false
      });
    });
    return false;
  });
  $('.channel-add-menu .add-btn').click(function addChannels(e) {
    var links = $(e.target).parent().find('.dropdown-menu a');
    for (var i=0; i<links.length && i<2; i++) {
      links[i].click();
    }
  });
});
