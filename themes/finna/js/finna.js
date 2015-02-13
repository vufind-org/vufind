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

    var my = {
        isTouchDevice: isTouchDevice,
        init: function() {    
            initAnchorNavigationLinks();
            finna.imagePopup.init();
        },
    };
    
    return my;
})();

$(document).ready(function() {
    finna.init();
});
