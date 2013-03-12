/*global alert, tabImage*/

// This overrides settings in jquery.tabSlideOut.v2.0.js
$(document).ready(function(){
  $('.slide-out-div').tabSlideOut({
    pathToTabImage: tabImage,
    imageHeight: '86px',
    imageWidth: '30px',
    handleOffset: '-1',
    speed: '300',
    topPos: '150px'
  });
});

// This is the ajax for the feedback
$(document).ready(function(){
  $('#contact_form label.error').hide();
  $("div#slideOut").removeClass('slideOutForm');
  $('input.text-input').addClass('feedbackDeselect');
  $('input.text-input').focus(function(){
    $(this).removeClass('feedbackDeselect').addClass('feedbackSelect');
  });
  $('input.text-input').blur(function(){
    $(this).removeClass('feedbackSelect').addClass('feedbackDeselect');
  });

  $('#contact_form form').validate();
  $('#contact_form form').unbind('submit').submit(function() {
    // validate and process form here
    var name = $("input#name");
    var email = $("input#email");
    var comments = $("textarea#comments");
    if (!$(this).valid() || !name.valid() || !email.valid() || !comments.valid()) { return false; }

    var dataString = 'name='+ encodeURIComponent(name.val()) + '&email='
        + encodeURIComponent(email.val()) + '&comments=' + encodeURIComponent(comments.val());

    // Grabs hidden inputs
    var formSuccess = $("input#formSuccess").val();
    var feedbackSuccess = $("input#feedbackSuccess").val();
    var feedbackFailure = $("input#feedbackFailure").val();

    $.ajax({
      type: "POST",
      url: $(this).attr('action'),
      data: dataString,
      success: function() {
        $('#contact_form').html("<div id='message'></div>");
        $('#message').html("<p class=\"feedbackHeader\"><b>"+formSuccess+"</b></p> <br />")
        .append("<p>"+feedbackSuccess+"</p>")
        .hide()
        .fadeIn(1500, function() {
          $('#message');
        });
      },
      error: function() {
          alert(feedbackFailure);
      }
    });
    return false;
  });
});
