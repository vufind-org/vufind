/* Login and logout helper methods. */

finna.common = (function () {

    var navibarLogin = function () {
        if( $('#loginOptions a').hasClass('navibar-login-on') &&
            $('#loginOptions').hasClass('hidden') )
        {
            window.location = path+'/MyResearch/Home?redirect=0';
        }
    };

    var loginSetup = function () {
        // Login link
        $('#loginOptions a.modal-link').click(function () {
            $('#loginOptions a.modal-link').addClass('navibar-login-on');
            Lightbox.addCloseAction(function() {$('#loginOptions a.modal-link').removeClass('navibar-login-on');});
        });

        Lightbox.addFormHandler('loginForm', function (evt) {
            ajaxLogin(evt.target);
            Lightbox.addCloseAction(navibarLogin);
            return false;
        });

        // jQuery moves focus to modal window when opening it,
        // so we have to direct it back to username input field.
        $('#modal').focus(function() {
            $('#login_username').focus();
        });
        // Set focus to username at login page
        $('#login_username').focus();

    };

    var my = {
        init: function () {
            loginSetup();
        }
    };

    return my;

})(finna);
