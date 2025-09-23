/*global finnaCustomInit */
/*exported finna */
var finna = (function finnaModule() {

  /**
   * Object which holds resolves, key is the name for the promise to resolve.
   * @member {object}
   */
  let resolves = {};

  /**
   * Object which holds promises, key is the name for the promise to wait for.
   * @member {object}
   */
  let promises = {
    lazyImages: new Promise((resolve) => { resolves.lazyImages = resolve; })
  };

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
        'feedback',
        'fines',
        'itemStatus',
        'layout',
        'menu',
        'myList',
        'openUrl',
        'primoAdvSearch',
        'record',
        'searchTabsRecommendations',
        'StreetSearch',
        'finnaSurvey',
        'multiSelect',
        'finnaMovement',
        'mdEditable',
        'a11y',
        'finnaDatepicker',
        'reservationList',
      ];

      $.each(modules, function initModule(ind, module) {
        if (typeof finna[module] !== 'undefined') {
          finna[module].init();
        }
      });
    },
    resolvePromise: (name) => {
      if (resolves[name]) {
        resolves[name]();
      }
    },
    getPromise: (name) => {
      return promises[name];
    },
    setPromise: (name) => {
      if (!promises[name]) {
        promises[name] = new Promise((resolve) => resolves[name] = resolve);
      }
      return promises[name];
    }
  };

  return my;
})();

$(function onReady() {
  finna.init();

  // init custom.js for custom theme
  if (typeof finnaCustomInit !== 'undefined') {
    finnaCustomInit();
  }
});
