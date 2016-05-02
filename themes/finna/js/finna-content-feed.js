/*global VuFind*/
finna.contentFeed = (function() {
    var loadFeed = function(container, modal) {
        var id = container.data('feed');
        var num = container.data('num');

        var contentHolder = container.find('.holder');
        // Append spinner
        contentHolder.append('<i class="fa fa-spin fa-spinner"></i>');
        contentHolder.find('.fa-spin').fadeOut(0).delay(1000).fadeIn(100);

        var url = VuFind.path + '/AJAX/JSON';
        var params = {method: 'getContentFeed', id: id, num: num};

        $.getJSON(url, params)
        .done(function(response) {
            if (response.data) {
                var data = response.data;
                if (typeof data.item != 'undefined' && typeof data.item.html != 'undefined') {
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
                } else {
                    contentHolder.empty().append(
                        $('<div/>').addClass('error').text(VuFind.translate('error_occurred'))
                    );
                }

                if (!modal) {
                    if (typeof data.navigation != 'undefined') {
                        $('.article-navigation-wrapper').html(data.navigation);
                        $('.article-navigation-header').show();
                    }
                }
            }
        })
        .fail(function(response, textStatus, err) {
            var err = '<!-- Feed could not be loaded';
            if (typeof response.responseJSON != 'undefined') {
                err += ': ' + response.responseJSON.data;
            }
            err += ' -->';
            contentHolder.html(err);
        });

        $('#modal').one('hidden.bs.modal', function() {
            $(this).removeClass('feed-content');
        });
    };

    var my = {
        loadFeed: loadFeed,
        init: function() {
            loadFeed($('.feed-content'), false);
        }
    };

    return my;
})(finna);
