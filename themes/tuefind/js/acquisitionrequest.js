var AcquisitionRequest = {

    checkFields: function() {
        var book = document.getElementById('ar-book');
        var firstname = document.getElementById('ar-firstname');
        var lastname = document.getElementById('ar-lastname');
        var email = document.getElementById('ar-email');
        var submit = document.getElementById('ar-submit');

        submit.disabled = (book.value == '' || firstname.value == '' || lastname.value == '');
    }
}
