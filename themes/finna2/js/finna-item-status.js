/*global VuFind, finna */
finna.itemStatus = (function finnaItemStatus() {

  /**
   * Finds the closest record-container and sets element ids to match
   * desired record id.
   * @param {HTMLSelectElement} element Select element to update
   * @returns {void}
   */
  function updateElement(element) {
    var id = document.createTextNode($(element).val()).nodeValue;
    if (!id) {
      return;
    }
    var recordContainer = $(element).closest('.record-container');
    var oldRecordId = recordContainer.find('.hiddenId')[0].value;

    const placeholder = $(element).find('.js-dedup-placeholder');
    let skipUpdate = id === oldRecordId;
    if (placeholder) {
      skipUpdate = false;
      placeholder.remove();
    }
    // Element changes are being watched with lazyloading so return if the value is same.
    // If placeholder is set, then force the load for first time.
    if (skipUpdate) {
      return;
    }

    // Update IDs of elements
    var hiddenId = recordContainer.find('.hiddenId');
    hiddenId.val(id);
    // Update IDs of elements
    recordContainer.find('[id="' + oldRecordId + '"]').each(function updateElemId() {
      $(this).attr('id', id);
    });

    // Update links as well
    recordContainer.find('a').each(function updateLinks() {
      if (typeof $(this).attr('href') !== 'undefined') {
        $(this).attr('href', $(this).attr('href').replace(oldRecordId, id));
      }
    });

    // Item statuses
    var $loading = $('<span/>')
      .addClass('location ajax-availability hidden')
      .html(VuFind.loading());
    recordContainer.find('.callnumAndLocation')
      .empty()
      .append($loading);
    recordContainer.find('.callnumber').removeClass('hidden');
    recordContainer.find('.location').removeClass('hidden');
    recordContainer.removeClass('js-item-done');
    VuFind.observerManager.observe(
      'itemStatuses',
      [recordContainer[0]]
    );
    VuFind.observerManager.observe(
      'FinnaDedupSelect',
      [recordContainer[0]]
    );
  }

  /**
   * Assigns a change eventlistener to all elements with class dedup-select
   * @param {HTMLElement|null} _holder Holder to initialize deduplication selections in
   * @returns {void}
   */
  function initDedupRecordSelection(_holder) {
    var holder = typeof _holder === 'undefined' ? $(document) : _holder;
    var selects = $(holder).find('.dedup-select');

    selects.on('change', function onChangeDedupSelection(e, auto_selected) {
      if (auto_selected) {
        return;
      }
      const self = $(this);
      var source = self.find('option:selected').data('source');
      // prefer 3 latest sources
      var cookie = VuFind.cookie.get('preferredRecordSource');
      try {
        cookie = JSON.parse(cookie);
      } catch (error) {
        cookie = [];
      }
      if (!Array.isArray(cookie)) {
        cookie = cookie ? [cookie] : [];
      }
      // Filter same sources from the resulting array
      cookie = cookie.filter((src, index) => {
        return index < 2 && src !== source;
      });
      cookie.unshift(source);
      VuFind.cookie.set('preferredRecordSource', JSON.stringify(cookie));
      selects.each(function setValues() {
        if (self[0] === $(this)[0]) {
          return;
        }
        var elem = $(this).find(`option[data-source='${source}']`);
        if (elem.length) {
          $(this).val(elem.val());
          updateElement(elem);
        }
      });
      updateElement(this);
    });
  }

  /**
   * Creates an observer for updating links for deduplicated records.
   * @returns {void}
   */
  function createLinkObserver() {
    VuFind.observerManager.createIntersectionObserver(
      'FinnaDedupSelect',
      (recordContainer) => {
        var $recordContainer = $(recordContainer);
        var hiddenId = $recordContainer.find('.hiddenId');
        var $recordUrls = $recordContainer.find('.available-online-links');
        if ($recordUrls.length) {
          $recordUrls.html(VuFind.loading());
          $.getJSON(
            VuFind.path + '/AJAX/JSON',
            {
              method: 'getRecordData',
              data: 'onlineUrls',
              source: $recordContainer.find('.hiddenSource')[0].value,
              id: hiddenId.val()
            }
          ).done(function onGetRecordLinksDone(response) {
            $recordUrls.replaceWith(VuFind.updateCspNonce(response.data.html));
            finna.layout.initTruncate($recordContainer);
            VuFind.openurl.embedOpenUrlLinks($recordContainer.find('.openUrlEmbed a'));
          }).fail(function onGetRecordLinksFail() {
            $recordUrls.html(VuFind.translate('error_occurred'));
          });
        }
      }
    );
  }

  var my = {
    initDedupRecordSelection: initDedupRecordSelection,
    updateElement: updateElement,
    init: function init() {
      createLinkObserver();
      initDedupRecordSelection();
    }
  };

  return my;
})();
