function toggleCollectionInfo() {
    $("#collectionInfo").toggle();
}

function showMoreInfoToggle() {
    toggleCollectionInfo();
    $("#moreInfoToggle").show();
    $("#moreInfoToggle").click(function(e) {
        e.preventDefault();
        toggleCollectionInfo();
    });
}

$(document).ready(function() {
    showMoreInfoToggle();
});