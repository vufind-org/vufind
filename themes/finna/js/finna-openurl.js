/*global VuFind*/
finna.openUrl = (function() {

    var loadResolverLinks = function($target, openUrl, searchClassId) {
        $target.addClass('ajax_availability');

        var url = VuFind.getPath() + '/AJAX/JSON?' + $.param({method:'getResolverLinks',openurl:openUrl,searchClassId:searchClassId});
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
        // Extract the OpenURL associated with the clicked element:
        var openUrl = element.children('span.openUrl:first').attr('title');

        // Hide the controls now that something has been clicked:
        var controls = element.parents('.openUrlControls');
        controls.removeClass('openUrlEmbed').addClass('hidden');

        // Locate the target area for displaying the results:
        var target = controls.next('div.resolver');

        // If the target is already visible, a previous click has populated it;
        // don't waste time doing redundant work.
        if (target.hasClass('hidden')) {
            loadResolverLinks(target.removeClass('hidden'), openUrl);
        }
    };

    var initLinks = function() {
        $('.openUrlEmbed a').each(function(ind, e) {
            $(e).one("inview", function(){
                embedOpenUrlLinks($(this));
            });
        });
    };

    var my = {
        initLinks: initLinks,
        init: function() {
            initLinks();
        }
    };

    return my;
})(finna);
