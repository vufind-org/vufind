function toggleCollectionInfo() {
  $("#collectionInfo").toggle();
}

function showMoreInfoToggle() {
  toggleCollectionInfo();
  $("#moreInfoToggle").removeClass('hidden');
  $("#moreInfoToggle").click(function(e) {
    e.preventDefault();
    toggleCollectionInfo();
  });
}

$(document).ready(function() {
  showMoreInfoToggle();
});