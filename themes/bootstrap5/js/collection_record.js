function toggleCollectionInfo() {
  $("#collectionInfo").toggle();
}

function showMoreInfoToggle() {
  // no rows in table? don't bother!
  if ($("#collectionInfo").find('tr').length < 1) {
    return;
  }
  toggleCollectionInfo();
  $("#moreInfoToggle").removeClass('hidden');
  $("#moreInfoToggle").on("click", function moreInfoToggleClick(e) {
    e.preventDefault();
    toggleCollectionInfo();
  });
}

$(function collectionRecordReady() {
  showMoreInfoToggle();
});
