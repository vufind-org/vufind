/* Finna Mozilla Persona login. */

finna.persona = (function(finna) {

    var getDestinationUrl = function(loggingOut) {
        // After logout always move to the front page.
        if (loggingOut) {
            return path;
        }
        var url = window.location.href;
        // Check if we have changed tab
        var recordTabs = $('.recordTabs');
        if (recordTabs.length > 0) {
            var phref = recordTabs.find('.active a').prop('href');
            url = phref.split('#')[0];
        }
        return url;
    };

    var personaLogout = function() {
        // This can be reached from logout observer as well as logout link click 
        // event. Avoid double execution..
        if (finna.persona.logoutInProgress) {
            return false;
        }
        finna.persona.logoutInProgress = true;
        $.ajax({
            type: "GET",
            dataType: "json",
            url: path + "/AJAX/JSON?method=personaLogout",
            success: function(response, status, xhr) {
                // No reload to avoid POST request problems
                window.location = getDestinationUrl(true);
            },
            error: function(xhr, status, err) {
                alert("logout failure: " + err);
                finna.persona.logoutInProgress = false;
            }
        });
    };

    var setupLoginLinks = function() {
        $('.persona-login').click(function() {
            navigator.id.request();
            return false;
        });
    };

    var setupLogoutLinks = function() {
        $('.persona-logout').unbind('click').click('click', function(event) {
            finna.persona.autoLogoutEnabled = false;
            navigator.id.logout();
            personaLogout();
            return false;
        });
    };

    var mozillaPersonaSetup = function(currentUser, autoLogoutEnabled) {
        if (navigator.id === undefined || navigator.id === null) {
            // Persona support not properly loaded
            return;
        }
        finna.persona.logoutInProgress = false;
        finna.persona.autoLogoutEnabled = autoLogoutEnabled;
        navigator.id.watch({
            loggedInUser: currentUser,
            onlogin: function(assertion) {
                $('.persona-login i').addClass('fa-spinner fa-spin');
                $.ajax({
                    type: "POST",
                    dataType: "json",
                    url: path + "/AJAX/JSON?method=personaLogin",
                    data: {
                        assertion: assertion
                    },
                    success: function(response, status, xhr) {
                        if (response.status === "OK") {
                            Lightbox.addCloseAction(refreshPageForLogin);
                            // and we update the modal
                            var params = deparam(Lightbox.lastURL);
                            if (params['subaction'] == 'UserLogin') {
                                Lightbox.close();
                            } else {
                                Lightbox.getByUrl(
                                    Lightbox.lastURL,
                                    Lightbox.lastPOST,
                                    Lightbox.changeContent
                                );
                            }
                        } else {
                            $('.persona-login i').removeClass('fa-spinner fa-spin');
                            navigator.id.logout();
                            alert("Login failed");
                        }
                    },
                    error: function(xhr, status, err) {
                        navigator.id.logout();
                        $('.persona-login i').removeClass('fa-spinner fa-spin');
                        alert("login failure: " + err);
                    }
                });
            },
            onlogout: function() {
                if (!currentUser || !finna.persona.autoLogoutEnabled) {
                    return;
                }
                personaLogout();
            }
        });

        setupLoginLinks();
        setupLogoutLinks();
    };

    var initPersona = function() {
        $.ajax({
            url: 'https://login.persona.org/include.js',
            dataType: 'script',
            success: function() {
                mozillaPersonaSetup(
                    mozillaPersonaCurrentUser ? mozillaPersonaCurrentUser : null,
                    mozillaPersonaAutoLogout ? true : false
                );
            }
        });
    };


    var my = {
        setupLogoutLinks: setupLogoutLinks,
        setupLoginLinks: setupLoginLinks,
        personaLogout: personaLogout,
        init: function() {
            initPersona();
        }
    };

    return my;

})(finna);
