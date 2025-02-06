/*global finna, VuFind */
finna.feedTabs = (function finnaFeedTab() {

  /**
   * Return the location hash without hashtag
   * @returns {string} hash without hashtag
   */
  function getTabFromLocationHash() {
    var hash = window.location.hash;
    return hash ? hash.substring(1) : '';
  }
  /**
   * FeedTab prototype constructor
   * @param {HTMLElement} container Root container
   */
  function FeedTab(container) {
    var _ = this;
    container.classList.add('init-done');
    _.anchors = container.querySelectorAll('.feed-tab-anchor, .feed-accordion-anchor');
    _.tabContent = container.querySelector('finna-feed');
    _.setEvents();
    _.firstLoad();
    _.allowHashChange = false;
    _.isLoading = false;
  }

  /**
   * Set proper events to listen for
   */
  FeedTab.prototype.setEvents = function setEvents() {
    var _ = this;
    _.anchors.forEach(function addClickListener(element) {
      element.addEventListener('click', function onFeedTabClick(e) {
        e.preventDefault();
        _.displayTab(element);
      });
    });

    window.addEventListener('hashchange', function checkForHashChange() {
      if (_.isLoading || !_.allowHashChange) {
        return;
      }
      var hash = getTabFromLocationHash();
      if (hash) {
        _.anchors.forEach(function checkIfThis(element) {
          if (element.classList.contains('feed-tab-anchor') &&
            element.dataset.tab === hash
          ) {
            element.click();
            element.focus();
          }
        });
      }
    });
  };

  /**
   * Display the proper feedtab and accordion tab
   * @param {HTMLElement} element Element to display
   */
  FeedTab.prototype.displayTab = function displayTab(element) {
    var _ = this;

    _.isLoading = true;
    var tab = element.dataset.tab;
    if (window.location.hash !== tab) {
      window.location.hash = tab;
    }

    _.anchors.forEach(function removeActive(el) {
      var parent = el.parentNode;
      if (el.dataset.tab === tab) {
        el.classList.add('active');
        el.setAttribute('aria-selected', true);
        if (el.classList.contains('feed-accordion-anchor')) {
          parent.insertAdjacentElement('afterend', _.tabContent);
        }
      } else {
        el.classList.remove('active');
        el.setAttribute('aria-selected', false);
      }
    });
    _.tabContent.feedId = tab;
    _.tabContent.onFeedLoaded = function onLoad() {
      _.isLoading = false;
      if (!_.allowHashChange) {
        _.allowHashChange = true;
      }
    };
  };

  /**
   * Load first tab page when initialization is completed
   */
  FeedTab.prototype.firstLoad = function firstLoad() {
    var _ = this;
    var hash = getTabFromLocationHash();

    _.anchors.forEach(function checkFirst(element) {
      if (!element.classList.contains('feed-tab-anchor')) {
        return;
      }
      if ((!hash && !_.isLoading && element.classList.contains('active')) ||
        hash === element.dataset.tab
      ) {
        element.click();
      }
    });
    if (_.anchors[0] && !_.isLoading) {
      _.anchors[0].parentNode.click();
    }
  };

  /**
   * Init feedtabs
   * @param {string} id Unique identifier
   */
  function init(id) {
    VuFind.observerManager.createIntersectionObserver(
      'FinnaFeedTabs',
      (element) => {
        new FeedTab(element);
      },
      document.querySelectorAll('.feed-tabs#' + id + ':not(.init-done)')
    );
  }

  var my = {
    init: init
  };

  return my;
})();
