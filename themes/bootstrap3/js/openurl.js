/*global extractClassParams, VuFind */
VuFind.register('openurl', function() {
  var _loadResolverLinks = function($target, openUrl, searchClassId) {
    $target.addClass('ajax_availability');
    var url = VuFind.path + '/AJAX/JSON?' + $.param({method:'getResolverLinks',openurl:openUrl,searchClassId:searchClassId});
    $.ajax({
      dataType: 'json',
      url: url
    })
    .done(function(response) {
      $target.removeClass('ajax_availability').empty().append(response.data);
    })
    .fail(function(response, textStatus) {
      $target.removeClass('ajax_availability').addClass('text-danger').empty();
      if (textStatus == 'abort' || typeof response.responseJSON === 'undefined') { return; }
      $target.append(response.responseJSON.data);
    });
  }

  var _embedOpenUrlLinks = function(element) {
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
      _loadResolverLinks(target.removeClass('hidden'), openUrl, element.data('search-class-id'));
    }
  }

  // Assign actions to the OpenURL links. This can be called with a container e.g. when 
  // combined results fetched with AJAX are loaded.
  var init = function(container)
  {
    if (typeof(container) == 'undefined') {
      container = $('body');
    }

     // assign action to the openUrlWindow link class
    container.find('a.openUrlWindow').click(function() {
      var params = extractClassParams(this);
      var settings = params.window_settings;
      window.open($(this).attr('href'), 'openurl', settings);
      return false;
    });

    // assign action to the openUrlEmbed link class
    container.find('.openUrlEmbed a').click(function() {
      _embedOpenUrlLinks($(this));
      return false;
    });

    container.find('.openUrlEmbed.openUrlEmbedAutoLoad a').trigger('click');
  }
  return {init: init}
});