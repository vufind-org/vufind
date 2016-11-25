var finna = (function() {

    var my = {
        init: function() {
            // List of modules to be inited
            var modules = [
                'advSearch',
                'autocomplete',
                'contentFeed',
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
                'organisationList',
                'persona',
                'primoAdvSearch',
                'record',
                'searchTabsRecommendations',
                'StreetSearch',
                'finnaSurvey'
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
