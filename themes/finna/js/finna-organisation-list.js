/*global VuFind*/
finna.organisationList = (function() {
    var initOrganisationPageLinksForParticipants = function() {
        var ids = $.makeArray($('.organisations .page-link').not('.done').map(function() {
            return $(this).data('organisation');
        }));
        if (!ids.length) {
            return;
        }
        finna.layout.getOrganisationPageLink(ids, false, false, function(response) {
            if (response) {
                $.each(response, function(id, url) {
                    var link = $('.organisations .page-link[data-organisation="' + id + '"]');
                    link.wrap($('<a/>').attr('href', url));
                });
            }
        });
    };
    var my = {
        init: function() {
            initOrganisationPageLinksForParticipants();
        }
    };

    return my;
})(finna);
