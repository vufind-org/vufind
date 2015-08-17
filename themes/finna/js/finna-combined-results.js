finna.combinedResults = (function() {

    var my = {
        init: function(holder) {
            finna.layout.initTruncate();
            finna.openUrl.initLinks();
            finna.layout.initSaveRecordLinks(holder);
        },
    };

    return my;
})(finna);
