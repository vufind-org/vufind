/* global finna */
finna.recommendationMemory = (function finnaRecommendationMemory() {
  function b64EncodeUnicode(str) {
    return btoa(encodeURIComponent(str).replace(/%([0-9A-F]{2})/g, function replacer(match, p1) {
      return String.fromCharCode(parseInt(p1, 16));
    }));
  }

  function getDataString(srcMod, rec, orig, recType) {
    var data = {
      'srcMod': srcMod,
      'rec': rec,
      'orig': orig,
      'recType': recType
    };
    return b64EncodeUnicode(JSON.stringify(data));
  }

  var my = {
    PARAMETER_NAME: 'rmKey',
    getDataString: getDataString
  };

  return my;
})();
