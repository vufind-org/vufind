finna.openUrl = (function() {

    var loadResolverLinks = function($target, openUrl, searchClassId) {
        $target.addClass('ajax_availability');

        var url = path + '/AJAX/JSON?' + $.param({method:'getResolverLinks',openurl:openUrl,searchClassId:searchClassId});
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
    };
    
    var initLinks = function(holder) {
        if (typeof(holder) == "undefined") {
            holder = $("body");
        }

        // assign action to the openUrlWindow link class
        holder.find('a.openUrlWindow').one("click", function(){
            var params = extractClassParams(this);
            var settings = params.window_settings;
            window.open($(this).attr('href'), 'openurl', settings);
            return false;
        });
        
        // assign action to the openUrlEmbed link class
        holder.find('a.openUrlEmbed').one("click", function(){
            var params = extractClassParams(this);
            var openUrl = $(this).children('span.openUrl:first').attr('title');
            $(this).hide();
            loadResolverLinks(
                holder.find($(this).parent().find(".resolver"))
                    .removeClass('hidden'), openUrl,params.searchClassId
            );
            return false;
        });
        
        holder.find('a.openUrlEmbed').each(function(ind, e) {
            $(e).one("inview", function(){
                $(this).click();
            });
        });
    };

    var triggerAutoLoad = function(holder) {
        if (typeof(holder) == "undefined") {
            holder = $("body");
        }

        holder.find('a.openUrlEmbed.openUrlEmbedAutoLoad').trigger("click");
    };

    var my = {
        initLinks: initLinks,
        triggerAutoLoad: triggerAutoLoad,
        init: function() {
            initLinks();
            triggerAutoLoad();
        },
    };

    return my;
})(finna);
