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

        // Standalone login form
        $('#loginForm').submit(function(evt) { 
            evt.preventDefault();
            standaloneAjaxLogin(evt.target);
        });
    };
    
    // ajaxLogin equivalent for non-lightbox login
    var standaloneAjaxLogin = function (form) {
        $(form).find('div.alert').remove();
        $(form).find('input[type=submit]').after('<i class="fa fa-spinner fa-spin"></i>');
        $.ajax({
            url: path + '/AJAX/JSON?method=getSalt',
            dataType: 'json',
            success: function(response) {
                if (response.status == 'OK') {
                    var salt = response.data;
        
                    // get the user entered password
                    var password = form.password.value;
                    
                    // base-64 encode the password (to allow support for Unicode)
                    // and then encrypt the password with the salt
                    password = rc4Encrypt(salt, btoa(unescape(encodeURIComponent(password))));
                    
                    // hex encode the encrypted password
                    password = hexEncode(password);
                    
                    var params = {password:password};
                    
                    // get any other form values
                    for (var i = 0; i < form.length; i++) {
                        if (form.elements[i].name == 'password') {
                            continue;
                        }
                        params[form.elements[i].name] = form.elements[i].value;
                    }
                    
                    // login via ajax
                    $.ajax({
                        type: 'POST',
                        url: path + '/AJAX/JSON?method=login',
                        dataType: 'json',
                        data: params,
                        success: function(response) {
                            if (response.status == 'OK') {
                                // No reload since any post params would cause a prompt
                                window.location.href = window.location.href;
                            } else {
                                var div = $('<div/>').addClass('alert alert-danger').text(response.data);
                                $(form).prepend(div);
                                $(form).find('.fa-spinner').hide();
                            }
                        }
                    });
                } else {
                    var div = $('<div/>').addClass('alert alert-danger').text(response.data);
                    $(form).prepend(div);
                    $(form).find('.fa-spinner').hide();
                }
            }
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
