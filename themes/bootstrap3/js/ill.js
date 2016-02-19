/*global VuFind */
function setUpILLRequestForm(recordId) {
  $("#ILLRequestForm #pickupLibrary").change(function() {
    $("#ILLRequestForm #pickupLibraryLocation option").remove();
    $("#ILLRequestForm #pickupLibraryLocationLabel i").addClass("fa fa-spinner icon-spin");
    var url = VuFind.getPath() + '/AJAX/JSON?' + $.param({
      id: recordId,
      method:'getLibraryPickupLocations',
      pickupLib: $("#ILLRequestForm #pickupLibrary").val()
    });
    $.ajax({
      dataType: 'json',
      cache: false,
      url: url
    })
    .done(function(response) {
      $.each(response.data.locations, function() {
        var option = $("<option></option>").attr("value", this.id).text(this.name);
        if (this.isDefault) {
          option.attr("selected", "selected");
        }
        $("#ILLRequestForm #pickupLibraryLocation").append(option);
      });
      $("#ILLRequestForm #pickupLibraryLocationLabel i").removeClass("fa fa-spinner icon-spin");
    })
    .fail(function(response) {
      $("#ILLRequestForm #pickupLibraryLocationLabel i").removeClass("fa fa-spinner icon-spin");
    });
  });
  $("#ILLRequestForm #pickupLibrary").change();
}
