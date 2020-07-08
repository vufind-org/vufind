/*global VuFind, finna, checkSaveStatuses*/
finna.combinedResults = (function finnaCombinedResults() {
  var my = {
    init: function init(container) {
      finna.layout.initTruncate();
      finna.openUrl.initLinks(container);
      VuFind.itemStatuses.check(container);
      finna.record.initRecordVersions(container);
      VuFind.lightbox.bind(container);
      VuFind.cart.init(container);
      checkSaveStatuses(container);
    }
  };

  return my;
})();
