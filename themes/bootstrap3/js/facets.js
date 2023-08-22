/*global VuFind */
/*exported initFacetTree */
function buildFacetNodes(data, currentPath, allowExclude, excludeTitle, showCounts)
{
  var facetList = document.createElement("ul");
  facetList.className = "facet__list";

  var toggleIcon = document.createElement("span");
  toggleIcon.innerHTML =
    VuFind.icon("truncate-more", "facet-tree__icon facet-tree__open") +
    VuFind.icon("truncate-less", "facet-tree__icon facet-tree__close");

  var leafHTML = document.createElement("span");
  leafHTML.innerHTML = VuFind.icon("format-file", "facet-tree__icon");
  var leafIcon = leafHTML.children[0];

  for (var i = 0; i < data.length; i++) {
    var facet = data[i];

    var hasChildren = typeof facet.children !== "undefined" && facet.children.length > 0;

    // LI
    var liEl = document.createElement("li");
    liEl.className = "facet-tree";

    if (facet.isApplied) {
      liEl.classList.append("applied");
    }

    // Link to the facet
    var linkEl = document.createElement("a");
    linkEl.className = "facet__link";
    linkEl.setAttribute("href", currentPath + facet.href);
    linkEl.setAttribute("title", facet.displayText);

    // Display with an checkbox icon (or not)
    if (facet.operator === "OR") {
      var icon = document.createElement("span");
      icon.className = "icon-link__icon";
      icon.innerHTML = facet.isApplied
        ? VuFind.icon("facet-checked", { title: VuFind.translate("Selected"), class: "icon-link__icon" })
        : VuFind.icon("facet-unchecked", "icon-link__icon");

      var iconLabel = document.createElement("span");
      iconLabel.className = "facet-value icon-link__label";
      iconLabel.innerText = facet.displayText;

      var iconLink = document.createElement("span");
      iconLink.className = "icon-link";
      iconLink.append(icon, iconLabel);

      linkEl.append(iconLink);
    } else {
      var textEl = document.createElement("span");
      textEl.className = "text";
      textEl.append(facet.displayText);

      linkEl.append(textEl);
    }

    // Add a badge
    if (showCounts) {
      var badgeEl = document.createElement("span");
      badgeEl.className = "badge";
      badgeEl.innerText = facet.count.toLocaleString();

      linkEl.append(badgeEl);
    }

    // Build the container
    var facetEl = document.createElement(hasChildren ? "summary" : "span");
    facetEl.className = "facet";
    facetEl.append(linkEl);

    // Add the exclude link
    if (allowExclude) {
      var excludeLink = document.createElement("a");
      excludeLink.className = "facet__exclude";
      excludeLink.innerHTML = VuFind.icon("facet-exclude");
      excludeLink.setAttribute("href", currentPath + facet.exclude);
      excludeLink.setAttribute("title", excludeTitle);

      facetEl.append(excludeLink);
    }

    // Add child elements
    if (hasChildren) {
      var detailsEl = document.createElement("details");
      detailsEl.className = "facet-tree__details";

      facetEl.classList.add("facet-tree__summary");
      facetEl.prepend(toggleIcon.cloneNode(true));
      detailsEl.append(facetEl);

      var childList = buildFacetNodes(facet.children, currentPath, allowExclude, excludeTitle, showCounts);
      childList.classList.add("facet-tree__children");
      detailsEl.append(childList);

      liEl.append(detailsEl);
    } else {
      facetEl.classList.add("facet-tree__leaf");
      facetEl.prepend(leafIcon.cloneNode(true));
      liEl.append(facetEl);
    }

    // Append to the UL
    facetList.append(liEl);
  }

  return facetList;
}

function buildFacetTree(treeNode, facetData, inSidebar) {
  // Enable keyboard navigation also when a screen reader is active
  treeNode.bind('select_node.jstree', VuFind.sideFacets.showLoadingOverlay);

  var currentPath = treeNode.data('path');
  var allowExclude = treeNode.data('exclude');
  var excludeTitle = treeNode.data('exclude-title');

  var facetList = buildFacetNodes(facetData, currentPath, allowExclude, excludeTitle, inSidebar);

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

  treeNode[0].replaceChildren(facetList);
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
  VuFind.truncate.initTruncate('.truncate-facets', '.facet');
}

VuFind.listen('VuFind.sidefacets.loaded', registerSideFacetTruncation);
