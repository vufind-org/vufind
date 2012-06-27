$(document).ready(function() {
    checkSaveStatuses();
    // attach click event to the save record link
    $('a.saveRecord').click(function() {
        var id = $(this).parents('.recordId').find('.hiddenId');
        if (id.length > 0) {
            // search results:
            id = id[0].value;
        } else {
            // record view:
            id = document.getElementById('record_id').value;
        }
        var controller = extractController(this);
        var $dialog = getLightbox(controller, 'Save', id, null, this.title, controller, 'Save', id);
        return false;
    });
});

function checkSaveStatuses() {
    var data = $.map($('.recordId'), function(i) {
        return {'id':$(i).find('.hiddenId')[0].value, 'source':extractSource(i)};
    });
    if (data.length) {
        var ids = [];
        var srcs = [];
        for (var i = 0; i < data.length; i++) {
            ids[i] = data[i].id;
            srcs[i] = data[i].source;
        }
        $.ajax({
            dataType: 'json',
            url: path + '/AJAX/JSON?method=getSaveStatuses',
            data: {id:ids, 'source':srcs},
            success: function(response) {
                if(response.status == 'OK') {
                    $('.savedLists > ul').empty();
                    $.each(response.data, function(i, result) {
                        var $container = $('#result'+result.record_number).find('.savedLists');
                        if ($container.length == 0) { // Record view
                            $container = $('#savedLists');
                        }
                        var $ul = $container.children('ul:first');
                        if ($ul.length == 0) {
                            $container.append('<ul></ul>');
                            $ul = $container.children('ul:first');
                        }
                        var html = '<li><a href="' + path + '/MyResearch/MyList/' + result.list_id + '">'
                                 + result.list_title + '</a></li>';
                        $ul.append(html);
                        $container.show();
                    });
                }
            }
        });
    }
}
