/*global VuFind, finna, checkSaveStatuses */
finna.searchTabsRecommendations = (function finnaSearchTabsRecommendations() {
  function initSearchTabsRecommendations() {
    var holder = $('#search-tabs-recommendations-holder');
    if (!holder[0]) {
      return;
    }
    var url = VuFind.path + '/AJAX/JSON?method=getSearchTabsRecommendations';
    var searchId = holder.data('searchId');
    if (!searchId) {
      return;
    }
    var limit = holder.data('limit');
    $.getJSON(url, {searchId: searchId, limit: limit})
      .done(function getRecommendationsDone(response) {
        var container = $('#search-tabs-recommendations-holder');
        container.html(response.data.html);
        finna.layout.initTruncate(container);
        finna.openUrl.initLinks();
        VuFind.lightbox.bind(container);
        VuFind.itemStatuses.check(container);
        finna.itemStatus.initDedupRecordSelection(container);
        checkSaveStatuses(container);
      });
  }

  var my = {
    init: function init() {
      initSearchTabsRecommendations();
    }
  };

  return my;
})();
