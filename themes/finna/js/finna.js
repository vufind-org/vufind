var finna = (function() {

    var my = {
        init: function() {    
            finna.imagePopup.init();
            finna.layout.init();
        },
    };
    
    return my;
})();

$(document).ready(function() {
    finna.init();
});
