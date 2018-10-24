var AcquisitionRequest = {

    checkFields: function(tuefind_type) {
        var book = document.getElementById('ar-book');
        var firstname = document.getElementById('ar-firstname');
        var lastname = document.getElementById('ar-lastname');
        var email = document.getElementById('ar-email');
        var submit = document.getElementById('ar-submit');

        if (tuefind_type == 'Krimdok')
            submit.disabled = (book.value == '' || firstname.value == '' || lastname.value == '');
        else
           submit.disabled = (book.value == '' || firstname.value == '' || lastname.value == '' || email.value =='');

    }
}

$(document).ready(function(){
    $('[data-toggle="tooltip"]').tooltip();
});

