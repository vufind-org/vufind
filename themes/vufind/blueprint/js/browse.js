$(document).ready(function() {
    registerLoadHandlers();
});

function registerLoadHandlers() {
    registerLoadHandler('loadOptions', 'getOptionsAsHTML');
    registerLoadHandler('loadAlphabet', 'getAlphabetAsHTML');
    registerLoadHandler('loadSubjects', 'getSubjectsAsHTML');
}

function registerLoadHandler(linkClass, method) {
    $('ul.browse a.'+linkClass).each(function() {
        var params = extractParams($(this).attr('class'));
        params.query = $(this).attr('title');
        $(this).unbind('click').click(function() {
            highlightBrowseLink(this);
            loadBrowseOptions(method, params);
            return false;
        });
    });
}

function loadBrowseOptions(method, params) {
    $('#list4container').empty().removeClass('browseNav');
    $('#'+params.target).addClass('browseNav').empty()
        .append('<div class="dialogLoading"></div>');
    $.ajax({
        dataType: 'json',
        url: path + '/AJAX/JSON_Browse?method='+method,
        data: params,
        success: function(response) {
            if(response.status == 'OK') {
                $('#'+params.target).empty().append(response.data);
                registerLoadHandlers();
            }
        }
    }); 
}

function highlightBrowseLink(link) {
    $(link).parentsUntil('div.browseNav').children('li').removeClass('active');
    $(link).parent('li').addClass('active');    
}