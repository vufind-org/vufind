/*global VuFind*/
finna.common = (function() {

    var decodeHtml = function(str) {
        return $("<textarea/>").html(str).text();
    };

    var getField = function(obj, field) {
        if (field in obj && typeof obj[field] != 'undefined') {
            return obj[field];
        }
        return null;
    };

    var initSearchInputListener = function() {
        var searchInput = $('.searchForm_lookfor:visible');
        if (searchInput.length == 0) {
            return;
        }
        $(window).keypress(function(e) {
            if (e && (!$(e.target).is('input, textarea, select')) 
                  && !$(e.target).hasClass('dropdown-toggle') // Bootstrap dropdown
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
    };
    
    var my = {
        decodeHtml: decodeHtml,
        getField: getField,
        init: function() {
            initSearchInputListener();
        }
    };

    return my;

})(finna);
