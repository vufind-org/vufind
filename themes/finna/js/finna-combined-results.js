finna.combinedResults = (function() {

    var my = {
        init: function(holder) {
            finna.layout.initTruncate();
            finna.layout.initAuthorizationNotification(holder);
            finna.openUrl.initLinks();
            finna.layout.initSaveRecordLinks(holder);
            finna.layout.checkSaveStatuses(holder);
            finna.layout.initLightbox(holder);
        },
    };

    return my;
})(finna);
