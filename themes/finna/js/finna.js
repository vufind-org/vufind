var finna = (function() {

    var my = {
        init: function() {
            // List of modules to be inited
            var modules = [
                'advSearch',
                'bx',
                'common',
                'dateRangeVis',
                'feed',
                'feedback',
                'imagePopup',
                'itemStatus',
                'layout',
                'libraryCards',
                'metalibLinks',
                'myList',
                'openUrl',
                'persona',
                'primoAdvSearch',
                'record',
                'searchTabsRecommendations'
            ];

            $.each(modules, function(ind, module) {
                if (typeof finna[module] !== 'undefined') {
                    finna[module].init();
                }
            });
        },
    };

    return my;
})();

$(document).ready(function() {
    finna.init();
    
    // init custom.js for custom theme
    if (typeof finnaCustomInit !== 'undefined') {
        finnaCustomInit();
    }
    
    // Override global checkSaveStatuses
    checkSaveStatuses = finna.layout.checkSaveStatuses;
});
