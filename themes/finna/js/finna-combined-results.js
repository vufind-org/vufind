/*global VuFind,checkSaveStatuses,setupSaveRecordLinks*/
finna.combinedResults = (function() {

    var my = {
        init: function(holder) {
            finna.layout.initTruncate();
            finna.layout.initAuthorizationNotification(holder);
            finna.openUrl.initLinks(holder);
            finna.layout.initLightbox(holder);
            checkSaveStatuses(holder);
            setupSaveRecordLinks(holder);
        },
    };

    return my;
})(finna);
