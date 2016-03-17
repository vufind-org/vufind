var finna = (function() {

    var my = {
        init: function() {
            // List of modules to be inited
            var modules = [
                'advSearch',
                'autocomplete',
                'bx',
                'common',
                'changeHolds',
                'dateRangeVis',
                'feed',
                'feedback',
                'imagePopup',
                'itemStatus',
                'layout',
                'metalibLinks',
                'myList',
                'openUrl',
                'persona',
                'primoAdvSearch',
                'record',
                'searchTabsRecommendations',
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
});
