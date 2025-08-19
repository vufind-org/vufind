/**
 * Toggle the visibility of the #collectionInfo element.
 */
function toggleCollectionInfo() {
  $("#collectionInfo").toggle();
}

/**
 * Initialize a "show more info" toggle button if the table has rows.
 */
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
