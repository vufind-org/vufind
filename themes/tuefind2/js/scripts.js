// show/hide back-to-top button
$(document).scroll(function(){
    if ($(this).scrollTop() > 50) {
        $('#tf-button-footer-back-to-top').fadeIn();
    } else {
        $('#tf-button-footer-back-to-top').fadeOut();
    }
});

$('#tf-back-to-top').tooltip();

// onclick-events
$(function() {

    // scroll down button
    $('#scroll-down-button').click(function(e) {
        e.preventDefault();
        $('html, body').animate({ scrollTop: $("#content").offset().top}, 500, 'linear');
    });

    // back-to-top button
    $('#tf-button-footer-back-to-top').click(function() {
        $('body,html').animate({
            scrollTop: 0
        }, 800);
        return false;
    });
});
