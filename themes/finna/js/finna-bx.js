finna.bx = (function() {

    var initBxRecommendations = function() {
        var url = VuFind.getPath() + '/AJAX/JSON?method=getbXRecommendations';
        var id = $('.hiddenSource')[0].value + '|' + $('.hiddenId')[0].value;
        $.getJSON(url, {id: id})
        .done(function(response) {
            $('#bx-recommendations-holder').html(response.data);
        })
        .fail(function() {
            $('#bx-recommendations-holder').text("Request for bX recommendations failed.");
        });
    };

    var my = {
        init: function() {
            initBxRecommendations();
        }
    };

    return my;

})(finna);
