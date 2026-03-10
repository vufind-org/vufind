/**
 origin: {vufind/bootstrap3}
 called by view helper/controller:
 usage: {}
 modified for finc:
 * Keep Accordion OPEN on load - CK #12819
 * fix toggler behaviour in collection-info #13039
 configured in: {}
 */

function toggleCollectionInfo() {
  $("#collectionInfo").toggle();
}

function showMoreInfoToggle() {
  // no rows in table? don't bother!
  if ($("#collectionInfo").find('tr').length < 1) {
    return;
  }
  // finc: keeps Accordion OPEN on load - CK #12819
  // toggleCollectionInfo();
  $("#moreInfoToggle").removeClass('hidden');
  // finc: fixes toggler behaviour in collection-info #13039
  //$("#moreInfoToggle").click(function moreInfoToggleClick(e) {
  //    e.preventDefault();
  //    toggleCollectionInfo();
  //});
}

$(document).ready(function collectionRecordReady() {
  showMoreInfoToggle();
});
