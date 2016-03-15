/*global VuFind, checkSaveStatuses*/
finna.combinedResults = (function() {

    var my = {
        init: function(container) {
            finna.layout.initTruncate();
            finna.openUrl.initLinks(container);
            finna.itemStatus.initItemStatuses(container);
            VuFind.lightbox.bind(container);
            checkSaveStatuses(container);
        },
    };

    return my;
})(finna);
