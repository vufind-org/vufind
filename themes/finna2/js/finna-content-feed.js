/*global VuFind, finna */
finna.contentFeed = (function finnaContentFeed() {
  function loadFeed(container, modal) {
    var id = container.data('feed');
    var element = container.data('element');
    var feedUrl = container.data('feed-url');

    var contentHolder = container.find('.holder');

    if (!id || !element || contentHolder.length === 0) {
      return;
    }

    // Append spinner
    contentHolder.append('<i class="fa fa-spin fa-spinner"></i>');
    contentHolder.find('.fa-spin').fadeOut(0).delay(1000).fadeIn(100);

    var url = VuFind.path + '/AJAX/JSON';
    var params = {method: 'getContentFeed', id: id, element: element};
    if (feedUrl) {
      params.feedUrl = feedUrl;
    }

    $.getJSON(url, params)
      .done(function onContentGetDone(response) {
        if (response.data) {
          var data = response.data;
          if (typeof data.item !== 'undefined' && typeof data.item.html !== 'undefined') {
            var item = data.item;
            contentHolder.html(item.html);
            var title = item.title;
            if (!modal) {
              $('.content-header').text(title);
              document.title = title + ' | ' + document.title;
            }
            if (typeof item.contentDate != 'undefined') {
              container.find('.date span').text(item.contentDate);
              container.find('.date').css('display', 'inline-block');
            }
            if (typeof item.xcal != 'undefined') {
              $.each(item.xcal, function addXcal(key, value) {
                var field = container.find('.xcal-' + key);
                if (key === 'organizer-url' || key === 'url') {
                  field.find('.xcal-value').attr('href', value);
                } else if (key === 'featured') {
                  container.find('.xcal-featured').attr('src', value);
                } else if (key === 'endDate' && value === item.xcal.startDate) {
                  return true;
                } else if ((key === 'startTime' || key === 'endTime') && item.xcal.startDate !== item.xcal.endDate) {
                  return true;
                } else if (key === 'startDate' || key === 'startTime' || key === 'endDate' || key === 'endTime') {
                  field.find('.xcal-value .xcal-' + key).append(value);
                  field.find('.xcal-value .xcal-' + key).removeClass('hidden');
                  field.removeClass('hidden');
                  return true;
                }
                field.find('.xcal-value').text(value);
                field.removeClass('hidden');
              });
            }
          } else {
            var err = $('<div/>').addClass('alert alert-danger');
            err.append($('<p/>').text(VuFind.translate('rss_article_not_found')));
            err.append($('<a/>')
              .attr('href', data.channel.link)
              .text(VuFind.translate('rss_article_channel_link').replace('%title%', data.channel.title)));
            contentHolder.empty().append(err);
          }

          if (!modal) {
            if (typeof data.navigation != 'undefined') {
              $('.article-navigation-wrapper').html(data.navigation);
              $('.article-navigation-header').show();
            }
          }
        }
      })
      .fail(function onContentGetFail(response/*, textStatus, err*/) {
        var err = '<!-- Feed could not be loaded';
        if (typeof response.responseJSON !== 'undefined') {
          err += ': ' + response.responseJSON.data;
        }
        err += ' -->';
        contentHolder.html(err);
      });

    $('#modal').one('hidden.bs.modal', function onContentModalHidden() {
      $(this).removeClass('feed-content');
    });
  }

  var my = {
    loadFeed: loadFeed,
    init: function init() {
      if ($('.feed-content').length > 0) {
        loadFeed($('.feed-content'), false);
      }
    }
  };

  return my;
})();
