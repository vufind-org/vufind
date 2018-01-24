/**********************************
 *
 * Custom Scripts for ixTheoTheme
 *
 * Benjamin Schnabel
 *
 **********************************/

//make the head dissapear on scrolling

/*
$(document).ready( function () {
    $('main').css('margin-top', '700px');
    $('header > div').addClass('fixed-top');
});

$(document).scroll( function () {

    if ($(document).scrollTop() > 0) {
        $('.title').css('display', 'none');
        $('.search-button').removeClass('btn-lg'); //TODO: use ID instead.
        $('.searchbar-select').removeClass('form-control-lg');
        $('.searchbar > input').removeClass('form-control-lg');
        $('.panel-home').css({'min-height': '150px'});
        $('.main').css('margin-top', '150px');

    } else {
        $('.title').css('display', 'block');
        $('.search-button').addClass('btn-lg');
        $('.searchbar-select').addClass('form-control-lg');
        $('.searchbar > input').addClass('form-control-lg');
        $('.panel-home').css({
            'background-image': 'url(/themes/ixTheoThemeNew/images/searchbar3.jpg)',
            'min-height': '600px'
        });
        $('.main').css('margin-top', '700px');
    }
});
*/

$(document).scroll( function (){
    // To-Top-Button
    if ($(this).scrollTop() > 50) {
        $('#back-to-top').fadeIn();
    } else {
        $('#back-to-top').fadeOut();
    }
});


// scroll body to 0px on click
$('#back-to-top').click(function () {
    $('#back-to-top').tooltip('hide');
    $('body,html').animate({
        scrollTop: 0
    }, 800);
    return false;
});

$('#back-to-top').tooltip();

/* scroll down button */
$(function() {
    $('#scroll-down-button').click(function(e) {
        e.preventDefault();
        $('html, body').animate({ scrollTop: $("#content").offset().top}, 500, 'linear');
    });
});