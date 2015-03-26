/* Finna Mozilla Persona login. */

finna.persona = (function (finna) {

    var getDestinationUrl = function (goingOut) {
        var url = window.location.href;
        // Check if we have changed tab
        var recordTabs = $('.recordTabs');
        if (recordTabs.length > 0) {
            var phref = recordTabs.find('.active a').prop('href');
            url = phref.split('#')[0];
        }
        console.log(goingOut);
        if (goingOut === true) {
            console.log('Going out '+url);
            url = url.split('?')[0];
            console.log('Cleared '+url);
        }
        console.log(url);
        return url;
    };

    var personaLogout = function () {
        $.ajax({
            type: "GET",
            dataType: "json",
            url: path + "/AJAX/JSON?method=personaLogout",
            success: function (response, status, xhr) {
                // No reload to avoid POST request problems
                window.location = getDestinationUrl(true);
            },
            error: function (xhr, status, err) {
                alert("logout failure: " + err);
            }
        });
    };

    var setSignoutLink = function () {
        var signoutLink = document.getElementById('persona-logout');
        if (signoutLink) {
            signoutLink.onclick = function () {
                navigator.id.logout();
                personaLogout();
                return false;
            };
        }
    };

    var mozillaPersonaSetup = function (currentUser, autoLogoutEnabled) {
        if (navigator.id === undefined || navigator.id === null) {
            console.log('Persona support not properly loaded');
            // Persona support not properly loaded
            return;
        }
        navigator.id.watch({
            loggedInUser: currentUser,
            onlogin: function (assertion) {
                $("#persona-login").addClass("persona-auth-loading");
                $.ajax({
                    type: "POST",
                    dataType: "json",
                    url: path + "/AJAX/JSON?method=personaLogin",
                    data: {
                        assertion: assertion
                    },
                    success: function (response, status, xhr) {
                        if (response.status === "OK") {
                            if (Lightbox.shown === false) {
                                // No reload to avoid POST request problems
                                window.location = getDestinationUrl(false);
                            } else {
                                var params = deparam(Lightbox.lastURL);
                                if (params.subaction === 'UserLogin') {
                                    window.location = getDestinationUrl(false);
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
                                    Lightbox.addCloseAction(finna.persona.setSignoutLink);
                                }
                            }
                        } else {
                            $("#persona-login").removeClass("persona-auth-loading");
                            navigator.id.logout();
                            alert("Login failed");
                        }
                    },
                    error: function (xhr, status, err) {
                        navigator.id.logout();
                        $("#persona-login").removeClass("persona-auth-loading");
                        alert("login failure: " + err);
                    }
                });
            },
            onlogout: function () {
                if (!currentUser || !autoLogoutEnabled) {
                    return;
                }
                personaLogout();
            }
        });

        var signinLink = document.getElementById('persona-login');
        if (signinLink) {
            signinLink.onclick = function () {
                navigator.id.request();
                return false;
            };
        }
        setSignoutLink();
    };

    var initPersona = function () {
        $.ajax({
            url: 'https://login.persona.org/include.js',
            dataType: 'script',
            success: function () {
                mozillaPersonaSetup(
                        mozillaPersonaCurrentUser ? mozillaPersonaCurrentUser : null,
                        mozillaPersonaAutoLogout ? true : false);
            }
        });
    };


    var my = {
        setSignoutLink: setSignoutLink,
        init: function () {
            initPersona();
        }
    };

    return my;

})(finna);
