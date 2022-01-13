var TueFind2 = {
    // these options can be overridden in child themes
    Searchbox: {
        HeightOptions: {
            Enabled: true,
            MinHrefLength: 4,
            MinHeight: 300
        }
    },
    ExpandDropdownsOnHover: false,

    /**
    * - resize the box if we are not on the default page anymore (detected by url length)
    * - function needs to be called directly in searchbox, else (e.g. document.onload) it first pops out and then pops back again,
    *   which looks strange and also screws up with anchors
    */
    ChangeSearchboxHeight: function() {
        if (TueFind2.Searchbox.HeightOptions.Enabled) {
            let parts = window.location.href.split('/');
            if (parts.length > TueFind2.Searchbox.HeightOptions.MinHrefLength)
                $('.panel-home').css({
                   "min-height": TueFind2.Searchbox.HeightOptions.MinHeight,
                   "margin-bottom": "20px"
                });
        }
    },
    ChangeSearchboxHeightRelbib: function() {
        if (TueFind2.Searchbox.HeightOptions.Enabled) {
            let parts = window.location.href.split('/');
            if (parts.length > TueFind2.Searchbox.HeightOptions.MinHrefLength)
                $('.panel-home').css("min-height", 420);
        }
    }
}

$(function(){
    if (TueFind2.ExpandDropdownsOnHover === true) {
        $('.dropdown').hover(function() {
            $(this).addClass('open');
        },
        function() {
            $(this).removeClass('open');
        });
    }
});
