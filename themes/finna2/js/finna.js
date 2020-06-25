/*global finnaCustomInit */
/*exported finna */
var finna = (function finnaModule() {

  var my = {
    init: function init() {
      // List of modules to be inited
      var modules = [
        'advSearch',
        'authority',
        'autocomplete',
        'contentFeed',
        'common',
        'changeHolds',
        'dateRangeVis',
        'feed',
        'feedback',
        'imagePopup',
        'itemStatus',
        'layout',
        'menu',
        'myList',
        'openUrl',
        'organisationList',
        'primoAdvSearch',
        'record',
        'searchTabsRecommendations',
        'StreetSearch',
        'finnaSurvey',
        'multiSelect'
      ];

      $.each(modules, function initModule(ind, module) {
        if (typeof finna[module] !== 'undefined') {
          finna[module].init();
        }
      });
    }
  };

  return my;
})();

$(document).ready(function onReady() {
  finna.init();

  // init custom.js for custom theme
  if (typeof finnaCustomInit !== 'undefined') {
    finnaCustomInit();
  }
});
