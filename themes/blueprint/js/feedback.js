/*global alert, path, tabImage*/

// This overrides settings in jquery.tabSlideOut.v2.0.js
$(function(){
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
$(function() {
  $('.error').hide();
  $("div#slideOut").removeClass('slideOutForm');
  $('input.text-input').addClass('feedbackDeselect');
  $('input.text-input').focus(function(){
    $(this).removeClass('feedbackDeselect').addClass('feedbackSelect');
  });
  $('input.text-input').blur(function(){
    $(this).removeClass('feedbackSelect').addClass('feedbackDeselect');
  });

  $(".button").click(function() {
    // validate and process form here
    // first hide error messages
    $('.submit_button').hide();
    $('.error').hide();

    var name = $("input#name").val();
    if (name == "") {
      $("label#name_error").show();
      $("input#name").focus();
      return false;
    }
    var email = $("input#email").val();
    if (email == "") {
      $("label#email_error").show();
      $("input#email").focus();
      return false;
    }
    function validateEmail(email) {
      var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
      return re.test(email);
    }
    if(!validateEmail(email)) {
      $("label#invalid_email_error").show();
      $("input#email").focus();
      return false;
    }
    var comments = $("textarea#comments").val();
    if (comments == "") {
      $("label#comments_error").show();
      return false;
    }
    $('input#submit_btn').hide();

    var dataString = 'name='+ encodeURIComponent(name) + '&email='
        + encodeURIComponent(email) + '&comments=' + encodeURIComponent(comments);
    // Grabs hidden inputs
    var formSuccess = $("input#formSuccess").val();
    var feedbackSuccess = $("input#feedbackSuccess").val();
    var feedbackFailure = $("input#feedbackFailure").val();

    $.ajax({
      type: "POST",
      url: path + '/Feedback/Email',
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
