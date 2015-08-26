/*global extractClassParams, path*/

function loadResolverLinks($target, openUrl) {
    $target.addClass('ajax_availability');
    var url = path + '/AJAX/JSON?' + $.param({method:'getResolverLinks',openurl:openUrl});
    $.ajax({
        dataType: 'json',
        url: url,
        success: function(response) {
            if (response.status == 'OK') {
                $target.removeClass('ajax_availability')
                    .empty().append(response.data);
            } else {
                $target.removeClass('ajax_availability').addClass('error')
                    .empty().append(response.data);
            }
        }
    });
}

function embedOpenUrlLinks(element) {
    var openUrl = element.children('span.openUrl:first').attr('title');
    element.removeClass('openUrlEmbed').hide();
    loadResolverLinks(element.next('div.resolver').removeClass('hidden'), openUrl);
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
    $('a.openUrlEmbed').click(function() {
        embedOpenUrlLinks($(this));
        return false;
    });

    $('a.openUrlEmbed.openUrlEmbedAutoLoad').trigger("click");
});
