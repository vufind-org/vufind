/*global finna */
finna.organisationList = (function finnaOrganisationList() {
  function initOrganisationPageLinksForParticipants() {
    var ids = $.makeArray($('.organisations .page-link').not('.done').map(function getId() {
      return $(this).data('organisation');
    }));
    if (!ids.length) {
      return;
    }
    finna.layout.getOrganisationPageLink(ids, false, false, function onGetOrganisationPageLink(response) {
      if (response) {
        $.each(response, function handleLink(id, url) {
          var link = $('.organisations .page-link[data-organisation="' + id + '"]');
          link.wrap($('<a/>').attr('href', url));
        });
      }
    });
  }

  var my = {
    init: function init() {
      initOrganisationPageLinksForParticipants();
    }
  };

  return my;
})();
