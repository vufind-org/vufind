/*global VuFind, checkSaveStatuses*/
finna.searchTabsRecommendations = (function() {
    var initSearchTabsRecommendations = function() {
        var holder = $('#search-tabs-recommendations-holder');
        if (!holder[0]) {
            return;
        }
        var url = VuFind.path + '/AJAX/JSON?method=getSearchTabsRecommendations';
        var searchId = holder.data('searchId');
        var limit = holder.data('limit');
        var jqxhr = $.getJSON(url, {searchId: searchId, limit: limit})
        .done(function(response) {
            var holder = $('#search-tabs-recommendations-holder');
            holder.html(response.data);
            finna.layout.initTruncate(holder);
            finna.openUrl.initLinks();
            VuFind.lightbox.bind(holder);
            finna.itemStatus.initItemStatuses(holder);
            finna.itemStatus.initDedupRecordSelection(holder);
            checkSaveStatuses(holder);
        })
        .fail(function(response, textStatus) {
            console.log(response, textStatus);
        });
    };

    var my = {
        init: function() {
            initSearchTabsRecommendations();
        }
    };

    return my;

})(finna);
