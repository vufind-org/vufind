finna.record = (function() {
    
    var initDescription = function() {
        var description = $("#description_text");
        if (description.length) {
            var id = description.data('id');
            var url = path + '/AJAX/JSON?method=getDescription&id=' + id;
            $.getJSON(url, function(response) {
                if (response.status === 'OK' && response.data.length > 0) {
                    description.html(response.data);
                    description.wrapInner('<div class="truncate-field"></div>');
                } else {
                    description.hide();
                }
            });
        }
    }

    var my = {
        init: function() {
            initDescription();
        },
    };

    return my;
})(finna);
