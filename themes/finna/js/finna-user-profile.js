/*global VuFind*/

finna.userProfile = (function () {
    var deleteAccountLogout = function () {
        if ($('#delete-account-note').hasClass('process-logout')) {
            $('#delete-account-note').removeClass('process-logout');
            var timeoutID = window.setTimeout(function () {
                if ($(".persona-logout").length) {
                    navigator.id.logout();
                    finna.persona.personaLogout();
                } else {
                    window.location = VuFind.getPath() + "/MyResearch/Logout";
                }
            }, 2000);
        }
    };
    
    var initProfile = function () {
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
            deleteAccountLogout();
        });
    };
    
    var my = {
        deleteAccountLogout: deleteAccountLogout,
        init: function () {
            initProfile();
        }
    };

    return my;
})(finna);
