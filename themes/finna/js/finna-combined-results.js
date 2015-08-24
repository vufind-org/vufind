finna.combinedResults = (function() {

    var my = {
        init: function(holder) {
            finna.layout.initTruncate();
            finna.layout.initAuthorizationNotification();
            finna.openUrl.initLinks();
            finna.layout.initSaveRecordLinks(holder);
            finna.layout.checkSaveStatuses(holder);
        },
    };

    return my;
})(finna);
