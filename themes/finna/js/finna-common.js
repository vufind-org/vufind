/* Login and logout helper methods. */

finna.common = (function() {

    var navibarLogin = function() {
        if ( $('#loginOptions a').hasClass('navibar-login-on') &&
            $('#loginOptions').hasClass('hidden') )
        {
            window.location = path+'/MyResearch/Home?redirect=0';
        }
    };

    var loginSetup = function() {
        // Login link
        $('#loginOptions a.modal-link').click(function() {
            $('#loginOptions a.modal-link').addClass('navibar-login-on');
            Lightbox.addCloseAction(function() {$('#loginOptions a.modal-link').removeClass('navibar-login-on');});
        });

        Lightbox.addFormHandler('loginForm', function(evt) {
            ajaxLogin(evt.target);
            Lightbox.addCloseAction(navibarLogin);
            return false;
        });

        // Modal window focus set to username input field.
        $('#modal').on('shown.bs.modal', function(e) {
            $('#login_username').focus();
        });

    };

    var initFeedbackForm = function() {
        Lightbox.addFormCallback('finna_feedback', function(html) {
            Lightbox.confirm(vufindString['feedback_success']);
        });
    };
    
    var my = {
        init: function() {
            loginSetup();
            initFeedbackForm();
        }
    };

    return my;

})(finna);
