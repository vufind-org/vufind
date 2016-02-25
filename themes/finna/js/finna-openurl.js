/*global embedOpenUrlLinks*/
finna.openUrl = (function() {

    var initLinks = function() {
        $('.openUrlEmbed a').each(function(ind, e) {
            $(e).one('inview', function(){
                embedOpenUrlLinks($(this));
            });
        });
    };

    var my = {
        initLinks: initLinks,
        init: function() {
            initLinks();
        }
    };

    return my;
})(finna);
