/*global VuFind, finna */
finna.openUrl = (function finnaOpenUrl() {
  function initLinks() {
    $('.openUrlEmbed a').each(function initOpenUrlEmbed(ind, e) {
      $(e).one('inview', function onInViewOpenUrl() {
        VuFind.openurl.embedOpenUrlLinks($(this));
      });
    });
  }

  var my = {
    initLinks: initLinks,
    init: function init() {
      initLinks();
    }
  };

  return my;
})();
