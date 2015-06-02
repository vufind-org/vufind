var finna = (function() {

    var my = {
        init: function() {
            // List of modules to be inited
            var modules = [
                'feed', 'imagePopup', 'feedback', 'layout', 'myList', 'record', 'persona', 'common'
            ];

            $.each(modules, function(ind, module) {
                if (typeof finna[module] !== 'undefined') {
                    finna[module].init();
                }
            });
        },
    };
    
    return my;
})();

$(document).ready(function() {
    finna.init();
});
