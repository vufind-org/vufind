/*global finna */
finna.feedTabs = (function finnaFeedTab() {
  function getTabContainer(tabs) {
    return tabs.find('.tab-content');
  }

  function loadFeed(tabs, tabId) {
    var tabContainer = getTabContainer(tabs);
    var feedContainer = tabContainer.find('.feed-container');
    feedContainer.data('init', null);
    feedContainer.data('feed', tabId);
    finna.feed.loadFeed(feedContainer);
  }

  function keyHandler(e/*, cb*/) {
    if (e.which === 13 || e.which === 32) {
      $(e.target).click();
      e.preventDefault();
      return false;
    }
    return true;
  }

  function toggleAccordion(container, accordion) {
    var tabContent = container.find('.tab-content').detach();
    var tabId = accordion.data('tab');
    var loadContent = false;
    var accordions = container.find('.feed-accordions');
    if (!accordion.hasClass('active') || accordion.hasClass('initial-active')) {
      accordions.find('.accordion.active')
        .removeClass('active')
        .attr('aria-selected', false);

      container.find('.feed-tab.active')
        .removeClass('active')
        .attr('aria-selected', false);

      accordions.toggleClass('all-closed', false);

      accordion
        .addClass('active')
        .attr('aria-selected', true);

      container.find('.feed-tab[data-tab="' + tabId + '"]')
        .addClass('active')
        .attr('aria-selected', true);

      loadContent = true;
    }
    tabContent.insertAfter(accordion);
    accordion.removeClass('initial-active');

    return loadContent;
  }

  function loadFeedTabs(container) {
    if (container.hasClass('inited')) {
      return;
    }
    container.addClass('inited');
    container.tab('show');

    // Init feed tabs
    container.find('li.nav-item').click(function feedTabClick() {
      var tabId = $(this).data('tab');
      var li = $(this).closest('li');
      if (li.hasClass('active') && !li.hasClass('initial-active')) {
        return false;
      }
      li.removeClass('initial-active');

      getTabContainer(container).removeClass('active');

      var accordion = container.find('.feed-accordions .accordion[data-tab="' + tabId + '"]');
      if (toggleAccordion(container, accordion)) {
        loadFeed(container, tabId);
      }

      return false;
    }).keyup(function onKeyUp(e) {
      return keyHandler(e);
    });

    // Init accordions (mobile)
    container.find('.feed-accordions .accordion').click(function accordionClicked(/*e*/) {
      var accordion = $(this);
      var tabId = accordion.data('tab');

      var tabs = accordion.closest('.feed-tabs');
      getTabContainer(tabs).removeClass('active');

      if (toggleAccordion(container, accordion)) {
        loadFeed(container, tabId);
      }
      return false;
    }).keyup(function onKeyUp(e) {
      return keyHandler(e);
    });

    container.find('.feed-accordions .accordion.active').click();
  }

  function init(id) {
    var container = $('.feed-tabs#' + id);
    $(container).one('inview', function doInit() {
      loadFeedTabs(container);
    });
  }

  var my = {
    init: init
  };

  return my;
})();
