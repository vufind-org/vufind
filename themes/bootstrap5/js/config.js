/*global VuFind */
VuFind.register('config', function Config() {
  var _config = {};

  /**
   * Adds new configuration properties to the internal store.
   * @param {object} config - An object containing key-value pairs of configuration settings.
   */
  function add(config) {
    for (var i in config) {
      if (Object.prototype.hasOwnProperty.call(config, i)) {
        _config[i] = config[i];
      }
    }
  }

  /**
   * Retrieves a configuration value by its key.
   * @param {string} key - The key of the configuration property to retrieve.
   * @param {*}      [defaultValue=null] - The default value to return if the key is not found.
   * @returns {*} The configuration value, or `defaultValue` if not found.
   */
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
