/*global VuFind*/
finna.common = (function() {

    var navibarLogin = function() {
        // Check that the login attempt comes from the normal login dialog
        var params = deparam(Lightbox.lastURL);
        if (params['redirect']) {
            window.location = VuFind.getPath() + '/MyResearch/Home?redirect=0';
        }
    };

    var loginSetup = function() {        
        Lightbox.addFormHandler('loginForm', function(evt) {
            ajaxLogin(evt.target);
            Lightbox.addCloseAction(navibarLogin);
            return false;
        });

        // Modal window focus set to username input field.
        $('#modal').on('shown.bs.modal', function(e) {
            setTimeout(function() { $('#login_MultiILS_username').focus(); }, 0);
        });

        // Standalone login form
        $('#loginForm').submit(function(evt) { 
            evt.preventDefault();
            standaloneAjaxLogin(evt.target);
        });
        
        // Login link
        $('#loginOptions a.modal-link').unbind('click').click(function() {
            // Since we unbind the original handler, we need to handle the title here
            var title = $(this).attr('title');
            if(typeof title === "undefined") {
              title = $(this).html();
            }
            $('#modal .modal-title').html(title);
            Lightbox.titleSet = true;
            return Lightbox.get('MyResearch', 'UserLogin', { redirect: 1 });
        });
    };
    
    // ajaxLogin equivalent for non-lightbox login
    var standaloneAjaxLogin = function (form) {
        $(form).find('div.alert').remove();
        $(form).find('input[type=submit]').after('<i class="fa fa-spinner fa-spin"></i>');
        $.ajax({
            url: VuFind.getPath() + '/AJAX/JSON?method=getSalt',
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
                        url: VuFind.getPath() + '/AJAX/JSON?method=login',
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
            Lightbox.confirm(VuFind.translate('feedback_success'));
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
