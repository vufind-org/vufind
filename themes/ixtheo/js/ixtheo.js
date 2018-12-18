function getSubscriptionBundleItems(bundle_id) {
  var query = "bundle_id:" + bundle_id;
  var datums = [];
  $.ajax({
    url: VuFind.path + '/AJAX/JSON',
    data: {
       method: 'getSubscriptionBundleEntries',
       bundle_id: bundle_id
    },
    dataType: 'json',
    success: function displaySubscriptionItems(json) {
      $(document).ready(function() {
        var data = $.parseJSON(json.data)
        if (data.items.length > 0) {
          datums = [];
          for (var j = 0; j < data.items.length; j++) {
            datums.push(data.items[j].title);
            $("#" + bundle_id).find("div").append(data.items[j].title);
          }
        }
      });
    },
    error: function(xhr, ajaxOptions, thrownError) {
      if (window.console && window.console.log) {
              console.log("Status: " + xhr.status + ", Error: " + thrownError);
      }
    }
  });
}

$(document).ready(function() {
   var previous_handler;
   $("#searchForm_type").on('focus', function () {
       previous_handler = this.value;
   }).change(function adjustSearchSort(e) {
     if (previous_handler == 'BibleRangeSearch') {
         var default_sort = $("#sort_options_1").data('default_sort');
         $("#sort_options_1").off(); // Prevent automatic reloading
         $("#sort_options_1").removeAttr('disabled'); //Handle leftover of forcing relevance search for bibrange
         $("#sort_options_1").val(default_sort).change();
         $(":input[name='sort']").val(default_sort);
         $("#sort_options_1").on();
         return false;
     }
   });
});
