var finna = (function() {

    // Append current anchor (location.hash) to selected links 
    // in order to preserve the anchor when the link is clicked.
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
        init: function() {    
            initAnchorNavigationLinks();
        },
    };
    
    return my;
})();

$(document).ready(function() {
    finna.init();
});
