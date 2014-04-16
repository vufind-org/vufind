/*global path */
function setUpILLRequestForm(recordId) {
    $("#ILLRequestForm #pickupLibrary").change(function() {
        $("#ILLRequestForm #pickupLibraryLocation option").remove();
        $("#ILLRequestForm #pickupLibraryLocationLabel").addClass("ajax_ill_request_loading");
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
                $("#ILLRequestForm #pickupLibraryLocationLabel").removeClass("ajax_ill_request_loading");
            },
            fail: function() {
                $("#ILLRequestForm #pickupLibraryLocationLabel").removeClass("ajax_ill_request_loading");
            }
        });   
        
    });
    $("#ILLRequestForm #pickupLibrary").change();
}
