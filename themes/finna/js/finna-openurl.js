/*global VuFind*/
finna.openUrl = (function() {
    var initLinks = function() {
        $('.openUrlEmbed a').each(function(ind, e) {
            $(e).one('inview', function(){
                VuFind.openurl.embedOpenUrlLinks($(this));
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
