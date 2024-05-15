/*global extractClassParams, VuFind */
VuFind.register('openurl', function OpenUrl() {
  function _loadResolverLinks($target, openUrl, searchClassId) {
    $target.addClass('ajax_availability');
    var url = VuFind.path + '/AJAX/JSON?' + $.param({
      method: 'getResolverLinks',
      openurl: openUrl,
      searchClassId: searchClassId
    });
    $.ajax({
      dataType: 'json',
      url: url
    })
      .done(function getResolverLinksDone(response) {
        $target.removeClass('ajax_availability').empty().append(response.data.html);
      })
      .fail(function getResolverLinksFail(response, textStatus) {
        $target.removeClass('ajax_availability').addClass('text-danger').empty();
        if (textStatus === 'abort' || typeof response.responseJSON == 'undefined') { return; }
        $target.append(response.responseJSON.data);
      });
  }

  function embedOpenUrlLinks(el) {
    var element = $(el);
    // Extract the OpenURL associated with the clicked element:
    var openUrl = element.children('span.openUrl:first').attr('title');

    // Hide the controls now that something has been clicked:
    var controls = element.parents('.openUrlControls');
    controls.removeClass('openUrlEmbed').addClass('hidden');

    // Locate the target area for displaying the results:
    var target = controls.next('div.resolver');

    // If the target is already visible, a previous click has populated it;
    // don't waste time doing redundant work.
    if (target.hasClass('hidden')) {
      _loadResolverLinks(target.removeClass('hidden'), openUrl, element.data('searchClassId'));
    }
  }

  // Assign actions to the OpenURL links. This can be called with a container e.g. when
  // combined results fetched with AJAX are loaded.
  function updateContainer(params) {
    var container = $(params.container);
    // assign action to the openUrlWindow link class
    container.find('a.openUrlWindow').off('click').on("click", function openUrlWindowClick() {
      var classParams = extractClassParams(this);
      var settings = classParams.window_settings;
      window.open($(this).attr('href'), 'openurl', settings);
      return false;
    });

    // assign action to the openUrlEmbed link class
    container.find('.openUrlEmbed a').off('click').on("click", function openUrlEmbedClick() {
      embedOpenUrlLinks(this);
      return false;
    });

    if (VuFind.isPrinting()) {
      container.find('.openUrlEmbed.openUrlEmbedAutoLoad a').trigger('click');
    } else {
      VuFind.observerManager.createIntersectionObserver(
        'openUrlEmbed',
        embedOpenUrlLinks,
        container.find('.openUrlEmbed.openUrlEmbedAutoLoad a').toArray()
      );
    }
  }

  function init() {
    updateContainer({container: document.body});
    VuFind.listen('results-init', updateContainer);
  }


  return {
    init: init,
    embedOpenUrlLinks: embedOpenUrlLinks
  };
});
