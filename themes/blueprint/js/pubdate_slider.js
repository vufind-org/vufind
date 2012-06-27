$(document).ready(function(){
    // create the slider for the publish date facet
    $('.dateSlider').each(function(i) {
        var myId = $(this).attr('id');
        var prefix = myId.substr(0, myId.length - 6);
        makePublishDateSlider(prefix);
    });
});

function makePublishDateSlider(prefix) {
    // create the slider widget
    $('#' + prefix + 'Slider').slider({
        range: true,
        min: 0, max: 9999, values: [0, 9999],
        slide: function(event, ui) {
            $('#' + prefix + 'from').val(ui.values[0]);
            $('#' + prefix + 'to').val(ui.values[1]);
        }
    });
    // initialize the slider with the original values
    // in the text boxes
    updatePublishDateSlider(prefix);

    // when user enters values into the boxes
    // the slider needs to be updated too
    $('#' + prefix + 'from, #' + prefix + 'to').change(function(){
        updatePublishDateSlider(prefix);
    });
}

function updatePublishDateSlider(prefix) {
    var from = parseInt($('#' + prefix + 'from').val());
    var to = parseInt($('#' + prefix + 'to').val());
    // assuming our oldest item is published in the 15th century
    var min = 1500;
    if (!from || from < min) {
        from = min;
    }
    // move the min 20 years away from the "from" value
    if (from > min + 20) {
        min = from - 20;
    }
    // and keep the max at 1 years from now
    var max = (new Date()).getFullYear() + 1;
    if (!to || to > max) {
        to = max;
    }
    // update the slider with the new min/max/values
    $('#' + prefix + 'Slider').slider('option', {
        min: min, max: max, values: [from, to]
    });
}