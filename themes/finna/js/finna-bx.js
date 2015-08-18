finna.bx = (function() {

    var initBxRecommendations = function() {
        var url = path + '/AJAX/JSON?method=getbXRecommendations';
        var id = $('.hiddenSource')[0].value + '|' + $('.hiddenId')[0].value;
        var jqxhr = $.getJSON(url, {id: id}, function(response) {
            if (response.status == 'OK') {
                if (response.data.length > 0) {
                    $('#bx-recommendations').removeClass('hidden');
                }
                var list = $('#bx-recommendations ul');
                for (var i = 0; i < response.data.length; i++) {
                    item = response.data[i];
                    var span = $('<span/>');
                    if (item.openurl) {
                        var a = $('<a/>');
                        a.attr('href', item.openurl);
                        a.attr('target', '_blank');
                        a.text(item.atitle);
                        span.append(a);
                    } else {
                        span.text(item.atitle);
                    }
                    var listItem = $('<li/>').addClass('list-group-item');
                    listItem.append(span);
                    if (item.authors) {
                        $.each(item.authors, function(key, value) {
                            if (key != "author") {
                            listItem.append( '<br/>' + value);
                            }
                        });
                    }
                    if (item.date) {
                        listItem.append(' (' + item.date + ')');
                    }
                    list.append(listItem);
                }
            }
        })
        .error(function() {
            $('#bx-recommendations').removeClass('hidden').text("Request for bX recommendations failed.");
        });

    };

    var my = {
        init: function() {
            initBxRecommendations();
        }
    };

    return my;

})(finna);
