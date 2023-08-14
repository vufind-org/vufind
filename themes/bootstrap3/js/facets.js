/*global VuFind, getFacetListContent */
/*exported initFacetTree */

function overrideHref(selector, overrideParams = {}) {
  $(selector).each(function overrideHrefEach() {
    let dummyDomain = 'https://www.example.org'; // we need this since the URL class cannot parse relative URLs
    let url = new URL(dummyDomain + VuFind.path + $(this).attr('href'));
    Object.entries(overrideParams).forEach(([key, value]) => {
      url.searchParams.set(key, value);
    });
    url = url.href;
    url = url.replaceAll(dummyDomain, '');
    $(this).attr('href', url);
  });
}

function updateHrefContains() {
  let overrideParams = { contains: $('.ajax_param[data-name="contains"]').val() };
  overrideHref('.js-facet-sort', overrideParams);
  overrideHref('.js-facet-next-page', overrideParams);
  overrideHref('.js-facet-prev-page', overrideParams);
}

function getFacetListContent(overrideParams = {}) {
  let url = $('.ajax_param[data-name="urlBase"]').val();

  $('.ajax_param').each(function ajaxParamEach() {
    let key = $(this).data('name');
    let val = $(this).val();
    if (key in overrideParams) {
      val = overrideParams[key];
    }
    url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(val);
  });
  url += '&ajax=1';

  return Promise.resolve($.ajax({
    url: url
  }));
}

function updateFacetListContent() {
  getFacetListContent().then(html => {
    let htmlList = '';
    $(html).find('.full-facet-list').each(function itemEach() {
      htmlList += $(this).prop('outerHTML');
    });
    $('#facet-info-result').html(htmlList);
    // This needs to be registered here as well so it works in a lightbox
    registerFacetListContentKeyupCallback();
  });
}

function setupFacetList() {
  if ($.isReady) {
    setupFacetList();
  } else {
    $(document).ready(function() {
      setupFacetList();
    });
  }
  $('.ajax_param[data-name="contains"]').on('keyup', function onKeyupChangeFacetList() {
    registerFacetListContentKeyupCallback();
  });
}

// Useful function to delay callbacks, e.g. when using a keyup event
// to detect when the user stops typing.
// See also: https://stackoverflow.com/questions/1909441/how-to-delay-the-keyup-handler-until-the-user-stops-typing
var keyupCallbackTimeout = null;
function registerFacetListContentKeyupCallback() {
  $('.ajax_param[data-name="contains"]').on('keyup', function onKeyupChangeFacetList() {
    clearTimeout(keyupCallbackTimeout);
    keyupCallbackTimeout = setTimeout(function onKeyupTimeout() {
      updateFacetListContent();
      updateHrefContains();
    }, 500);
  });
}


function buildFacetNodes(data, currentPath, allowExclude, excludeTitle, counts)
{
  var json = [];
  var selected = VuFind.translate('Selected');
  var separator = VuFind.translate('number_thousands_separator');

  for (var i = 0; i < data.length; i++) {
    var facet = data[i];

    var url = currentPath + facet.href;
    var excludeUrl = currentPath + facet.exclude;
    var item = document.createElement('span');
    item.className = 'text';
    if (facet.isApplied) {
      item.className += ' applied';
    }
    item.setAttribute('title', facet.displayText);
    if (facet.operator === 'OR') {
      item.innerHTML = facet.isApplied ? VuFind.icon('facet-checked', { title: selected, class: 'icon-link__icon' }) : VuFind.icon('facet-unchecked', 'icon-link__icon');
    }
    var facetValue = document.createElement('span');
    facetValue.className = 'facet-value icon-link__label';
    facetValue.appendChild(document.createTextNode(facet.displayText));
    item.appendChild(facetValue);

    var children = null;
    if (typeof facet.children !== 'undefined' && facet.children.length > 0) {
      children = buildFacetNodes(facet.children, currentPath, allowExclude, excludeTitle, counts);
    }

    json.push({
      text: item.outerHTML,
      children: children,
      state: {
        opened: facet.hasAppliedChildren,
        selected: facet.isApplied
      },
      a_attr: {
        class: 'hierarchical-facet-anchor icon-link',
        href: url
      },
      data: {
        url: url.replace(/&amp;/g, '&'),
        count: !facet.isApplied && counts && facet.count
          ? facet.count.toString().replace(/\B(?=(\d{3})+\b)/g, separator) : null,
        excludeUrl: allowExclude && !facet.isApplied ? excludeUrl : '',
        excludeTitle: excludeTitle
      }
    });
  }

  return json;
}

function buildFacetTree(treeNode, facetData, inSidebar) {
  // Enable keyboard navigation also when a screen reader is active
  treeNode.bind('select_node.jstree', VuFind.sideFacets.showLoadingOverlay);

  var currentPath = treeNode.data('path');
  var allowExclude = treeNode.data('exclude');
  var excludeTitle = treeNode.data('exclude-title');

  var results = buildFacetNodes(facetData, currentPath, allowExclude, excludeTitle, inSidebar);
  treeNode.find('.loading-spinner').parent().remove();
  if (inSidebar) {
    treeNode.on('loaded.jstree open_node.jstree', function treeNodeOpen(/*e, data*/) {
      treeNode.find('ul.jstree-container-ul > li.jstree-node').addClass('list-group-item');
      treeNode.find('a.exclude').click(VuFind.sideFacets.showLoadingOverlay);
    });
    if (treeNode.parent().hasClass('truncate-hierarchy')) {
      treeNode.on('loaded.jstree', function initHierarchyTruncate(/*e, data*/) {
        VuFind.truncate.initTruncate(treeNode.parent(), '.list-group-item');
      });
    }
  }

  treeNode.jstree({
    'core': {
      'data': results
    },
    'plugins': ['vufindFacet']
  });
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
      let button = $(this);
      let page = parseInt(button.attr('data-page'), 10);
      if (button.attr('disabled')) {
        return false;
      }
      button.attr('disabled', 1);
      button.html(VuFind.translate('loading_ellipsis'));

      let overrideParams = {facetpage: page, layout: 'lightbox'};
      getFacetListContent(overrideParams).then(data => {
        $(data).find('.js-facet-item').each(function eachItem() {
          button.before($(this).prop('outerHTML'));
        });
        let list = $(data).find('.js-facet-item');
        if (list.length && $(data).find('.js-facet-next-page').length) {
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
    let margin = 230;
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
  VuFind.truncate.initTruncate('.truncate-facets', '.facet');
}

VuFind.listen('VuFind.sidefacets.loaded', registerSideFacetTruncation);
