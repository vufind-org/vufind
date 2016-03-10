/*global VuFind,checkSaveStatuses,setupSaveRecordLinks*/
finna.combinedResults = (function() {

    var my = {
        init: function(container) {
            finna.layout.initTruncate();
            finna.layout.initAuthorizationNotification(container);
            finna.openUrl.initLinks(container);
            finna.itemStatus.initItemStatuses(container);
            VuFind.lightbox.bind(container);
            checkSaveStatuses(container);
            setupSaveRecordLinks(container);
        },
    };

    return my;
})(finna);
