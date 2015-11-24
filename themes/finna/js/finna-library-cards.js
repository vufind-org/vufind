/*global VuFind*/
finna.libraryCards = (function() {

    var initPasswordModal = function() {
        $('.change-password-link a.modal-link').click(function() {
            var get = deparam(this.href);
            return Lightbox.get('LibraryCards', 'newPassword', get);
        });

        Lightbox.addFormHandler('newPasswordForm', function(evt) {
            if (evt.isDefaultPrevented()) {
                $('.fa.fa-spinner', evt.target).remove();
                return false;
            }
            $(evt.target).find(':submit').attr('disabled', true);
            Lightbox.submit($(evt.target), function(html) {
                $(evt.target).find(':submit').removeAttr('disabled');
                var type = "danger";
                var divPattern = '<div class="alert alert-'+type+'">';
                var fi = html.indexOf(divPattern);
                if (fi > -1) {
                  var li = html.indexOf('</div>', fi+divPattern.length);
                  Lightbox.displayError(html.substring(fi+divPattern.length, li).replace(/^[\s<>]+|[\s<>]+$/g, ''), type);
                  $('#hash').val($('<div/>').html(html).find('#hash').val());
                } else {
                  Lightbox.confirm(VuFind.translate('new_password_success'));
                }
            });
            return false;
        });
    };
    
    var my = {
        init: function() {
            initPasswordModal();
        },
    };

    return my;
})(finna);
