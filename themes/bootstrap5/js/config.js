/*global VuFind */
VuFind.register('config', function Config() {
  var _config = {};

  function add(config) {
    for (var i in config) {
      if (Object.prototype.hasOwnProperty.call(config, i)) {
        _config[i] = config[i];
      }
    }
  }

  function get(key, defaultValue = null) {
    if (_config[key] === undefined) {
      return defaultValue;
    }
    return _config[key];
  }

  return {
    add: add,
    get: get,
  };
});
