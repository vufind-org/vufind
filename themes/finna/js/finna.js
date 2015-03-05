var finna = (function() {

    var my = {
        init: function() {    
            finna.imagePopup.init();
            finna.layout.init();
            if (typeof finna.record !== "undefined") {
                finna.record.init();
            }
        },
    };
    
    return my;
})();

$(document).ready(function() {
    finna.init();
});
