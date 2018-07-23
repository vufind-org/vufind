var Tuefind = {
    // these options can be overridden in child themes
    Searchbox: {
        HeightOptions: {
            Enabled: true,
            MinHrefLength: 4,
            MinHeight: 300
        }
    },

    /**
    * - resize the box if we are not on the default page anymore (detected by url length)
    * - function needs to be called directly in searchbox, else (e.g. document.onload) it first pops out and then pops back again,
    *   which looks strange and also screws up with anchors
    */
    ChangeSearchboxHeight: function() {
        if (this.Searchbox.HeightOptions.Enabled) {
            var parts = window.location.href.split('/');
            if (parts.length > this.Searchbox.HeightOptions.MinHrefLength)
                $('.panel-home').css("min-height", this.Searchbox.HeightOptions.MinHeight);
        }
    }

};
