/*global VuFind */
/*exported setUpILLRequestForm */
function setUpILLRequestForm(recordId) {
  $("#ILLRequestForm #pickupLibrary").change(function illPickupChange() {
    $("#ILLRequestForm #pickupLibraryLocation option").remove();
    $("#ILLRequestForm #pickupLibraryLocationLabel i").addClass("fa fa-spinner icon-spin");
    var url = VuFind.path + '/AJAX/JSON?' + $.param({
      id: recordId,
      method: 'getLibraryPickupLocations',
      pickupLib: $("#ILLRequestForm #pickupLibrary").val()
    });
    $.ajax({
      dataType: 'json',
      cache: false,
      url: url
    })
    .done(function illPickupLocationsDone(response) {
      var homeLibrary = $("#ILLRequestForm #homeLibrary").val();
      $.each(response.data.locations, function illPickupLocationEach() {
        var option = $("<option></option>").attr("value", this.id).text(this.name);
        if (homeLibrary != "" && option.attr("value") == homeLibrary) {
          option.attr("selected", "selected");
        } else if (this.isDefault) {
          option.attr("selected", "selected");
        }
        $("#ILLRequestForm #pickupLibraryLocation").append(option);
      });
      $("#ILLRequestForm #pickupLibraryLocationLabel i").removeClass("fa fa-spinner icon-spin");
    })
    .fail(function illPickupLocationsFail(/*response*/) {
      $("#ILLRequestForm #pickupLibraryLocationLabel i").removeClass("fa fa-spinner icon-spin");
    });
  });
  $("#ILLRequestForm #pickupLibrary").change();
}
