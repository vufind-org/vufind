/*global registerAjaxCommentRecord, VuFind */
VuFind.register('embedded', function embedded() {
  var _STORAGEKEY = 'vufind_search_open';
  var _SEPARATOR = ':::';
  var _DELIM = ',';
  var _STATUS = {};

  /**
   * Synchronize the current status information to session storage for persistence.
   */
  function saveStatusToStorage() {
    var storage = [];
    var str;
    for (str in _STATUS) {
      if ({}.hasOwnProperty.call(_STATUS, str)) {
        if (_STATUS[str]) {
          str += _SEPARATOR + _STATUS[str];
        }
        storage.push(str);
      }
    }
    sessionStorage.setItem(_STORAGEKEY, $.uniqueSort(storage).join(_DELIM));
  }

  /**
   * Add a record and its active tab to the storage status.
   * @param {string} id  The record ID.
   * @param {string} tab The ID of the active tab.
   * @private
   */
  function addToStorage(id, tab) {
    _STATUS[id] = tab;
    saveStatusToStorage();
  }
  /**
   * Remove a record from the storage status.
   * @param {string} id The record ID.
   */
  function removeFromStorage(id) {
    if (delete _STATUS[id]) {
      saveStatusToStorage();
    }
  }

  /**
   * Load the content for a specific record tab via AJAX.
   * @param {string}  tabid    The ID of the tab to load.
   * @param {boolean} [_click] Whether to trigger a click on the tab after loading (default = false).
   * @returns {boolean} Returns false if the tab redirects to a new page, otherwise true.
   */
  function ajaxLoadTab(tabid, _click) {
    var click = _click || false;
    var $tab = $('#' + tabid);
    var $result = $tab.closest('.result');
    if ($result.length === 0) {
      return true;
    }
    var id = $result.find('.hiddenId')[0].value;
    var source = $result.find('.hiddenSource')[0].value;
    if ($tab.parent().hasClass('noajax')) {
      window.location.href = $tab.is('a')
        ? $tab.attr('href') // tab case
        : $tab.find('a').attr('data-href'); // accordion case
      return false;
    }
    var urlroot;
    if (source === VuFind.defaultSearchBackend) {
      urlroot = 'Record';
    } else {
      urlroot = source.charAt(0).toUpperCase() + source.slice(1).toLowerCase() + 'Record';
    }
    if (!$tab.hasClass('loaded')) {
      $('#' + tabid + '-content').html(VuFind.loading());
      var tab = tabid.split('_');
      tab = tab[0];
      $.ajax({
        url: VuFind.path + '/' + urlroot + '/' + encodeURIComponent(id) + '/AjaxTab',
        type: 'POST',
        data: { tab: tab },
        success: function ajaxTabSuccess(data) {
          var html = data.trim();
          if (html.length > 0) {
            $('#' + tabid + '-content').html(VuFind.updateCspNonce(html));
            VuFind.emit('record-tab-init', {container: document.querySelector('#' + tabid + '-content')});
          } else {
            $('#' + tabid + '-content').html(VuFind.translate('collection_empty'));
          }
          $('#' + tabid).addClass('loaded');
        }
      });
    }
    if (click && !$tab.parent().hasClass('default')) {
      $tab.trigger("click");
    }
    return true;
  }

  /**
   * Toggle the embedded detailed view of a record.
   * @param {jQuery} _link   The link element.
   * @param {string} [tabid] The ID of the tab to open (default = first available tab).
   * @returns {boolean} Return false to prevent the default link behavior.
   */
  function toggleDataView(_link, tabid) {
    var $link = $(_link);
    var viewType = $link.attr('data-view');
    // If full, return true
    if (viewType === 'full') {
      return true;
    }
    var result = $link.closest('.result');
    var mediaBody = result.find('.media-body');
    var shortNode = mediaBody.find('.result-body');
    var linksNode = mediaBody.find('.result-links');
    var longNode = mediaBody.find('.long-view');
    // Insert new elements
    if (!$link.hasClass('js-setup')) {
      $link.prependTo(mediaBody);
      result.addClass('embedded');
      shortNode.addClass('collapse');
      linksNode.addClass('collapse');
      longNode = $('<div class="long-view collapse"></div>');
      // Add loading status
      shortNode
        .before('<div class="loading hidden">' + VuFind.loading() + '</div>')
        .before(longNode);
      longNode.on('show.bs.collapse', function embeddedExpand() {
        $link.addClass('expanded');
      });
      longNode.on('hidden.bs.collapse', function embeddedCollapsed(e) {
        if ($(e.target).hasClass('long-view')) {
          $link.removeClass('expanded');
        }
      });
      $link.addClass('expanded js-setup');
    }
    // Gather information
    var divID = result.find('.hiddenId')[0].value;
    // Toggle visibility
    if (!longNode.is(':visible')) {
      // AJAX for information
      if (longNode.is(':empty')) {
        var loadingNode = mediaBody.find('.loading');
        loadingNode.removeClass('hidden');
        $link.addClass('expanded');
        $.ajax({
          dataType: 'json',
          url: VuFind.path + '/AJAX/JSON?' + $.param({
            method: 'getRecordDetails',
            id: divID,
            type: viewType,
            source: result.find('.hiddenSource')[0].value
          }),
          success: function getRecordDetailsSuccess(response) {
            // Insert tabs html
            longNode.html(VuFind.updateCspNonce(response.data.html));
            // Hide loading
            loadingNode.addClass('hidden');
            longNode.collapse('show');
            // Load first tab
            if (tabid) {
              document.getElementById(tabid).click();
              ajaxLoadTab(tabid, true);
            } else {
              var $firstTab = $(longNode).find('.list-tab-toggle.active');
              if ($firstTab.length === 0) {
                $firstTab = $(longNode).find('.list-tab-toggle').first();
              }
              ajaxLoadTab($firstTab.attr('id'), true);
            }
            // Bind tab clicks
            longNode.find('.list-tab-toggle').on('click', function embeddedTabLoad() {
              if (!$(this).parent().hasClass('noajax')) {
                addToStorage(divID, this.id);
              }
              return ajaxLoadTab(this.id);
            });
            longNode.find('[id^=usercomment]').find('input[type=submit]').off("click").on(
              "click",
              function embeddedComments() {
                return registerAjaxCommentRecord(longNode);
              }
            );
            longNode.find('[data-background]').each(function setupEmbeddedBackgroundTabs(index, el) {
              ajaxLoadTab(el.id, false);
            });
            // Add events to record toolbar
            VuFind.lightbox.bind(longNode);
            if (typeof VuFind.saveStatuses.init === 'function') {
              VuFind.saveStatuses.init(longNode);
            }
          }
        });
      } else {
        longNode.collapse('show');
      }
      shortNode.collapse('hide');
      linksNode.collapse('hide');
      if (!$link.hasClass('auto')) {
        addToStorage(divID, $(longNode).find('.list-tab-toggle.active').attr('id'));
      } else {
        $link.removeClass('auto');
      }
    } else {
      shortNode.collapse('show');
      linksNode.collapse('show');
      longNode.collapse('hide');
      removeFromStorage(divID);
    }
    return false;
  }

  /**
   * Load the status of open records from session storage.
   */
  function loadStorage() {
    var storage = sessionStorage.getItem(_STORAGEKEY);
    if (!storage) {
      return;
    }
    var items = storage.split(_DELIM);
    var doomed = [];
    var hiddenIds;
    var parts;
    var result;
    var i;
    var j;
    hiddenIds = $('.hiddenId');
    for (i = 0; i < items.length; i++) {
      parts = items[i].split(_SEPARATOR);
      _STATUS[parts[0]] = parts[1] || null;
      result = null;
      for (j = 0; j < hiddenIds.length; j++) {
        if (hiddenIds[j].value === parts[0]) {
          result = $(hiddenIds[j]).closest('.result');
          break;
        }
      }
      if (result === null) {
        doomed.push(parts[0]);
        continue;
      }
      var $link = result.find('.getFull');
      $link.addClass('auto expanded');
      toggleDataView($link, parts[1]);
    }
    for (i = 0; i < doomed.length; i++) {
      removeFromStorage(doomed[i]);
    }
  }

  /**
   * Update the container by binding events and loading stored states.
   * @param {object} params An object containing the container element.
   */
  function updateContainer(params) {
    const container = $(params.container);
    container.find('.getFull').on('click', function linkToggle() { return toggleDataView(this); });
    container.find('.full-record-link').removeClass('hidden');
    loadStorage();
  }

  /**
   * Initialize the embedded module.
   */
  function init() {
    updateContainer({container: document});
    VuFind.listen('results-init', updateContainer);
  }

  return {
    init: init
  };
});
