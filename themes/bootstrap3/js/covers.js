
function loadCoversForResults(size = "small") {
    results = $('div.result');
    ids = [];
    results.each(function(index, element) {
        ids.push({
            elementId: $(element).attr("id"),
            data: {
                source: $(this).find(".hiddenSource").val(),
                recordId: $(this).find(".hiddenId").val(),
                size: size
            }
        });
    });
    ids.forEach(function(value) {
        loadCover(value.data, "#" + value.elementId + " .recordcover");
    });
}

function loadCoverForDetail(size = "small") {
    data = {
        source: $(".record .hiddenSource").val(),
        recordId: $(".record .hiddenId").val(),
        size: size
    };
    loadCover(data, ".record .recordcover");
}

function loadCover(data, selector) {
    url = VuFind.path + '/AJAX/JSON?method=' + 'getRecordCover';
    callback = function(response, status, xhr) {
        console.log(response);
        if (response.data.url !== false) {
            $(this.elementSelector).attr("src", response.data.url);
        }
    };
    $.ajax({
        dataType: "json",
        url: url,
        method: "GET",
        data: data,
        elementSelector: selector,
        success: callback
    });
}

//$(document).ready(function() {
//    loadCoversForResults();
//    loadCoverForDetail();
//});