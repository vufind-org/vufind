/*global VuFind, finna */
finna.itemStatus = (function finnaItemStatus() {
  function initDedupRecordSelection(_holder) {
    var holder = typeof _holder === 'undefined' ? $(document) : _holder;

    holder.find('.dedup-select').change(function onChangeDedupSelection() {
      var id = $(this).val();
      var source = $(this).find('option:selected').data('source');
      $.cookie('preferredRecordSource', source, {path: VuFind.path});

      var recordContainer = $(this).closest('.record-container');
      recordContainer.data('ajaxAvailabilityDone', 0);
      var oldRecordId = recordContainer.find('.hiddenId')[0].value;

      // Update IDs of elements
      recordContainer.find('.hiddenId').val(id);

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
        .html('<i class="fa fa-spinner fa-spin"></i> ' + VuFind.translate('loading') + '...<br>');
      recordContainer.find('.callnumAndLocation')
        .empty()
        .append($loading);
      recordContainer.find('.callnumber').removeClass('hidden');
      recordContainer.find('.location').removeClass('hidden');
      recordContainer.removeClass('js-item-done');
      VuFind.itemStatuses.checkRecord(recordContainer);
    });
  }

  var my = {
    initDedupRecordSelection: initDedupRecordSelection,
    init: function init() {
      if (!$('.results').hasClass('result-view-condensed')) {
        initDedupRecordSelection();
      }
    }
  };

  return my;
})();
