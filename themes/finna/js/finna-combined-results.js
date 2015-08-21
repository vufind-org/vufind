finna.combinedResults = (function() {

    var my = {
        init: function(holder) {
            finna.layout.initTruncate();
            finna.layout.initAuthorizationNotification();
            finna.openUrl.initLinks(holder);
            finna.openUrl.triggerAutoLoad();
            finna.layout.initSaveRecordLinks(holder);
            finna.layout.checkSaveStatuses(holder);
        },
    };

    return my;
})(finna);
