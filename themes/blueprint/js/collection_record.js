$(document).ready(function() {
    showMoreInfoToggle();
});

function showMoreInfoToggle() {
    toggleCollectionInfo();
    $("#moreInfoToggle").show();
    $("#moreInfoToggle").click(function(e) {
        e.preventDefault();
        toggleCollectionInfo();
    });
}

function toggleCollectionInfo() {
    $("#collectionInfo").toggle();
}
