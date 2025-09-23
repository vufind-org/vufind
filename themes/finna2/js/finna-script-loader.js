/* global finna, VuFind */

/**
 * Module for a script loader.
 * Exposes functions:
 * - load
 * - loadInOrder
 * @returns {object} Exposed functions
 */
finna.scriptLoader = (() => {
  /**
   * Load given scripts asynchronously.
   * @param {object}   scripts        Object of scripts to load
   *                                  Key is an unique identifier used to check if
   *                                  script has already been loaded
   *                                  Value is the js file name to load
   * @param {?Function} scriptsLoaded Callback when the scripts are loaded
   */
  function load(scripts, scriptsLoaded = () => {}) {
    let promisesToWait = [];
    const onLoadFunc = (e) => finna.resolvePromise(e.currentTarget.id);
    for (let [key, value] of Object.entries(scripts)) {
      key = `scriptloader-js-${key}`;
      let foundPromise = finna.getPromise(key);
      if (!foundPromise) {
        foundPromise = finna.setPromise(key);
        const scriptElement = document.createElement('script');
        scriptElement.src = `${VuFind.path}/themes/finna2/js/${value}?_=${Date.now()}`;
        scriptElement.async = 'async';
        scriptElement.id = key;
        scriptElement.addEventListener('load', onLoadFunc);
        scriptElement.setAttribute('nonce', VuFind.getCspNonce());
        document.head.appendChild(scriptElement);
      }
      promisesToWait.push(foundPromise);
      // Wait until the promise has resolved (Script has loaded) until loading the next one.
    }
    const handlePromise = (cb) => {
      promisesToWait.shift().then(() => {
        if (promisesToWait.length > 0) {
          handlePromise(cb);
        } else {
          cb();
        }
      });
    };
    handlePromise(scriptsLoaded);
  }

  return {
    load
  };
})();
