
finna.userprofile = (function () {

    var deleteAccountLogout = function () {
        if ($('#delete-account-note').hasClass('process-logout')) {
            $('#delete-account-note').removeClass('process-logout');
            var timeoutID = window.setTimeout(function () {
                if ($("#persona-logout").length) {
                    navigator.id.logout();
                    finna.persona.personaLogout();
                } else {
                    window.location = path + "/MyResearch/Logout";
                }
            }, 2000);
        }
    };
    var my = {
        deleteAccountLogout: deleteAccountLogout
    };

    return my;
})(finna);

$(document).ready(function () {
    // My profile address change
    $('.profile-library-info-address-update').click(function () {
        var params = extractClassParams(this);
        return Lightbox.get(params.controller, 'ChangeProfileAddress');
    });

    // My profile messaging change
    $('#profile-messaging-update').click(function () {
        var params = extractClassParams(this);
        return Lightbox.get(params.controller, 'ChangeMessagingSettings');
    });

    // My profile delete account
    $('#delete-account button').click(function () {
        var params = extractClassParams(this);
        Lightbox.addOpenAction(function () {
            $('.modal-dialog #delete-account-cancel').on('click', function () {
                Lightbox.close();
            });
        });
        return Lightbox.get(params.controller, 'DeleteAccount');
    });
    Lightbox.addFormCallback('deleteAccount', function (html) {
        Lightbox.changeContent(html);
        finna.userprofile.deleteAccountLogout();
    });
});
