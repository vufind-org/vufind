/*global path */
function setUpILLRequestForm(recordId) {
    $("#ILLRequestForm #pickupLibrary").change(function() {
        $("#ILLRequestForm #pickupLibraryLocation option").remove();
        $("#ILLRequestForm #pickupLibraryLocationLabel i").addClass("fa fa-spinner icon-spin");
        var url = path + '/AJAX/JSON?' + $.param({method:'getLibraryPickupLocations', id: recordId, pickupLib: $("#ILLRequestForm #pickupLibrary").val() });
        $.ajax({
            dataType: 'json',
            cache: false,
            url: url,
            success: function(response) {
                if (response.status == 'OK') {
                    $.each(response.data.locations, function() {
                        var option = $("<option></option>").attr("value", this.id).text(this.name);
                        if (this.isDefault) {
                            option.attr("selected", "selected");
                        }
                        $("#ILLRequestForm #pickupLibraryLocation").append(option);
                    });
                }
                $("#ILLRequestForm #pickupLibraryLocationLabel i").removeClass("fa fa-spinner icon-spin");
            },
            fail: function() {
                $("#ILLRequestForm #pickupLibraryLocationLabel i").removeClass("fa fa-spinner icon-spin");
            }
        });   
        
    });
    $("#ILLRequestForm #pickupLibrary").change();
}
