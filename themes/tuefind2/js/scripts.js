// show/hide back-to-top button
$(document).scroll(function(){
    if ($(this).scrollTop() > 50) {
        $('#back-to-top').fadeIn();
    } else {
        $('#back-to-top').fadeOut();
    }
});

$('#back-to-top').tooltip();

// onclick-events
$(function() {

    // scroll down button
    $('#scroll-down-button').click(function(e) {
        e.preventDefault();
        $('html, body').animate({ scrollTop: $("#content").offset().top}, 500, 'linear');
    });

    // back-to-top button
    $('#back-to-top').click(function() {
        $('body,html').animate({
            scrollTop: 0
        }, 800);
        return false;
    });
});
