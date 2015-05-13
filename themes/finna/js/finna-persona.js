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
            }
        });
    };

    var setLoginLink = function() {
        var loginLink = document.getElementById('persona-login');
        if (loginLink) {
            loginLink.onclick = function() {
                navigator.id.request();
                return false;
            };
        }
    };

    var setLogoutLink = function() {
        var logoutLink = document.getElementById('persona-logout');
        if (logoutLink) {
            logoutLink.onclick = function() {
                navigator.id.logout();
                personaLogout();
                return false;
            };
        }
    };

    var mozillaPersonaSetup = function(currentUser, autoLogoutEnabled) {
        if (navigator.id === undefined || navigator.id === null) {
            // Persona support not properly loaded
            return;
        }
        navigator.id.watch({
            loggedInUser: currentUser,
            onlogin: function(assertion) {
                $("#persona-login").addClass("persona-login-loading");
                $.ajax({
                    type: "POST",
                    dataType: "json",
                    url: path + "/AJAX/JSON?method=personaLogin",
                    data: {
                        assertion: assertion
                    },
                    success: function(response, status, xhr) {
                        if (response.status === "OK") {
                            if (Lightbox.shown === false) {
                                // No reload to avoid POST request problems
                                window.location = getDestinationUrl(false);
                            } else {
                                var params = deparam(Lightbox.lastURL);
                                if (params.subaction === 'UserLogin') {
                                    if ( $('#loginOptions a').hasClass('navibar-login-on') ) {
                                        window.location = path+'/MyResearch/Home?redirect=0';
                                    } else {
                                        window.location = getDestinationUrl(false);
                                    }
                                } else {
                                    // Update the modal
                                    updatePageForLogin();
                                    Lightbox.getByUrl(
                                            Lightbox.lastURL,
                                            Lightbox.lastPOST,
                                            Lightbox.changeContent
                                            );
                                    // No page load, so have to add logout id
                                    $('i.fa-sign-out').parent().attr('id', 'persona-logout');
                                    Lightbox.addCloseAction(finna.persona.setLogoutLink);
                                }
                            }
                        } else {
                            $("#persona-login").removeClass("persona-login-loading");
                            navigator.id.logout();
                            alert("Login failed");
                        }
                    },
                    error: function(xhr, status, err) {
                        navigator.id.logout();
                        $("#persona-login").removeClass("persona-login-loading");
                        alert("login failure: " + err);
                    }
                });
            },
            onlogout: function() {
                if (!currentUser || !autoLogoutEnabled) {
                    return;
                }
                personaLogout();
            }
        });

        setLoginLink();
        setLogoutLink();
    };

    var initPersona = function() {
        $.ajax({
            url: 'https://login.persona.org/include.js',
            dataType: 'script',
            success: function() {
                mozillaPersonaSetup(
                        mozillaPersonaCurrentUser ? mozillaPersonaCurrentUser : null,
                        mozillaPersonaAutoLogout ? true : false);
            }
        });
    };


    var my = {
        setLogoutLink: setLogoutLink,
        setLoginLink: setLoginLink,
        personaLogout: personaLogout,
        init: function() {
            initPersona();
        }
    };

    return my;

})(finna);
