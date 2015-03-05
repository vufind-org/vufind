finna.layout = (function() {

    var isTouchDevice = function() {
        return !!('ontouchstart' in window)
            || !!('onmsgesturechange' in window); // IE10
    };

    // Append current anchor (location.hash) to selected links
    // in order to preserve the anchor when the link is clicked.
    // This is used in top header language links.
    var initAnchorNavigationLinks = function() {
        $('a.preserve-anchor').each(function() {
            var hash = location.hash;
            if (hash.length == 0) {
                return;
            }
            $(this).attr('href', $(this).attr('href') + hash);
        });
    };

    var initFixFooter = function() {
        var detectHeight = $(window).height() - $('body').height();
        if (detectHeight > 0) {
            var expandedFooter = $('footer').height() + detectHeight;
            $('footer').height(expandedFooter);
        }
    };

    var initOpenUrlLinks = function() {
        var links = $('a.openUrlEmbed');
        links.each(function(ind, e) {
            $(e).one('inview', function() {
                $(this).click();
            });
        });
    };

    var my = {
        isTouchDevice: isTouchDevice,
        init: function() {
            $('select.jumpMenu').unbind('change').change(function(){ $(this).closest('form').submit(); });

            initAnchorNavigationLinks();
            initFixFooter();
            initOpenUrlLinks();
        },
    };

    return my;
})(finna);

