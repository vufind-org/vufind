/*global VuFind*/
finna.common = (function() {
    var initSearchInputListener = function() {
        var searchInput = $('.searchForm_lookfor:visible');
        if (searchInput.length == 0) {
            return;
        }
        $(window).keypress(function(e) {
            if (e && (!$(e.target).is('input, textarea, select')) 
                  && !$('#modal').is(':visible') 
                  && (e.which >= 48) // Start from normal input keys
                  && !(e.metaKey || e.ctrlKey || e.altKey)
            ) {
                var letter = String.fromCharCode(e.which);
                
                // IE 8-9
                if (typeof document.createElement('input').placeholder == 'undefined') {
                    if (searchInput.val() == searchInput.attr('placeholder')) {
                      searchInput.val('');
                      searchInput.removeClass('placeholder');
                    }
                }
                
                // Move cursor to the end of the input
                var tmpVal = searchInput.val();
                searchInput.val(' ').focus().val(tmpVal + letter);
                
                // Scroll to the search form
                $('html, body').animate({scrollTop: searchInput.offset().top - 20}, 150);
               
                e.preventDefault();
           }
        });
    }
    
    var my = {
        init: function() {
            initSearchInputListener();
        }
    };

    return my;

})(finna);
