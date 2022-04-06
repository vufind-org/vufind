/*global Hunt, VuFind */

VuFind.register('recordVersions', function recordVersions() {
  function checkRecordVersions(_container) {
    var container = typeof _container === 'undefined' ? $(document) : $(_container);

    var elements = container.hasClass('record-versions') && container.hasClass('ajax')
      ? container : container.find('.record-versions.ajax');
    elements.each(function checkVersions() {
      var $elem = $(this);
      if ($elem.hasClass('loaded')) {
        return;
      }
      $elem.addClass('loaded');
      $elem.removeClass('hidden');
      $elem.append('<span class="js-load">' + VuFind.translate('loading_ellipsis') + '</span>');
      var $item = $(this).parents('.result');
      var id = $item.find('.hiddenId')[0].value;
      var source = $item.find('.hiddenSource')[0].value;
      $.getJSON(
        VuFind.path + '/AJAX/JSON',
        {
          method: 'getRecordVersions',
          id: id,
          source: source
        }
      )
        .done(function onGetVersionsDone(response) {
          if (response.data.length > 0) {
            $elem.html(VuFind.updateCspNonce(response.data));
          } else {
            $elem.text('');
          }
        })
        .fail(function onGetVersionsFail() {
          $elem.text(VuFind.translate('error_occurred'));
        });
    });
  }

  function init(_container) {
    if (typeof Hunt === 'undefined') {
      checkRecordVersions(_container);
    } else {
      var container = typeof _container === 'undefined'
        ? document.body
        : _container;
      new Hunt(
        $(container).find('.record-versions.ajax').toArray(),
        { enter: checkRecordVersions }
      );
    }
  }

  return { init: init, check: checkRecordVersions };
});

