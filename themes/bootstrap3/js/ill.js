/*global VuFind */
/*exported setUpILLRequestForm */
function setUpILLRequestForm(recordId) {
  $("#ILLRequestForm #pickupLibrary").change(function illPickupChange() {
    $("#ILLRequestForm #pickupLibraryLocation option").remove();
    $("#ILLRequestForm #pickupLibraryLocationLabel i").addClass("fa fa-spinner fa-spin");
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
        $.each(response.data.locations, function illPickupLocationEach() {
          var option = $("<option></option>").attr("value", this.id).text(this.name);
          if (this.isDefault) {
            option.attr("selected", "selected");
          }
          $("#ILLRequestForm #pickupLibraryLocation").append(option);
        });
        $("#ILLRequestForm #pickupLibraryLocationLabel i").removeClass("fa fa-spinner fa-spin");
      })
      .fail(function illPickupLocationsFail(/*response*/) {
        $("#ILLRequestForm #pickupLibraryLocationLabel i").removeClass("fa fa-spinner fa-spin");
      });
  });
  $("#ILLRequestForm #pickupLibrary").change();
}
