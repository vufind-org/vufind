/*global extractClassParams, VuFind */

function loadResolverLinks($target, openUrl, searchClassId) {
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

function embedOpenUrlLinks(element) {
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
    loadResolverLinks(target.removeClass('hidden'), openUrl, element.data('search-class-id'));
  }
}

$(document).ready(function() {
  // assign action to the openUrlWindow link class
  $('a.openUrlWindow').click(function(){
    var params = extractClassParams(this);
    var settings = params.window_settings;
    window.open($(this).attr('href'), 'openurl', settings);
    return false;
  });

  // assign action to the openUrlEmbed link class
  $('.openUrlEmbed a').click(function() {
    embedOpenUrlLinks($(this));
    return false;
  });

  $('.openUrlEmbed.openUrlEmbedAutoLoad a').trigger("click");
});
