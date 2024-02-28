/*global VuFind */
/*exported setUpILLRequestForm */
function setUpILLRequestForm(recordId) {
  $("#ILLRequestForm #pickupLibrary").on("change", function illPickupChange() {
    $("#ILLRequestForm #pickupLibraryLocation option").remove();
    $("#ILLRequestForm #pickupLibraryLocationLabel .loading-icon").show();
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
        $("#ILLRequestForm #pickupLibraryLocationLabel .loading-icon").hide();
      })
      .fail(function illPickupLocationsFail(/*response*/) {
        $("#ILLRequestForm #pickupLibraryLocationLabel .loading-icon").hide();
      });
  });
  $("#ILLRequestForm #pickupLibrary").trigger("change");
}
