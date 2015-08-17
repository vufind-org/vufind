finna.openUrl = (function() {

    var loadResolverLinks = function($target, openUrl, searchClassId) {
        $target.addClass('ajax_availability');

        var url = path + '/AJAX/JSON?' + $.param({method:'getResolverLinks',openurl:openUrl,searchClassId:searchClassId});
        $.ajax({
            dataType: 'json',
            url: url,
            success: function(response) {
                if (response.status == 'OK') {
                    $target.removeClass('ajax_availability')
                        .empty().append(response.data);
                } else {
                    $target.removeClass('ajax_availability').addClass('error')
                        .empty().append(response.data);
                }
            }
        });
    };

    var embedOpenUrlLinks = function (element) {
        var params = extractClassParams(this);
        var openUrl = element.children('span.openUrl:first').attr('title');
        element.removeClass('openUrlEmbed').hide();
        loadResolverLinks(element.next('div.resolver').removeClass('hidden'), openUrl, params.searchClassId);
    }

    var initLinks = function() {
        $('a.openUrlEmbed').each(function(ind, e) {
            $(e).one("inview", function(){
                embedOpenUrlLinks($(this));
            });
        });
    };

    var my = {
        initLinks: initLinks,
        init: function() {
            initLinks();
        },
    };

    return my;
})(finna);
