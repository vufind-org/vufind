var finna = (function() {

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
        var detectHeight = $(window).height() - $('header').height() - $('section.searchlayout').height() - $('section.main').height() - $('footer').height();
        if (detectHeight > 0) {
            var expandedFooter = $('footer').height() + detectHeight;
            $('footer').height(expandedFooter);
            }
    };
    
    var my = {
        isTouchDevice: isTouchDevice,
        init: function() {    
            initAnchorNavigationLinks();
            initFixFooter();
            finna.imagePopup.init();
        },
    };
    
    return my;
})();

$(document).ready(function() {
    finna.init();
});
