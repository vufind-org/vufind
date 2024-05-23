/*global VuFind*/

/**
 * Manager for creating observers.
 */
VuFind.register('observerManager', () => {
  let observers = {};

  /**
   * Observe given elements. Observer used is identified with identifier.
   *
   * @param {String}         identifier Observers identifier
   * @param {Array|NodeList} elements   Elements to observe
   */
  function observe(identifier, elements) {
    if (typeof observers[identifier] === 'undefined') {
      console.error(`Observer with identifier ${identifier} is undefined`);
      return;
    }
    for (let i = 0; i < elements.length; i++) {
      const current = elements[i];
      if (typeof observers[identifier].observer !== 'undefined') {
        observers[identifier].observer.observe(current);
      } else {
        observers[identifier].function(current);
      }
    }
  }

  /**
   * Create an IntersectionObserver.
   * If the IntersectionObserver is not supported, onIntersect will be used as a
   * standalone function.
   *
   * @link https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API 
   *
   * @param {String}         identifier  Id of the observer to create
   * @param {Function}       onIntersect Callback to use on elements
   * @param {Array|NodeList} elements    Initial elements to be observed
   * @param {Object}         options     Options for the Intersection Observer
   */
  function createIntersectionObserver(identifier, onIntersect, elements, options) {
    if (typeof observers[identifier] === 'undefined') {
      observers[identifier] = {
        function: onIntersect
      };
      if (('IntersectionObserver' in window) &&
        ('IntersectionObserverEntry' in window) &&
        ('isIntersecting' in window.IntersectionObserverEntry.prototype) &&
        ('intersectionRatio' in window.IntersectionObserverEntry.prototype)
      ) {
        observers[identifier].observer = new IntersectionObserver(
          (entries, obs) => {
            entries.forEach((entry) => {
              if (entry.isIntersecting) {
                onIntersect(entry.target);
                obs.unobserve(entry.target);
              }
            }); 
          }, options);
      }
    } else if (observers[identifier].function.toString() !== onIntersect.toString()) {
      console.warn(
        `Attempt to reinitialize intersection observer with identifier: ${identifier}
        using different callback.`
      );
    }

    if (typeof elements !== 'undefined' && elements.length) {
      observe(identifier, elements);
    }
  }

  /**
   * Remove an observer.
   *
   * @param {String} identifier Identifier of observer to remove
   */
  function disconnect(identifier) {
    if (typeof observers[identifier] !== 'undefined'
      && typeof observers[identifier].observer !== 'undefined'
    ) {
      observers[identifier].observer.disconnect();
    }
    delete observers[identifier];
  }

  return { createIntersectionObserver, observe, disconnect };
});
