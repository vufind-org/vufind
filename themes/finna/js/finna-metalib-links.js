finna.metalibLinks = (function() {
    var initSearchLinks = function() {
        $('.metalib-link').each(function(ind, e) {
            $(this).one('inview', function() {
                checkSearchLink($(this));
            });
        });
    };
    var checkSearchLink = function(link) {
        var parent = link;
        var jqxhr = $.getJSON(path + '/AJAX/JSON?method=metalibLinks', {id: [link.data('ird')]}, function(response) {
            if (response.status == 'OK') {
                $(response.data).each(function(ind, ird) {
                    parent.find('.loading').remove();
                    var link = parent.find("." + ird.status);
                    if (link.length) {
                        link.removeClass("hide");
                    }
                });
            } else {
                link.text("MetaLib link check failed.");
            }
        });
    };

    var my = {
        initSearchLinks: initSearchLinks,
        init: function() {
            initSearchLinks();
        }
    };

    return my;
})(finna);
