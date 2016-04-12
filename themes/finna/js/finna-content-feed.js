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
                contentHolder.html(response.data.html);
                var title = response.data.title;

                if (!modal) {
                    $('.content-header').text(title);
                    document.title = title + ' | ' + document.title;
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
