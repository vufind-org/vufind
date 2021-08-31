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
    // expand panel if url parameter is set
    let urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('expand')) {
        let url = window.location.href;
        let index = url.indexOf("#");
        if (index !== -1) {
            let anchor = url.substring(index+1);
            let element = $('[href="#' + anchor + '"]');
            if (element.attr('data-toggle') == 'collapse')
                element.click();
        }
    }
});
