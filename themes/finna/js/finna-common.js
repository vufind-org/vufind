/*global VuFind*/
finna.common = (function() {

    var loginSetup = function() {
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
            url: VuFind.path + '/AJAX/JSON?method=getSalt',
            dataType: 'json'
        })
        .done(function(response) {
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
                url: VuFind.path + '/AJAX/JSON?method=login',
                dataType: 'json',
                data: params
            }).done(function(response) {
                // No reload since any post params would cause a prompt
                window.location.href = window.location.href;
            })
            .fail(function(response, textStatus) {
                var div = $('<div/>').addClass('alert alert-danger').text(response.responseJSON.data);
                $(form).prepend(div);
                $(form).find('.fa-spinner').hide();
            });
        });
    };    
    
    var initSearchInputListener = function() {
        var searchInput = $('.searchForm_lookfor:visible');
        if (searchInput.length == 0) {
            return;
        }
        $(window).keypress(function(e) {
            if (e && (!$(e.target).is('input, textarea, select')) 
                  && !$('#modal').is(':visible') 
                  && (e.which >= 48) // Start from normal input keys
                  && !(e.metaKey || e.ctrlKey || e.altKey)
            ) {
                var letter = String.fromCharCode(e.which);
                
                // IE 8-9
                if (typeof document.createElement('input').placeholder == 'undefined') {
                    if (searchInput.val() == searchInput.attr('placeholder')) {
                      searchInput.val('');
                      searchInput.removeClass('placeholder');
                    }
                }
                
                // Move cursor to the end of the input
                var tmpVal = searchInput.val();
                searchInput.val(' ').focus().val(tmpVal + letter);
                
                // Scroll to the search form
                $('html, body').animate({scrollTop: searchInput.offset().top - 20}, 150);
               
                e.preventDefault();
           }
        });
    }
    
    var my = {
        init: function() {
            loginSetup();
            initSearchInputListener();
        }
    };

    return my;

})(finna);
