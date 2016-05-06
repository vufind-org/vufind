$(document).ready(function channelReady() {
  $('.channel').flickity({
    cellAlign: 'left',
    contain: true,
    pageDots: false
  });
  $('.channel-record').click(function channelRecord() { return false; });
  $('.channel').on('staticClick', function channelPopover(event, pointer, cellElement, cellIndex) {
    var record = $(cellElement);
    if (record.data('popover')) {
      record.popover('toggle');
    } else {
      record.data('popover', true);
      record.popover({
        content: VuFind.translate('loading')+'...',
        html: true,
        placement: 'bottom',
        trigger: 'focus',
        container: '#'+record.closest('.channel').attr('id')
      });
      record.popover('show');
      $.ajax({
        url: VuFind.path + getUrlRoot(record.attr('href')) + '/AjaxTab',
        type: 'POST',
        data: {tab: 'description'}
      })
      .done(function channelPopoverDone(data) {
        record.data('bs.popover').options.content = '<h2>'+record.text()+'</h2>'
          + '<div class="btn-group btn-group-justified">'
          + '<a href="'+VuFind.path+'/Channels/Record?'
            + 'id=' + encodeURIComponent(record.attr('data-record-id'))
            + '&source=' + encodeURIComponent(record.attr('data-record-source'))
          +'" class="btn btn-default">More Like This</a>'
          + '<a href="'+record.attr('href')+'" class="btn btn-default">Go To Record</a>'
          + '</div>'
          + data;
        record.popover('show');
      });
    }
  });
});