/*global VuFind */
/*exported initFacetTree */
function buildFacetNodes(facetName, data, currentPath, allowExclude, excludeTitle, showCounts, counter, locale)
{
  // Helper function to create elements
  function el(tagName, className = null) {
    const node = document.createElement(tagName);

    if (className !== null) {
      node.className = className;
    }

    return node;
  }

  // Build a UL
  let facetList = el('ul');

  // Elements
  for (let i = 0; i < data.length; i++) {
    let facet = data[i];

    const hasChildren = typeof facet.children !== 'undefined' && facet.children.length > 0;

    // Create badge
    let badgeEl = null;
    if (showCounts && !facet.isApplied && facet.count) {
      badgeEl = el('span', 'badge');
      badgeEl.innerText = facet.count.toLocaleString(locale);
    }

    // Create exclude link
    let excludeEl = null;
    if (allowExclude && !facet.isApplied) {
      excludeEl = el('a', 'exclude');
      excludeEl.innerHTML = VuFind.icon('facet-exclude');
      excludeEl.setAttribute('href', currentPath + facet.exclude);
      excludeEl.setAttribute('title', excludeTitle);
    }

    // Create facet text element
    const orFacet = facet.operator === 'OR';
    let valueEl = el('span', 'facet-value');
    valueEl.innerText = facet.displayText;
    let textEl = el('span', 'text');
    if (orFacet) {
      valueEl.className += ' icon-link__label';
      textEl.innerHTML = facet.isApplied
        ? VuFind.icon('facet-checked', { title: VuFind.translate('Selected'), class: 'icon-link__icon' })
        : VuFind.icon('facet-unchecked', 'icon-link__icon');
    }
    textEl.append(valueEl);

    // Create link element
    const linkEl = el('a', (orFacet ? ' icon-link' : ''));
    linkEl.setAttribute('href', currentPath + facet.href);
    linkEl.setAttribute('title', facet.displayText);
    linkEl.append(textEl);

    // Create facet element
    const classes = 'facet js-facet-item'
      + (facet.isApplied ? ' active' : '')
      + (orFacet ? ' facetOR' : ' facetAND');
    let facetEl;
    if (excludeEl) {
      linkEl.className += ' text';
      facetEl = el('div', classes);
      facetEl.append(linkEl);
      if (badgeEl) {
        facetEl.append(badgeEl);
      }
      facetEl.append(excludeEl);
    } else {
      if (badgeEl) {
        linkEl.append(badgeEl);
      }
      linkEl.className = classes + ' ' + linkEl.className;
      facetEl = linkEl;
    }

    // Create toggle button
    const toggleButton = el('button', 'facet-tree__toggle-expanded');
    toggleButton.setAttribute('aria-expanded', facet.hasAppliedChildren ? 'true' : 'false');
    toggleButton.setAttribute('data-toggle-aria-expanded', '');
    toggleButton.setAttribute('aria-label', facet.displayText);

    let itemContainerEl = el('span', 'facet-tree__item-container' + (allowExclude ? ' facet-tree__item-container--exclude' : ''));
    itemContainerEl.append(facetEl);

    // Create an li node with or without children
    const liEl = el('li');
    if (hasChildren) {
      liEl.className = 'facet-tree__parent';
      const childUlId = 'facet_' + facetName + '_' + (++counter.count);

      toggleButton.setAttribute('aria-controls', childUlId);
      toggleButton.innerHTML = VuFind.icon('facet-expand', 'facet-tree__expand') + VuFind.icon('facet-collapse', 'facet-tree__collapse');

      const childrenEl = buildFacetNodes(facetName, facet.children, currentPath, allowExclude, excludeTitle, showCounts, counter, locale);
      childrenEl.id = childUlId;

      liEl.append(toggleButton, itemContainerEl, childrenEl);
    } else {
      toggleButton.innerHTML = VuFind.icon('facet-noncollapsible', 'facet-tree__noncollapsible');
      toggleButton.setAttribute('disabled', '');

      liEl.append(toggleButton, itemContainerEl);
    }

    // Append to the UL
    facetList.append(liEl);
  }

  return facetList;
}

function buildFacetTree(treeNode, facetData, inSidebar) {
  var currentPath = treeNode.data('path');
  var allowExclude = treeNode.data('exclude');
  var excludeTitle = treeNode.data('exclude-title');
  var facetName = treeNode.data('facet');
  var locale = $('html').attr('lang');
  if (locale) {
    locale = locale.replace('_', '-');
  }

  var facetList = buildFacetNodes(facetName, facetData, currentPath, allowExclude, excludeTitle, inSidebar, { count: 0 }, locale);
  treeNode[0].replaceChildren(facetList);

  if (inSidebar) {
    treeNode.find('a').click(VuFind.sideFacets.showLoadingOverlay);
    if (treeNode.parent().hasClass('truncate-hierarchy')) {
      VuFind.truncate.initTruncate(treeNode.parent(), 'div > ul > li');
    }
  }
}

function loadFacetTree(treeNode, inSidebar)
{
  var loaded = treeNode.data('loaded');
  if (loaded) {
    return;
  }
  treeNode.data('loaded', true);

  if (inSidebar) {
    treeNode.prepend('<li class="jstree-node list-group-item facet-load-indicator">' + VuFind.loading() + '</li>');
  } else {
    treeNode.prepend('<div>' + VuFind.loading() + '<div>');
  }
  var request = {
    method: "getFacetData",
    source: treeNode.data('source'),
    facetName: treeNode.data('facet'),
    facetSort: treeNode.data('sort'),
    facetOperator: treeNode.data('operator'),
    query: treeNode.data('query'),
    querySuppressed: treeNode.data('querySuppressed'),
    extraFields: treeNode.data('extraFields')
  };
  $.getJSON(VuFind.path + '/AJAX/JSON?' + request.query,
    request,
    function getFacetData(response/*, textStatus*/) {
      buildFacetTree(treeNode, response.data.facets, inSidebar);
    }
  );
}

function initFacetTree(treeNode, inSidebar)
{
  // Defer init if the facet is collapsed:
  let $collapse = treeNode.parents('.facet-group').find('.collapse');
  if (!$collapse.hasClass('in')) {
    $collapse.on('show.bs.collapse', function onExpand() {
      loadFacetTree(treeNode, inSidebar);
    });
    return;
  } else {
    loadFacetTree(treeNode, inSidebar);
  }
}

/* --- Side Facets --- */
VuFind.register('sideFacets', function SideFacets() {
  function showLoadingOverlay(e, data) {
    e.preventDefault();
    var overlay = '<div class="facet-loading-overlay">'
      + '<span class="facet-loading-overlay-label">'
      + VuFind.loading()
      + "</span></div>";
    $(this).closest(".collapse").append(overlay);
    if (typeof data !== "undefined") {
      // Remove jstree-clicked class from JSTree links to avoid the color change:
      data.instance.get_node(data.node, true).children().removeClass('jstree-clicked');
    }
    // This callback operates both as a click handler and a JSTree callback;
    // if the data element is undefined, we assume we are handling a click.
    var href = typeof data === "undefined" || typeof data.node.data.url === "undefined"
      ? $(this).attr('href') : data.node.data.url;
    window.location.assign(href);
    return false;
  }

  function activateFacetBlocking(context) {
    var finalContext = (typeof context === "undefined") ? $(document.body) : context;
    finalContext.find('a.facet:not(.narrow-toggle),.facet a').click(showLoadingOverlay);
  }

  function activateSingleAjaxFacetContainer() {
    var $container = $(this);
    var facetList = [];
    var $facets = $container.find('div.collapse.in[data-facet], .checkbox-filter[data-facet]');
    $facets.each(function addFacet() {
      if (!$(this).data('loaded')) {
        facetList.push($(this).data('facet'));
      }
    });
    if (facetList.length === 0) {
      return;
    }
    var request = {
      method: 'getSideFacets',
      searchClassId: $container.data('searchClassId'),
      location: $container.data('location'),
      configIndex: $container.data('configIndex'),
      query: $container.data('query'),
      querySuppressed: $container.data('querySuppressed'),
      extraFields: $container.data('extraFields'),
      enabledFacets: facetList
    };
    $container.find('.facet-load-indicator').removeClass('hidden');
    $.getJSON(VuFind.path + '/AJAX/JSON?' + request.query, request)
      .done(function onGetSideFacetsDone(response) {
        $.each(response.data.facets, function initFacet(facet, facetData) {
          var containerSelector = typeof facetData.checkboxCount !== 'undefined'
            ? '.checkbox-filter' : ':not(.checkbox-filter)';
          var $facetContainer = $container.find(containerSelector + '[data-facet="' + facet + '"]');
          $facetContainer.data('loaded', 'true');
          if (typeof facetData.checkboxCount !== 'undefined') {
            if (facetData.checkboxCount !== null) {
              $facetContainer.find('.avail-count').text(
                facetData.checkboxCount.toString().replace(/\B(?=(\d{3})+\b)/g, VuFind.translate('number_thousands_separator'))
              );
            }
          } else if (typeof facetData.html !== 'undefined') {
            $facetContainer.html(VuFind.updateCspNonce(facetData.html));
            activateFacetBlocking($facetContainer);
          } else {
            var treeNode = $facetContainer.find('.jstree-facet');
            VuFind.emit('VuFind.sidefacets.treenodeloaded', {node: treeNode});

            buildFacetTree(treeNode, facetData.list, true);
          }
          $facetContainer.find('.facet-load-indicator').remove();
        });
        VuFind.lightbox.bind($('.sidebar'));
        VuFind.emit('VuFind.sidefacets.loaded');
      })
      .fail(function onGetSideFacetsFail() {
        $container.find('.facet-load-indicator').remove();
        $container.find('.facet-load-failed').removeClass('hidden');
      });
  }

  function loadAjaxSideFacets() {
    $('.side-facets-container-ajax').each(activateSingleAjaxFacetContainer);
  }

  function facetSessionStorage(e) {
    var source = $('#result0 .hiddenSource').val();
    var id = e.target.id;
    var key = 'sidefacet-' + source + id;
    if (!sessionStorage.getItem(key)) {
      sessionStorage.setItem(key, document.getElementById(id).className);
    } else {
      sessionStorage.removeItem(key);
    }
  }

  function init() {
    // Display "loading" message after user clicks facet:
    activateFacetBlocking();

    // Side facet status saving
    $('.facet-group .collapse').each(function openStoredFacets(index, item) {
      var source = $('#result0 .hiddenSource').val();
      var storedItem = sessionStorage.getItem('sidefacet-' + source + item.id);
      if (storedItem) {
        var saveTransition = $.support.transition;
        try {
          $.support.transition = false;
          if ((' ' + storedItem + ' ').indexOf(' in ') > -1) {
            $(item).collapse('show');
          } else if (!$(item).data('forceIn')) {
            $(item).collapse('hide');
          }
        } finally {
          $.support.transition = saveTransition;
        }
      }
    });
    $('.facet-group').on('shown.bs.collapse', facetSessionStorage);
    $('.facet-group').on('hidden.bs.collapse', facetSessionStorage);

    // Side facets loaded with AJAX
    $('.side-facets-container-ajax')
      .find('div.collapse[data-facet]:not(.in)')
      .on('shown.bs.collapse', function expandFacet() {
        loadAjaxSideFacets();
      });
    loadAjaxSideFacets();

    // Keep filter dropdowns on screen
    $(".search-filter-dropdown").on("shown.bs.dropdown", function checkFilterDropdownWidth(e) {
      var $dropdown = $(e.target).find(".dropdown-menu");
      if ($(e.target).position().left + $dropdown.width() >= window.innerWidth) {
        $dropdown.addClass("dropdown-menu-right");
      } else {
        $dropdown.removeClass("dropdown-menu-right");
      }
    });
  }

  return { init: init, showLoadingOverlay: showLoadingOverlay };
});

/* --- Lightbox Facets --- */
VuFind.register('lightbox_facets', function LightboxFacets() {
  function lightboxFacetSorting() {
    var sortButtons = $('.js-facet-sort');
    function sortAjax(button) {
      var sort = $(button).data('sort');
      var list = $('#facet-list-' + sort);
      if (list.find('.js-facet-item').length === 0) {
        list.find('.js-facet-next-page').html(VuFind.translate('loading_ellipsis'));
        $.ajax(button.href + '&layout=lightbox')
          .done(function facetSortTitleDone(data) {
            list.prepend($('<span>' + data + '</span>').find('.js-facet-item'));
            list.find('.js-facet-next-page').html(VuFind.translate('more_ellipsis'));
          });
      }
      $('.full-facet-list').addClass('hidden');
      list.removeClass('hidden');
      sortButtons.removeClass('active');
    }
    sortButtons.click(function facetSortButton() {
      sortAjax(this);
      $(this).addClass('active');
      return false;
    });
  }

  function setup() {
    lightboxFacetSorting();
    $('.js-facet-next-page').on("click", function facetLightboxMore() {
      var button = $(this);
      var page = parseInt(button.attr('data-page'), 10);
      if (button.attr('disabled')) {
        return false;
      }
      button.attr('disabled', 1);
      button.html(VuFind.translate('loading_ellipsis'));
      $.ajax(this.href + '&layout=lightbox')
        .done(function facetLightboxMoreDone(data) {
          var htmlDiv = $('<div>' + data + '</div>');
          var list = htmlDiv.find('.js-facet-item');
          button.before(list);
          if (list.length && htmlDiv.find('.js-facet-next-page').length) {
            button.attr('data-page', page + 1);
            button.attr('href', button.attr('href').replace(/facetpage=\d+/, 'facetpage=' + (page + 1)));
            button.html(VuFind.translate('more_ellipsis'));
            button.removeAttr('disabled');
          } else {
            button.remove();
          }
        });
      return false;
    });
    var margin = 230;
    $('#modal').on('show.bs.modal', function facetListHeight() {
      $('#modal .lightbox-scroll').css('max-height', window.innerHeight - margin);
    });
    $(window).on("resize", function facetListResize() {
      $('#modal .lightbox-scroll').css('max-height', window.innerHeight - margin);
    });
  }

  return { setup: setup };
});

function registerSideFacetTruncation() {
  VuFind.truncate.initTruncate('.truncate-facets', '.facet__list__item');
}

VuFind.listen('VuFind.sidefacets.loaded', registerSideFacetTruncation);
